<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\StockTransferData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Services\StockTransferService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StockTransferService $transfers,
        private readonly StockTransferRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            StockTransferResource::collection(
                $this->repository->paginate($request->input('per_page', 15)),
            ),
        );
    }

    /**
     * Creates the transfer *and* moves the stock — there is no second call.
     *
     * The store check lives in `StoreStockTransferRequest`, which scopes both
     * branch ids to the actor's own store. That is the whole guard: with no
     * route-model-bound write left, nothing here resolves a transfer by id.
     */
    public function store(StoreStockTransferRequest $request)
    {
        $transfer = $this->transfers->create(StockTransferData::from($request->validated()));

        return $this->successResponse(
            new StockTransferResource($this->withDetail($transfer)),
            'Transfer completed',
            201,
        );
    }

    /** Loaded before the resource serialises it — `whenLoaded` drops absent keys. */
    private function withDetail(StockTransfer $transfer): StockTransfer
    {
        return $transfer->load(['fromBranch', 'toBranch', 'items.product', 'items.productUnit.unit'])
            ->loadCount('items');
    }
}
