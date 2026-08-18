<?php

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Repositories\Contracts\BranchProductStockRepositoryInterface;
use App\Repositories\Contracts\ProductBatchRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of the stock ledger.
 *
 * Every method updates `stock_movements` and the `branch_product_stocks`
 * cache inside one transaction, taking a row lock on the cache first. Two
 * tinderas in the same branch selling the last sachet is a real race, and the
 * lock is what serialises them.
 *
 * All quantity arithmetic goes through bcmath at scale 3. These figures come
 * from decimal columns and multi-UOM conversions (25.5 kg per sako); binary
 * floating point drifts, and the drift accumulates in a ledger that is never
 * rewritten.
 */
class StockService
{
    private const QTY_SCALE = 3;

    private const COST_SCALE = 4;

    public function __construct(
        protected readonly StockMovementRepositoryInterface $movements,
        protected readonly BranchProductStockRepositoryInterface $stocks,
        protected readonly ProductBatchRepositoryInterface $batches,
    ) {}

    /**
     * Bring stock in — a delivery, an opening balance, a return, a repack in.
     *
     * Creates or merges a batch, writes one movement, and moves the cache.
     */
    public function receive(
        Branch $branch,
        Product $product,
        ProductUnit $productUnit,
        string|float|int $quantity,
        // No default. A silent `PURCHASE_RECEIPT` is what made the stock-in
        // form write deliveries for months; naming the type is now the
        // caller's job, and every existing caller already does.
        StockMovementTypeEnum $type,
        string|float|int $unitCost = 0,
        ?string $expiryDate = null,
        ?string $batchCode = null,
        ?Model $reference = null,
        ?string $note = null,
    ): StockMovement {
        $entered = $this->normalise($quantity);
        $base = $this->toBase($productUnit, $entered);

        if (bccomp($base, '0', self::QTY_SCALE) <= 0) {
            throw new \InvalidArgumentException('A receipt must have a quantity greater than zero.');
        }

        // Cost is per base unit — a box costing ₱700 for 100 sachets is ₱7.00
        // a sachet, and the batch has to hold the per-sachet figure or FIFO
        // costing is wrong by the conversion factor.
        //
        // Divided wide then rounded, not truncated: a box of 12 at ₱100 is
        // ₱8.3333 truncated, which multiplies back to ₱99.9996 against the
        // ₱100.00 paid, and the shortfall recurs on every delivery.
        // Kept as typed as well as converted. Deriving the entered figure back
        // from the base one drifts on the rounding, and reconciling a ledger
        // row against a supplier's invoice needs the number on the invoice.
        $enteredUnitCost = $this->normalise($unitCost, self::COST_SCALE);

        $baseUnitCost = Money::divide(
            $enteredUnitCost,
            (string) $productUnit->conversion_factor,
            self::COST_SCALE,
        );

        return DB::transaction(function () use (
            $branch, $product, $productUnit, $entered, $base,
            $baseUnitCost, $enteredUnitCost, $type, $expiryDate, $batchCode, $reference, $note
        ) {
            $stock = $this->stocks->lockFor($branch->id, $product->id);

            $batch = $this->batches->findMergeable($branch->id, $product->id, $expiryDate, $baseUnitCost)
                ?? $this->batches->createBatch([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'batch_code' => $batchCode,
                    'expiry_date' => $expiryDate,
                    'quantity_remaining' => 0,
                    'unit_cost' => $baseUnitCost,
                    'received_at' => now(),
                ]);

            $this->batches->addQuantity($batch, $base);

            $movement = $this->movements->record([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'quantity_entered' => $entered,
                'quantity_base' => $base,
                'type' => $type->value,
                'batch_id' => $batch->id,
                'unit_cost' => $baseUnitCost,
                'unit_cost_entered' => $enteredUnitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => Auth::id(),
                'occurred_at' => now(),
                'note' => $note,
            ]);

            $this->applyReceiptToCache($stock, $base, $baseUnitCost);

            return $movement;
        });
    }

    /**
     * Take stock out — a sale, spoilage, personal use, a transfer out.
     *
     * Deducts FEFO across batches, so a quantity spanning two batches produces
     * two movements, each carrying the cost of the batch it drew from. That is
     * what makes cost of goods sold accurate rather than averaged.
     *
     * @return Collection<int, StockMovement>
     */
    public function issue(
        Branch $branch,
        Product $product,
        ProductUnit $productUnit,
        string|float|int $quantity,
        StockMovementTypeEnum $type = StockMovementTypeEnum::SALE,
        ?Model $reference = null,
        ?string $note = null,
    ): Collection {
        $entered = $this->normalise($quantity);
        $base = $this->toBase($productUnit, $entered);

        if (bccomp($base, '0', self::QTY_SCALE) <= 0) {
            throw new \InvalidArgumentException('An issue must have a quantity greater than zero.');
        }

        return DB::transaction(function () use (
            $branch, $product, $productUnit, $entered, $base, $type, $reference, $note
        ) {
            $stock = $this->stocks->lockFor($branch->id, $product->id);

            if (bccomp((string) $stock->quantity_on_hand, $base, self::QTY_SCALE) < 0) {
                throw new InsufficientStockException(sprintf(
                    'Only %s %s of %s left in %s.',
                    rtrim(rtrim((string) $stock->quantity_on_hand, '0'), '.'),
                    $product->baseUnit?->abbreviation ?? 'units',
                    $product->name,
                    $branch->name,
                ));
            }

            $movements = collect();
            $remaining = $base;

            foreach ($this->batches->fefoOpen($branch->id, $product->id) as $batch) {
                if (bccomp($remaining, '0', self::QTY_SCALE) <= 0) {
                    break;
                }

                $available = $this->normalise($batch->quantity_remaining);
                $take = bccomp($available, $remaining, self::QTY_SCALE) <= 0 ? $available : $remaining;

                $this->batches->subtractQuantity($batch, $take);

                $movements->push($this->movements->record([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    // Entered quantity belongs to the whole issue, so it is
                    // reported on the first movement only; splitting it across
                    // batches would double-count on replay.
                    'quantity_entered' => $movements->isEmpty() ? $entered : 0,
                    'quantity_base' => '-'.$take,
                    'type' => $type->value,
                    'batch_id' => $batch->id,
                    'unit_cost' => $batch->unit_cost,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'user_id' => Auth::id(),
                    'occurred_at' => now(),
                    'note' => $note,
                ]));

                $remaining = bcsub($remaining, $take, self::QTY_SCALE);
            }

            // The cache said there was enough but the batches did not cover
            // it — the two have drifted. Refuse rather than silently issue
            // uncosted stock; `inventory:reconcile` is the repair path.
            if (bccomp($remaining, '0', self::QTY_SCALE) > 0) {
                throw new InsufficientStockException(sprintf(
                    'Stock for %s in %s is inconsistent: %s base units unaccounted for in batches. Run inventory:reconcile.',
                    $product->name,
                    $branch->name,
                    $remaining,
                ));
            }

            $this->stocks->adjustQuantity($stock, '-'.$base);

            return $movements;
        });
    }

    /**
     * Stock counted in with no supplier and no money behind it.
     *
     * The one thing this does that `receive()` does not is name the movement
     * type. Everything else is pass-through — which is why `batchCode` and
     * `note` are here: the stock-in form accepts both, and dropping them
     * silently would be the same class of bug as the mislabelling this method
     * exists to prevent.
     */
    public function openingBalance(
        Branch $branch,
        Product $product,
        ProductUnit $productUnit,
        string|float|int $quantity,
        string|float|int $unitCost = 0,
        ?string $expiryDate = null,
        ?string $batchCode = null,
        ?string $note = null,
    ): StockMovement {
        return $this->receive(
            branch: $branch,
            product: $product,
            productUnit: $productUnit,
            quantity: $quantity,
            type: StockMovementTypeEnum::OPENING_BALANCE,
            unitCost: $unitCost,
            expiryDate: $expiryDate,
            batchCode: $batchCode,
            note: $note ?? 'Opening balance',
        );
    }

    /**
     * Recompute the cache from the ledger and report the drift.
     *
     * @return array{quantity_before: string, quantity_after: string, drift: string}
     */
    public function reconcile(int $branchId, int $productId): array
    {
        return DB::transaction(function () use ($branchId, $productId) {
            $stock = $this->stocks->lockFor($branchId, $productId);

            $before = $this->normalise($stock->quantity_on_hand);
            $after = $this->normalise($this->movements->sumBaseQuantity($branchId, $productId));

            $this->stocks->setQuantity($stock, $after);

            return [
                'quantity_before' => $before,
                'quantity_after' => $after,
                'drift' => bcsub($after, $before, self::QTY_SCALE),
            ];
        });
    }

    /**
     * Weighted average, recomputed on every receipt. Display only — the true
     * cost of a sale comes from the batch it drew from.
     */
    private function applyReceiptToCache(BranchProductStock $stock, string $base, string $unitCost): void
    {
        $currentQty = $this->normalise($stock->quantity_on_hand);
        $newQty = bcadd($currentQty, $base, self::QTY_SCALE);

        $average = bccomp($newQty, '0', self::QTY_SCALE) > 0
            ? bcdiv(
                bcadd(
                    bcmul($currentQty, (string) $stock->average_cost, self::COST_SCALE),
                    bcmul($base, $unitCost, self::COST_SCALE),
                    self::COST_SCALE,
                ),
                $newQty,
                self::COST_SCALE,
            )
            : $unitCost;

        $this->stocks->setQuantity($stock, $newQty, $average);
    }

    private function toBase(ProductUnit $productUnit, string $quantity): string
    {
        return bcmul($quantity, (string) $productUnit->conversion_factor, self::QTY_SCALE);
    }

    private function normalise(string|float|int $value, int $scale = self::QTY_SCALE): string
    {
        return bcadd((string) $value, '0', $scale);
    }
}
