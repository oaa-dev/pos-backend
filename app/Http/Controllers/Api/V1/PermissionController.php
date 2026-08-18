<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionGroupResource;
use App\Services\PermissionService;
use App\Traits\ApiResponse;

class PermissionController extends Controller
{
    use ApiResponse;

    public function __construct(protected PermissionService $permissionService) {}

    public function index()
    {
        return $this->successResponse(
            PermissionGroupResource::collection($this->permissionService->groupedByModule()),
            'Permissions retrieved successfully',
        );
    }
}
