<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\ApprovalService;
use App\Services\SaleService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SaleService $saleService,
        protected ApprovalService $approvalService,
    ) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            SaleResource::collection($this->saleService->paginate($request->input('per_page', 25))),
        );
    }

    public function show(Sale $sale)
    {
        $this->authorize('view', $sale);

        return $this->successResponse(
            new SaleResource($sale->load([
                'items.product', 'items.productUnit.unit', 'payments', 'discounts', 'customer', 'user', 'branch',
            ])),
        );
    }

    public function store(StoreSaleRequest $request)
    {
        $existing = $this->saleService->findByUuid($request->validated('uuid'));

        $sale = $this->saleService->create($request->validated());

        // 200 on a repeat, 201 on a genuine create: the terminal can retry a
        // timed-out submit without wondering whether it charged twice.
        return $this->successResponse(
            new SaleResource($sale),
            $existing !== null ? 'Sale already recorded' : 'Sale recorded successfully',
            $existing !== null ? 200 : 201,
        );
    }

    public function void(VoidSaleRequest $request, Sale $sale)
    {
        $this->authorize('view', $sale);

        // The PIN identifies whoever authorised this, which may not be the
        // person holding the terminal.
        $approver = $this->approvalService->resolveApprover(
            $request->validated('approval_pin'),
            'sales.void',
            $request->ip(),
        );

        $sale = $this->saleService->void($sale, $request->validated('reason'), $approver->id);

        return $this->successResponse(new SaleResource($sale), 'Sale voided successfully');
    }
}
