<?php

namespace App\Repositories\Contracts;

use App\Data\ReportFilterData;

interface ReportRepositoryInterface
{
    public function posDaily(int $branchId, string $date): array;

    public function dailySales(int $branchId, ReportFilterData $filter): array;

    public function dailyProfit(int $branchId, ReportFilterData $filter): array;

    public function productSales(int $branchId, ReportFilterData $filter): array;

    public function saveZReading(int $branchId, string $date, int $userId, array $summary): object;
}
