<?php

namespace App\Repositories;

use App\Models\ProductBatch;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\ProductBatchRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductBatchRepository extends BaseRepository implements BranchScopedInterface, ProductBatchRepositoryInterface
{
    protected function model(): string
    {
        return ProductBatch::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            'branch_id',
            'product_id',
            'status',
            AllowedFilter::callback(
                'expiring_before',
                fn ($query, $value) => $query->expiringBefore($value),
            ),
            AllowedFilter::callback('open', fn ($query) => $query->open()),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'expiry_date', 'received_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['product', 'branch'];
    }

    protected function defaultSort(): string
    {
        return 'expiry_date';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['product.baseUnit']);
    }

    /**
     * An existing open batch with the same expiry and the same cost.
     *
     * Without this, full batch tracking would create a row per delivery
     * forever — a sachet of Tide that never expires would accumulate hundreds.
     * Merging on (expiry, cost) keeps non-perishables near one row per cost
     * change while the model stays uniform across the catalogue.
     */
    public function findMergeable(int $branchId, int $productId, ?string $expiryDate, string $unitCost): ?ProductBatch
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('status', 'open')
            ->when(
                $expiryDate === null,
                fn ($query) => $query->whereNull('expiry_date'),
                fn ($query) => $query->whereDate('expiry_date', $expiryDate),
            )
            ->whereRaw('CAST(unit_cost AS DECIMAL(12,4)) = CAST(? AS DECIMAL(12,4))', [$unitCost])
            ->first();
    }

    public function createBatch(array $values): ProductBatch
    {
        return ProductBatch::create($values);
    }

    /**
     * Open batches in FEFO order, locked for the duration of the transaction.
     */
    public function fefoOpen(int $branchId, int $productId): Collection
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->open()
            ->fefo()
            ->lockForUpdate()
            ->get();
    }

    public function addQuantity(ProductBatch $batch, string $quantity): ProductBatch
    {
        $batch->quantity_remaining = bcadd((string) $batch->quantity_remaining, $quantity, 3);
        $batch->status = 'open';
        $batch->save();

        return $batch;
    }

    public function subtractQuantity(ProductBatch $batch, string $quantity): ProductBatch
    {
        $batch->quantity_remaining = bcsub((string) $batch->quantity_remaining, $quantity, 3);

        // A depleted batch is closed rather than deleted — its movements still
        // reference it, and the cost it carried has to stay explainable.
        if (bccomp((string) $batch->quantity_remaining, '0', 3) <= 0) {
            $batch->status = 'depleted';
        }

        $batch->save();

        return $batch;
    }

    public function expiring(int $branchId, string $before): Collection
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->open()
            ->expiringBefore($before)
            ->with('product.baseUnit')
            ->fefo()
            ->get();
    }
}
