<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\ReorderFilterData;
use App\Data\StockAdjustmentData;
use App\Data\StockReceiptData;
use App\Enums\StockAdjustmentReasonEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderReportRequest;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Requests\StoreStockReceiptRequest;
use App\Http\Requests\UpdateReorderPointsRequest;
use App\Http\Resources\BranchProductStockResource;
use App\Http\Resources\ProductBatchResource;
use App\Http\Resources\ReorderRowResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Branch;
use App\Models\Product;
use App\Repositories\Contracts\BranchProductStockRepositoryInterface;
use App\Repositories\Contracts\ProductBatchRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Services\ReorderService;
use App\Services\StockAdjustmentService;
use App\Services\StockReceiptService;
use App\Services\StockService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StockService $stockService,
        protected StockReceiptService $stockReceiptService,
        protected StockAdjustmentService $adjustmentService,
        protected BranchProductStockRepositoryInterface $stocks,
        protected StockMovementRepositoryInterface $movements,
        protected ProductBatchRepositoryInterface $batches,
        protected ReorderService $reorder,
    ) {}

    /**
     * "Should I buy this, and how much?"
     *
     * Sales velocity joined to stock on hand, with a suggested order quantity
     * in the unit the store actually buys in.
     */
    public function reorder(ReorderReportRequest $request, Branch $branch)
    {
        // Route-model binding never reaches the repository's branch scope, so
        // the policy is what stops one branch reading another's figures.
        $this->authorize('view', $branch);

        $report = $this->reorder->report($branch, ReorderFilterData::from($request->validated()));

        return $this->successResponse([
            ...$report,
            'rows' => ReorderRowResource::collection($report['rows']),
        ]);
    }

    /**
     * Accept reorder points — one product or the whole report.
     *
     * The first write in this project to set `reorder_point` above zero.
     * `BranchProductStock::low()` requires it, so until this runs the
     * low-stock alert has never matched a single row.
     */
    public function updateReorderPoints(UpdateReorderPointsRequest $request, Branch $branch)
    {
        // Mandatory. This resolves a branch through route-model binding, which
        // never reaches the repository's branch scope — without the policy a
        // supervisor in one branch could set reorder points in another.
        $this->authorize('view', $branch);

        $written = $this->reorder->saveReorderPoints($branch, $request->validated('points'));

        return $this->successResponse(
            ['updated' => $written],
            $written === 1 ? 'Reorder point saved' : "{$written} reorder points saved",
        );
    }

    public function stocks(Request $request)
    {
        return $this->paginatedResponse(
            BranchProductStockResource::collection($this->stocks->paginate($request->input('per_page', 25))),
        );
    }

    public function movements(Request $request)
    {
        return $this->paginatedResponse(
            StockMovementResource::collection($this->movements->paginate($request->input('per_page', 25))),
        );
    }

    public function batches(Request $request)
    {
        return $this->paginatedResponse(
            ProductBatchResource::collection($this->batches->paginate($request->input('per_page', 25))),
        );
    }

    public function lowStock(Branch $branch)
    {
        $this->authorize('view', $branch);

        return $this->successResponse(
            BranchProductStockResource::collection($this->stocks->lowStock($branch->id)),
        );
    }

    /**
     * Batches expiring within N days — the alert that makes FEFO actionable
     * rather than merely correct.
     */
    public function expiring(Request $request, Branch $branch)
    {
        $this->authorize('view', $branch);

        $days = (int) $request->input('days', 30);

        return $this->successResponse(
            ProductBatchResource::collection(
                $this->batches->expiring($branch->id, now()->addDays($days)->toDateString()),
            ),
        );
    }

    public function receive(StoreStockReceiptRequest $request)
    {
        $data = StockReceiptData::from($request->validated());
        $branch = Branch::findOrFail($data->branch_id);

        // The policy half of the branch guard. Route-model binding never
        // reaches the repository on this route, so removing this lets a
        // tindera receive stock into a branch she is not assigned to.
        $this->authorize('view', $branch);

        // One screen for both. Naming a supplier or a payment makes this a
        // delivery — which charges the till and remembers the supplier's cost;
        // naming neither makes it an opening balance counted in. The ledger
        // records which, so the two stay distinguishable.
        $movement = $this->stockReceiptService->record($branch, $data);

        return $this->successResponse(
            new StockMovementResource($movement->load(['product', 'productUnit.unit'])),
            'Stock received successfully',
            201,
        );
    }

    public function adjust(StoreStockAdjustmentRequest $request)
    {
        $data = StockAdjustmentData::from($request->validated());
        $branch = Branch::findOrFail($data->branch_id);

        // Same branch guard as `receive()` — see the note there.
        $this->authorize('view', $branch);

        $adjustment = $this->adjustmentService->record(
            branch: $branch,
            reason: StockAdjustmentReasonEnum::from($data->reason),
            items: $data->items,
            note: $data->note,
        );

        return $this->successResponse($adjustment, 'Stock adjusted successfully', 201);
    }
}
