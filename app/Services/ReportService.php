<?php

namespace App\Services;

use App\Data\ReportFilterData;
use App\Models\Branch;
use App\Repositories\Contracts\ReportRepositoryInterface;

class ReportService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    public function posDaily(Branch $branch, string $date): array
    {
        return $this->reports->posDaily($branch->id, $date);
    }

    public function dailySales(Branch $branch, ReportFilterData $filter): array
    {
        return $this->reports->dailySales($branch->id, $filter);
    }

    public function dailyProfit(Branch $branch, ReportFilterData $filter): array
    {
        return $this->reports->dailyProfit($branch->id, $filter);
    }

    public function productSales(Branch $branch, ReportFilterData $filter): array
    {
        return $this->reports->productSales($branch->id, $filter);
    }

    public function zRead(Branch $branch, string $date, int $userId): object
    {
        $filter = new ReportFilterData($date, $date);

        return $this->reports->saveZReading($branch->id, $date, $userId, $this->dailySales($branch, $filter));
    }
}
