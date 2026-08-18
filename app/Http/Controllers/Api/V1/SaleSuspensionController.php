<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleSuspensionRequest;
use App\Http\Resources\SaleSuspensionResource;
use App\Models\Branch;
use App\Models\SaleSuspension;
use App\Services\SaleSuspensionService;
use App\Traits\ApiResponse;

class SaleSuspensionController extends Controller
{
    use ApiResponse;

    public function __construct(protected SaleSuspensionService $suspensions) {}

    public function index(Branch $branch)
    {
        $this->authorize('view', $branch);

        return $this->successResponse(
            SaleSuspensionResource::collection($this->suspensions->forBranch($branch)),
        );
    }

    public function store(StoreSaleSuspensionRequest $request)
    {
        $branch = Branch::findOrFail($request->validated('branch_id'));
        $this->authorize('view', $branch);

        $suspension = $this->suspensions->suspend(
            $branch,
            $request->validated('payload'),
            $request->validated('label'),
        );

        return $this->successResponse(
            new SaleSuspensionResource($suspension),
            'Cart suspended successfully',
            201,
        );
    }

    public function resume(SaleSuspension $suspension)
    {
        $this->authorize('view', $suspension->branch);

        // The terminal restores this snapshot only inside the fifteen-minute
        // window enforced by the service.
        return $this->successResponse(
            new SaleSuspensionResource($this->suspensions->resume($suspension)),
            'Cart resumed successfully',
        );
    }

    public function destroy(SaleSuspension $suspension)
    {
        $this->authorize('view', $suspension->branch);

        return $this->successResponse(
            new SaleSuspensionResource($this->suspensions->discard($suspension)),
            'Cart discarded successfully',
        );
    }
}
