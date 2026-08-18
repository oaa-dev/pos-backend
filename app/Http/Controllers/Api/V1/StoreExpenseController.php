<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\StoreExpenseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoreExpenseRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Http\Resources\StoreExpenseResource;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\StoreExpense;
use App\Services\StoreExpenseService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class StoreExpenseController extends Controller
{
    use ApiResponse;

    public function __construct(protected StoreExpenseService $expenses) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            StoreExpenseResource::collection($this->expenses->paginate($request->input('per_page', 25))),
        );
    }

    public function categories()
    {
        return $this->successResponse(
            ExpenseCategoryResource::collection(
                ExpenseCategory::active()->orderBy('name')->get(),
            ),
        );
    }

    public function store(StoreStoreExpenseRequest $request)
    {
        $branch = Branch::findOrFail($request->validated('branch_id'));
        $this->authorize('view', $branch);

        return $this->successResponse(
            new StoreExpenseResource($this->expenses->record(StoreExpenseData::from($request->validated()))),
            'Expense recorded successfully',
            201,
        );
    }

    public function approve(Request $request, StoreExpense $storeExpense)
    {
        $this->authorize('view', $storeExpense->branch);

        return $this->successResponse(
            new StoreExpenseResource(
                $this->expenses->approve($storeExpense, (int) $request->user()->id),
            ),
            'Expense approved',
        );
    }

    /** Total and per-category spend — what a net-profit report needs. */
    public function summary(Request $request, Branch $branch)
    {
        $this->authorize('view', $branch);

        return $this->successResponse(
            $this->expenses->summary(
                $branch->id,
                $request->input('from', now()->startOfMonth()->toDateString()),
                $request->input('to', now()->toDateString()),
            ),
        );
    }
}
