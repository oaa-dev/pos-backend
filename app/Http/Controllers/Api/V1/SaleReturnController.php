<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\SaleReturnData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleReturnRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleReturnService;
use App\Traits\ApiResponse;

class SaleReturnController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SaleReturnService $returns) {}

    public function index()
    {
        return $this->successResponse(SaleReturn::with('sale')->latest('returned_at')->get());
    }

    public function store(StoreSaleReturnRequest $request, Sale $sale)
    {
        $this->authorize('view', $sale);

        return $this->successResponse($this->returns->create($sale, SaleReturnData::from($request->validated())), 'Return recorded', 201);
    }
}
