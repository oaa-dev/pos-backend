<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Services\RegionService;
use App\Traits\ApiResponse;

class RegionController extends Controller
{
    use ApiResponse;

    public function __construct(protected RegionService $regionService) {}

    public function index()
    {
        return $this->paginatedResponse(
            RegionResource::collection($this->regionService->paginate()),
        );
    }

    public function show(int $id)
    {
        return $this->successResponse(
            new RegionResource($this->regionService->findById($id)),
        );
    }
}
