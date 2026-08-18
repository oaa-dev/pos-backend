<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardOverviewRequest;
use App\Services\DashboardService;
use App\Traits\ApiResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(protected DashboardService $dashboard) {}

    /**
     * Everything the landing screen needs, in one call.
     *
     * Branch scoping happens inside the service: `branches.view-all` sees every
     * branch, everyone else only what they are assigned to. Without that a
     * supervisor would read another branch's takings here, since this
     * aggregates across branches rather than going through a scoped repository.
     */
    public function overview(DashboardOverviewRequest $request)
    {
        return $this->successResponse(
            $this->dashboard->overview((int) ($request->validated('days') ?? 30)),
        );
    }
}
