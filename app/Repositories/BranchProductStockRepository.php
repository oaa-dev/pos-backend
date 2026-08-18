<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\BranchProductStock;
use App\Repositories\Contracts\BranchProductStockRepositoryInterface;
use App\Repositories\Contracts\BranchScopedInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BranchProductStockRepository extends BaseRepository implements BranchProductStockRepositoryInterface, BranchScopedInterface
{
    protected function model(): string
    {
        return BranchProductStock::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'product.name',
                'product.sku',
                'product.plu_code',
            ])),
            'branch_id',
            'product_id',
            AllowedFilter::callback('low', fn ($query) => $query->low()),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'quantity_on_hand'];
    }

    protected function allowedIncludes(): array
    {
        return ['product', 'branch'];
    }

    protected function defaultSort(): string
    {
        return 'id';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['product.baseUnit', 'branch']);
    }

    /**
     * Fetch the row FOR UPDATE, creating it on first touch.
     *
     * Must be called inside a transaction — the lock is what serialises two
     * cashiers selling the same last item.
     */
    public function lockFor(int $branchId, int $productId): BranchProductStock
    {
        BranchProductStock::firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
        );

        return $this->builder()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    public function setQuantity(BranchProductStock $stock, string $quantity, ?string $averageCost = null): BranchProductStock
    {
        $stock->quantity_on_hand = $quantity;

        if ($averageCost !== null) {
            $stock->average_cost = $averageCost;
        }

        $stock->save();

        return $stock;
    }

    public function adjustQuantity(BranchProductStock $stock, string $delta): BranchProductStock
    {
        $stock->quantity_on_hand = bcadd((string) $stock->quantity_on_hand, $delta, 3);
        $stock->save();

        return $stock;
    }

    public function lowStock(int $branchId): Collection
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->low()
            ->with('product.baseUnit')
            ->get();
    }

    /**
     * Everything the reorder decision needs, in one row per stocked product.
     *
     * **Driven from `branch_product_stocks`, with sales LEFT JOINed.** The
     * obvious query — start at `sale_items`, join stock — silently omits every
     * product that sold nothing in the period, and those are exactly the rows
     * worth reading: a product that stopped moving is either dead stock or a
     * stockout nobody noticed. It would not error; the rows would just be
     * absent.
     *
     * Sales are net of returns and count completed sales only — a voided sale
     * is not demand. Open purchase orders are counted as outstanding
     * (ordered − received) so the caller can avoid re-buying goods in transit.
     */
    public function reorderRows(int $branchId, string $from, string $to): Collection
    {
        $window = [$from.' 00:00:00', $to.' 23:59:59'];

        $sold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', $window)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity_base) as sold_base');

        // Returned quantity is stored against the original sale item, so the
        // base figure comes back through the item's own unit ratio.
        $returned = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->where('sale_returns.branch_id', $branchId)
            ->whereBetween('sale_returns.created_at', $window)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM(sale_return_items.quantity * (sale_items.quantity_base / sale_items.quantity)) as returned_base');

        return $this->builder()
            ->where('branch_product_stocks.branch_id', $branchId)
            ->leftJoinSub($sold, 'sold', 'sold.product_id', '=', 'branch_product_stocks.product_id')
            ->leftJoinSub($returned, 'ret', 'ret.product_id', '=', 'branch_product_stocks.product_id')
            ->with(['product.baseUnit', 'product.units.unit', 'product.supplierProducts'])
            ->select([
                'branch_product_stocks.*',
                DB::raw('COALESCE(sold.sold_base, 0) as sold_base'),
                DB::raw('COALESCE(ret.returned_base, 0) as returned_base'),
            ])
            ->get();
    }
}
