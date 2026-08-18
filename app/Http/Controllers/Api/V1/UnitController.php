<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\UnitData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use ApiResponse;

    public function __construct(protected UnitService $unitService) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            UnitResource::collection($this->unitService->paginate($request->input('per_page', 50))),
        );
    }

    /**
     * The unit list a select needs, unpaginated and unguarded.
     *
     * Separate from index() so `units.view` keeps guarding the Units screen
     * while the product form and the selling-units dialog can still name a
     * unit — see routes/api.php.
     */
    public function dropdown()
    {
        return $this->successResponse(
            UnitResource::collection($this->unitService->dropdown()),
        );
    }

    public function show(Unit $unit)
    {
        return $this->successResponse(new UnitResource($unit));
    }

    public function store(StoreUnitRequest $request)
    {
        $unit = $this->unitService->store(UnitData::from($request->validated()));

        return $this->successResponse(new UnitResource($unit), 'Unit created successfully', 201);
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $unit = $this->unitService->updateUnit($unit, UnitData::from($request->validated()));

        return $this->successResponse(new UnitResource($unit), 'Unit updated successfully');
    }

    /**
     * Retires rather than erases — see UnitService::retire().
     */
    public function destroy(Unit $unit)
    {
        $this->unitService->retire($unit);

        return $this->successResponse(null, 'Unit retired successfully');
    }
}
