<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\DiscountTypeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscountTypeRequest;
use App\Http\Requests\UpdateDiscountTypeRequest;
use App\Http\Resources\DiscountTypeResource;
use App\Models\DiscountType;
use App\Services\DiscountTypeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DiscountTypeController extends Controller
{
    use ApiResponse;

    public function __construct(protected DiscountTypeService $discountTypes) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            DiscountTypeResource::collection($this->discountTypes->paginate($request->input('per_page', 50))),
        );
    }

    public function show(DiscountType $discountType)
    {
        return $this->successResponse(new DiscountTypeResource($discountType));
    }

    public function store(StoreDiscountTypeRequest $request)
    {
        $type = $this->discountTypes->store(DiscountTypeData::from($request->validated()));

        return $this->successResponse(new DiscountTypeResource($type), 'Discount type created successfully', 201);
    }

    public function update(UpdateDiscountTypeRequest $request, DiscountType $discountType)
    {
        $type = $this->discountTypes->updateType($discountType, DiscountTypeData::from($request->validated()));

        return $this->successResponse(new DiscountTypeResource($type), 'Discount type updated successfully');
    }

    public function destroy(DiscountType $discountType)
    {
        $this->discountTypes->retire($discountType);

        return $this->successResponse(null, 'Discount type retired successfully');
    }
}
