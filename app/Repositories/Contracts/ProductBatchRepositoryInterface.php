<?php

namespace App\Repositories\Contracts;

use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Collection;

interface ProductBatchRepositoryInterface extends BaseRepositoryInterface
{
    public function findMergeable(int $branchId, int $productId, ?string $expiryDate, string $unitCost): ?ProductBatch;

    /**
     * @param  array<string, mixed>  $values
     */
    public function createBatch(array $values): ProductBatch;

    public function fefoOpen(int $branchId, int $productId): Collection;

    public function addQuantity(ProductBatch $batch, string $quantity): ProductBatch;

    public function subtractQuantity(ProductBatch $batch, string $quantity): ProductBatch;

    public function expiring(int $branchId, string $before): Collection;
}
