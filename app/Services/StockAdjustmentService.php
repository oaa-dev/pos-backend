<?php

namespace App\Services;

use App\Data\StockAdjustmentItemData;
use App\Enums\StockAdjustmentReasonEnum;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adjustments are the shrinkage accountability trail: every discrepancy is
 * attributable to a person, a reason, and a moment. The reason decides which
 * movement type the ledger records, so spoilage, expiry, personal use and
 * freebies stay separable in reports rather than collapsing into one
 * "adjustment" bucket that explains nothing.
 */
class StockAdjustmentService extends BaseService
{
    public function __construct(
        protected readonly StockAdjustmentRepositoryInterface $adjustments,
        protected readonly StockService $stock,
    ) {
        parent::__construct($adjustments);
    }

    /**
     * @param  list<array{product_id: int, product_unit_id: int, quantity: float|string, note?: string|null}>  $items
     */
    /** @param  list<StockAdjustmentItemData>  $items */
    public function record(Branch $branch, StockAdjustmentReasonEnum $reason, array $items, ?string $note = null): StockAdjustment
    {
        return DB::transaction(function () use ($branch, $reason, $items, $note) {
            $adjustment = $this->adjustments->createAdjustment([
                'reference_no' => 'ADJ-'.Str::upper(Str::random(10)),
                'branch_id' => $branch->id,
                'reason' => $reason->value,
                'note' => $note,
                'adjusted_by' => Auth::id(),
                'occurred_at' => now(),
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item->product_id);
                $productUnit = ProductUnit::findOrFail($item->product_unit_id);

                $quantity = (string) $item->quantity;
                $inbound = bccomp($quantity, '0', 3) > 0;
                $magnitude = ltrim($quantity, '-');

                $movementType = $reason->movementType($inbound);

                if ($inbound) {
                    // An inbound correction has no delivery behind it, so it
                    // is costed at what the product currently averages rather
                    // than at zero — otherwise a recount would quietly
                    // devalue the stock it restores.
                    $this->stock->receive(
                        branch: $branch,
                        product: $product,
                        productUnit: $productUnit,
                        quantity: $magnitude,
                        type: $movementType,
                        unitCost: $this->currentUnitCost($branch, $product, $productUnit),
                        reference: $adjustment,
                        note: $item->note,
                    );
                } else {
                    $this->stock->issue(
                        branch: $branch,
                        product: $product,
                        productUnit: $productUnit,
                        quantity: $magnitude,
                        type: $movementType,
                        reference: $adjustment,
                        note: $item->note,
                    );
                }

                $this->adjustments->addItem($adjustment, [
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'quantity_entered' => $quantity,
                    'quantity_base' => bcmul($quantity, (string) $productUnit->conversion_factor, 3),
                    'note' => $item->note,
                ]);
            }

            return $adjustment->load(['items.product', 'adjustedBy']);
        });
    }

    /**
     * Average cost is held per base unit; `receive()` divides by the
     * conversion factor, so multiply back up to keep the round trip exact.
     */
    private function currentUnitCost(Branch $branch, Product $product, ProductUnit $productUnit): string
    {
        $stock = $branch->productStocks()
            ->where('product_id', $product->id)
            ->first();

        return bcmul(
            (string) ($stock?->average_cost ?? '0'),
            (string) $productUnit->conversion_factor,
            4,
        );
    }
}
