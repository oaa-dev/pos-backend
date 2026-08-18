<?php

namespace App\Repositories\Contracts;

use App\Models\BranchProductStock;
use Illuminate\Database\Eloquent\Collection;

interface BranchProductStockRepositoryInterface extends BaseRepositoryInterface
{
    public function lockFor(int $branchId, int $productId): BranchProductStock;

    public function setQuantity(BranchProductStock $stock, string $quantity, ?string $averageCost = null): BranchProductStock;

    public function adjustQuantity(BranchProductStock $stock, string $delta): BranchProductStock;

    public function lowStock(int $branchId): Collection;

    /** One row per stocked product with sales, returns, and on-order figures. */
    public function reorderRows(int $branchId, string $from, string $to): Collection;
}
