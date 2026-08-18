<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashMovementTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Requests\StoreCashMovementRequest;
use App\Http\Resources\CashDrawerSessionResource;
use App\Models\Branch;
use App\Models\CashDrawerSession;
use App\Services\CashDrawerService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CashDrawerController extends Controller
{
    use ApiResponse;

    public function __construct(protected CashDrawerService $drawer) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            CashDrawerSessionResource::collection($this->drawer->paginate($request->input('per_page', 25))),
        );
    }

    /** What the terminal asks on load — no open shift means it cannot sell. */
    public function current(Branch $branch)
    {
        $this->authorize('view', $branch);

        $session = $this->drawer->findOpenForBranch($branch->id);

        return $this->successResponse(
            $session ? new CashDrawerSessionResource($session) : null,
        );
    }

    public function open(OpenShiftRequest $request)
    {
        $branch = Branch::findOrFail($request->validated('branch_id'));
        $this->authorize('view', $branch);

        $session = $this->drawer->open($branch, $request->validated('opening_float') ?? 0);

        return $this->successResponse(
            new CashDrawerSessionResource($session),
            'Shift opened successfully',
            201,
        );
    }

    public function close(CloseShiftRequest $request, CashDrawerSession $session)
    {
        $this->authorize('view', $session->branch);

        $session = $this->drawer->close(
            $session,
            $request->validated('closing_counted'),
            $request->validated('closing_notes'),
        );

        return $this->successResponse(
            new CashDrawerSessionResource($session),
            'Shift closed successfully',
        );
    }

    public function recordMovement(StoreCashMovementRequest $request, CashDrawerSession $session)
    {
        $this->authorize('view', $session->branch);

        $this->drawer->recordMovement(
            $session,
            CashMovementTypeEnum::from($request->validated('type')),
            $request->validated('amount'),
            $request->validated('reason'),
        );

        return $this->successResponse(
            new CashDrawerSessionResource($session->fresh()),
            'Cash movement recorded successfully',
            201,
        );
    }
}
