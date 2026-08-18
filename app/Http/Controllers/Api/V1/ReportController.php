<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\ReportFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReportRequest;
use App\Http\Requests\PosDailyReportRequest;
use App\Models\Branch;
use App\Services\ReportService;
use App\Traits\ApiResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ReportService $reports) {}

    public function posDaily(PosDailyReportRequest $request, Branch $branch)
    {
        $this->authorize('view', $branch);
        $date = $request->validated('date') ?? now()->toDateString();

        return $this->successResponse($this->reports->posDaily($branch, $date));
    }

    public function daily(DailyReportRequest $request, Branch $branch)
    {
        $this->authorize('view', $branch);

        return $this->successResponse($this->reports->dailySales($branch, $this->filter($request)));
    }

    public function profit(DailyReportRequest $request, Branch $branch)
    {
        $this->authorize('view', $branch);

        return $this->successResponse($this->reports->dailyProfit($branch, $this->filter($request, dimensions: false)));
    }

    public function products(DailyReportRequest $request, Branch $branch)
    {
        $this->authorize('view', $branch);

        return $this->successResponse($this->reports->productSales($branch, $this->filter($request)));
    }

    public function zRead(DailyReportRequest $request, Branch $branch)
    {
        $this->authorize('view', $branch);
        $date = $request->validated('to') ?? now()->toDateString();

        return $this->successResponse($this->reports->zRead($branch, $date, (int) $request->user()->id), 'Z-reading completed');
    }

    private function filter(DailyReportRequest $request, bool $dimensions = true): ReportFilterData
    {
        $from = $request->validated('from') ?? now()->toDateString();

        return ReportFilterData::from([
            'from' => $from,
            'to' => $request->validated('to') ?? $from,
            'product_id' => $dimensions ? $request->integer('product_id') ?: null : null,
            'customer_id' => $dimensions ? $request->integer('customer_id') ?: null : null,
        ]);
    }
}
