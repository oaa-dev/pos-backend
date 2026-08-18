<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProvinceResource;
use App\Services\ProvinceService;
use App\Traits\ApiResponse;

class ProvinceController extends Controller
{
    use ApiResponse;

    public function __construct(protected ProvinceService $provinceService) {}

    public function index()
    {
        return $this->paginatedResponse(
            ProvinceResource::collection($this->provinceService->paginate()),
        );
    }

    public function show(int $id)
    {
        return $this->successResponse(
            new ProvinceResource($this->provinceService->findById($id)),
        );
    }
}
