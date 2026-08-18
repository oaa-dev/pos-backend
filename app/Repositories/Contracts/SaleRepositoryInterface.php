<?php

namespace App\Repositories\Contracts;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleDiscount;
use App\Models\SaleItem;
use App\Models\SalePayment;

interface SaleRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUuid(string $uuid): ?Sale;

    public function lockByUuid(string $uuid): ?Sale;

    public function nextSaleNumber(Branch $branch): string;

    /** @param array<string, mixed> $values */
    public function addItem(Sale $sale, array $values): SaleItem;

    /** @param array<string, mixed> $values */
    public function addPayment(Sale $sale, array $values): SalePayment;

    /** @param array<string, mixed> $values */
    public function addDiscount(Sale $sale, array $values): SaleDiscount;
}
