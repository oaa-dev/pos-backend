<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Services\CityService;
use App\Traits\ApiResponse;

class CityController extends Controller
{
    use ApiResponse;

    public function __construct(protected CityService $cityService) {}

    public function index()
    {
        return $this->paginatedResponse(
            CityResource::collection($this->cityService->paginate()),
        );
    }

    public function show(int $id)
    {
        return $this->successResponse(
            new CityResource($this->cityService->findById($id)),
        );
    }
}
