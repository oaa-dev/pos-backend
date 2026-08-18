<?php

namespace App\Repositories\Contracts;

use App\Models\StockMovement;

interface StockMovementRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function record(array $values): StockMovement;

    public function sumBaseQuantity(int $branchId, int $productId): string;
}
