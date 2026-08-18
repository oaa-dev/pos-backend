<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleDiscount;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SaleRepository extends BaseRepository implements BranchScopedInterface, SaleRepositoryInterface
{
    protected function model(): string
    {
        return Sale::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'sale_number',
                'customer.name',
                'customer.nickname',
            ])),
            'branch_id',
            'status',
            'customer_id',
            'user_id',
            'cash_drawer_session_id',
            AllowedFilter::callback(
                'sold_from',
                fn ($query, $value) => $query->whereDate('sold_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'sold_to',
                fn ($query, $value) => $query->whereDate('sold_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'sold_at', 'total'];
    }

    protected function allowedIncludes(): array
    {
        return ['items', 'items.product', 'payments', 'discounts', 'customer', 'user', 'branch'];
    }

    protected function defaultSort(): string
    {
        return '-sold_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['customer', 'user', 'payments']);
    }

    public function findByUuid(string $uuid): ?Sale
    {
        return $this->findBy('uuid', $uuid);
    }

    public function lockByUuid(string $uuid): ?Sale
    {
        return $this->builder()->where('uuid', $uuid)->lockForUpdate()->first();
    }

    /**
     * Per-branch sequence with the branch code as prefix — MAIN-000123.
     *
     * Taken inside the sale's transaction, so two concurrent sales in one
     * branch cannot claim the same number.
     */
    public function nextSaleNumber(Branch $branch): string
    {
        $last = DB::table('sales')
            ->where('branch_id', $branch->id)
            ->lockForUpdate()
            ->max('id');

        return sprintf('%s-%06d', $branch->code, ((int) $last) + 1);
    }

    public function addItem(Sale $sale, array $values): SaleItem
    {
        return $sale->items()->create($values);
    }

    public function addPayment(Sale $sale, array $values): SalePayment
    {
        return $sale->payments()->create($values);
    }

    public function addDiscount(Sale $sale, array $values): SaleDiscount
    {
        return $sale->discounts()->create($values);
    }
}
