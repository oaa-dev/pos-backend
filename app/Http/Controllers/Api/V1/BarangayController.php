<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BarangayResource;
use App\Services\BarangayService;
use App\Traits\ApiResponse;

class BarangayController extends Controller
{
    use ApiResponse;

    public function __construct(protected BarangayService $barangayService) {}

    public function index()
    {
        return $this->paginatedResponse(
            BarangayResource::collection($this->barangayService->paginate()),
        );
    }

    public function show(int $id)
    {
        return $this->successResponse(
            new BarangayResource($this->barangayService->findById($id)),
        );
    }
}
