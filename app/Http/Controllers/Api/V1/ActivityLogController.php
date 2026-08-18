<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Read-only, and index-only.
 *
 * There is no show, no store and no destroy: the log is written by the system
 * and never by a request, and an audit trail that can be edited through the API
 * is not one. Guarded by `activity-logs.view`, which lives in
 * `PermissionSeeder::PLATFORM_MODULES` and so never reaches an owner.
 */
class ActivityLogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ActivityLogRepositoryInterface $logs) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            ActivityLogResource::collection(
                $this->logs->paginate($request->input('per_page', 15)),
            ),
        );
    }
}
