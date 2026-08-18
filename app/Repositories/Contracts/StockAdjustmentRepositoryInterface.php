<?php

namespace App\Repositories\Contracts;

use App\Models\StockAdjustment;

interface StockAdjustmentRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function createAdjustment(array $values): StockAdjustment;

    /**
     * @param  array<string, mixed>  $values
     */
    public function addItem(StockAdjustment $adjustment, array $values): void;
}
