<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\SupplierData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ApiResponse;

    public function __construct(protected SupplierService $suppliers) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            SupplierResource::collection($this->suppliers->paginate($request->input('per_page', 25))),
        );
    }

    public function show(Supplier $supplier)
    {
        return $this->successResponse(new SupplierResource($supplier->load('address')));
    }

    public function store(StoreSupplierRequest $request)
    {
        return $this->successResponse(
            new SupplierResource($this->suppliers->store(SupplierData::from($request->validated()))),
            'Supplier created successfully',
            201,
        );
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        return $this->successResponse(
            new SupplierResource($this->suppliers->updateSupplier($supplier, SupplierData::from($request->validated()))),
            'Supplier updated successfully',
        );
    }
}
