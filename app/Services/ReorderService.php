<?php

namespace App\Services;

use App\Data\ReorderFilterData;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\ProductUnit;
use App\Repositories\Contracts\BranchProductStockRepositoryInterface;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Should I buy this, and how much?"
 *
 * Sales velocity joined to stock on hand. The two halves have always existed
 * separately — the product-sales report knows what sold, `branch_product_stocks`
 * knows what is left — and nothing put them side by side, which is the entire
 * decision a sari-sari owner makes when restocking.
 *
 * Deliberately not part of `StockService`: that class is the single writer of
 * the stock ledger, and nothing here writes a movement. The one thing this does
 * persist is `reorder_point`, which is not ledger data.
 */
class ReorderService
{
    private const QTY = 3;

    /** Velocity is carried wide and rounded once, at the end. */
    private const RATE = 4;

    public function __construct(
        protected readonly BranchProductStockRepositoryInterface $stocks,
    ) {}

    /**
     * @return array{from:string, to:string, days:int, cover_target_days:int, low_confidence:bool, rows:list<array<string,mixed>>}
     */
    public function report(Branch $branch, ReorderFilterData $filter): array
    {
        $days = $this->days($filter);

        // Three days of sales divided by three is not a rate. Computed anyway —
        // refusing would be worse than labelling — but the caller is told.
        $lowConfidence = $days < 7;

        $rows = $this->stocks->reorderRows($branch->id, $filter->from, $filter->to)
            ->map(fn (BranchProductStock $stock) => $this->row($stock, $days, $filter))
            ->sortByDesc(fn (array $row) => (float) $row['suggested_base'])
            ->values()
            ->all();

        return [
            'from' => $filter->from,
            'to' => $filter->to,
            'days' => $days,
            'cover_target_days' => $filter->cover_target_days,
            'low_confidence' => $lowConfidence,
            'rows' => $rows,
        ];
    }

    /**
     * Write the accepted reorder points.
     *
     * The first code in this project ever to set `reorder_point` above zero.
     * `BranchProductStock::low()` requires it, so until now the low-stock alert
     * has never matched a single row.
     *
     * @param  list<array{product_id:int, reorder_point:float|string, reorder_quantity?:float|string|null}>  $points
     * @return int how many rows were written
     */
    public function saveReorderPoints(Branch $branch, array $points): int
    {
        return DB::transaction(function () use ($branch, $points) {
            $written = 0;

            foreach ($points as $point) {
                // Scoped to the branch in the lookup, not just in validation: a
                // product id that exists in another branch must not silently
                // create a row here.
                $stock = BranchProductStock::query()
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $point['product_id'])
                    ->first();

                if ($stock === null) {
                    continue;
                }

                $stock->reorder_point = $this->qty($point['reorder_point']);

                if (isset($point['reorder_quantity'])) {
                    $stock->reorder_quantity = $this->qty($point['reorder_quantity']);
                }

                $stock->save();
                $written++;
            }

            return $written;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(BranchProductStock $stock, int $days, ReorderFilterData $filter): array
    {
        $sold = $this->qty($stock->sold_base ?? 0);
        $returned = $this->qty($stock->returned_base ?? 0);
        $onHand = $this->qty($stock->quantity_on_hand);

        // Net, not gross: a returned item was never demand.
        $net = bcsub($sold, $returned, self::QTY);

        if (bccomp($net, '0', self::QTY) < 0) {
            $net = '0.000';
        }

        // Divide wide, round once — bcmath truncates, and a velocity truncated
        // here is then multiplied by the cover target, compounding the loss.
        $avgDaily = Money::divide($net, (string) max($days, 1), self::RATE);

        $sellsNothing = bccomp($avgDaily, '0', self::RATE) === 0;

        // How much to hold. The reorder point — the level that triggers the
        // low warning — is not derived from this: it is a quantity the owner
        // sets per product, because that is how they think about it.
        $target = $sellsNothing
            ? '0.000'
            : Money::round(bcmul($avgDaily, (string) $filter->cover_target_days, self::RATE), self::QTY);

        // max(0, …): an overstocked product suggests nothing, never a negative
        // order.
        //
        // No "on order" term any more. It counted outstanding purchase-order
        // lines, and purchase orders are gone — a sari-sari delivery arrives
        // the same day it is asked for, so there was nothing in flight to
        // subtract.
        $shortfall = bcsub($target, $onHand, self::QTY);

        if (bccomp($shortfall, '0', self::QTY) < 0) {
            $shortfall = '0.000';
        }

        $unit = $this->purchaseUnit($stock);
        $factor = $unit !== null && bccomp((string) $unit->conversion_factor, '0', 4) > 0
            ? (string) $unit->conversion_factor
            : '1';

        // Half a kilo of bigas is a real quantity; half an itlog is not. A
        // reorder point of "1.806 piraso" cannot be counted on a shelf, so
        // countable units round up to something actionable.
        $fractional = (bool) ($stock->product?->baseUnit?->allows_fraction ?? false);

        $shortfall = $this->whole($shortfall, $fractional);
        $target = $this->whole($target, $fractional);

        return [
            'product_id' => $stock->product_id,
            'product_name' => $stock->product?->name,
            'base_unit' => $stock->product?->baseUnit?->abbreviation,
            'sold_base' => $sold,
            'returned_base' => $returned,
            'net_sold_base' => $net,
            'avg_daily' => $avgDaily,
            'quantity_on_hand' => $onHand,
            // Null rather than a huge number: a product selling nothing has no
            // meaningful days of cover, and "999999" would sort as urgent.
            'days_cover' => $sellsNothing ? null : Money::divide($onHand, $avgDaily, 1),
            'reorder_point' => (string) $stock->reorder_point,
            'target_stock' => $target,
            'suggested_base' => $shortfall,
            // Whole purchase units, rounded UP — you cannot order two-thirds of
            // a box, and rounding down guarantees running short.
            'suggested_quantity' => (int) ceil((float) $shortfall / (float) $factor),
            'purchase_unit' => $unit === null ? null : [
                'id' => $unit->id,
                'name' => $unit->unit?->name,
                'abbreviation' => $unit->unit?->abbreviation,
                'conversion_factor' => (string) $unit->conversion_factor,
            ],
        ];
    }

    /**
     * The unit this product is actually bought in.
     *
     * Preference order, because the answer has to be actionable at the order
     * form rather than merely correct:
     *
     *   1. what a supplier last sold it in — recorded on every goods receipt
     *   2. the largest pack that exists for the product
     *   3. the base unit, so there is always an answer
     */
    private function purchaseUnit(BranchProductStock $stock): ?ProductUnit
    {
        $product = $stock->product;

        if ($product === null) {
            return null;
        }

        $units = $product->units;

        $lastBought = $product->supplierProducts
            ?->firstWhere(fn ($row) => $row->product_unit_id !== null)?->product_unit_id;

        if ($lastBought !== null) {
            $match = $units->firstWhere('id', $lastBought);

            // Honoured when it is a real pack, or when it is the base unit —
            // a supplier that genuinely sells singles is fine. Refused only
            // for a non-base unit converting 1:1, which is the base unit under
            // another name: labelling a quantity with it turns "buy 8 pieces"
            // into "buy 8 dozen", a 12x over-order with no error anywhere.
            if ($match !== null && ($this->isPack($match) || $match->is_base)) {
                return $match;
            }
        }

        // Only a unit that genuinely holds more than one base unit is a pack.
        // A "dozen" configured with a conversion factor of 1 is the base unit
        // wearing another name, and presenting it as a pack turns "buy 8
        // pieces" into "buy 8 dozen" — a 12x over-order with no error anywhere.
        $packs = $units->filter(fn (ProductUnit $unit) => $this->isPack($unit));

        if ($packs->isNotEmpty()) {
            return $packs->sortByDesc(fn (ProductUnit $unit) => (float) $unit->conversion_factor)->first();
        }

        return $units->firstWhere('is_base', true) ?? $units->first();
    }

    /** A pack holds more than one base unit. Anything else is the base unit. */
    private function isPack(ProductUnit $unit): bool
    {
        return bccomp((string) $unit->conversion_factor, '1', 4) > 0;
    }

    /**
     * Round up to a whole unit unless fractions of it are a real quantity.
     *
     * Up, never down: a reorder point rounded down warns too late, and an
     * order rounded down leaves you short.
     */
    private function whole(string $value, bool $fractional): string
    {
        if ($fractional) {
            return $value;
        }

        return bcadd((string) (int) ceil((float) $value), '0', self::QTY);
    }

    private function days(ReorderFilterData $filter): int
    {
        // Inclusive: a report for a single day covers one day, not zero.
        return Carbon::parse($filter->from)->startOfDay()
            ->diffInDays(Carbon::parse($filter->to)->startOfDay()) + 1;
    }

    private function qty(string|float|int|null $value): string
    {
        return bcadd((string) ($value ?? 0), '0', self::QTY);
    }
}
