<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\BranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignBranchUserRequest;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use ApiResponse;

    public function __construct(protected BranchService $branchService) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            BranchResource::collection($this->branchService->paginate($request->input('per_page', 15))),
        );
    }

    /**
     * The branch list a select needs, unpaginated.
     *
     * Carries no `can:`, and safely: every row is bound to the caller by
     * `BranchRepository::dropdown()` — assignment first, then their own store
     * — so this returns nothing an actor could not already reach. `store_id`
     * is honoured only for the platform operator, who has no store of their
     * own to be pinned to.
     */
    public function dropdown(Request $request)
    {
        return $this->successResponse(
            BranchResource::collection(
                $this->branchService->dropdown($request->integer('store_id') ?: null),
            ),
        );
    }

    public function show(Branch $branch)
    {
        // Route-model binding resolves the branch directly, bypassing the
        // repository's branch scope — the policy is what stops a tindera
        // reading a branch she is not assigned to.
        $this->authorize('view', $branch);

        return $this->successResponse(
            new BranchResource($branch->load(['address', 'users'])->loadCount('users')),
        );
    }

    public function store(StoreBranchRequest $request)
    {
        $data = BranchData::from($request->validated());

        $branch = $this->branchService->store($data);

        return $this->successResponse(new BranchResource($branch), 'Branch created successfully', 201);
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        $data = BranchData::from($request->validated());

        $branch = $this->branchService->updateBranch($branch, $data);

        return $this->successResponse(new BranchResource($branch), 'Branch updated successfully');
    }

    public function assignUser(AssignBranchUserRequest $request, Branch $branch)
    {
        $user = User::findOrFail($request->validated('user_id'));

        $branch = $this->branchService->assignUser(
            $branch,
            $user,
            (bool) $request->validated('is_primary', false),
        );

        return $this->successResponse(new BranchResource($branch), 'User assigned to branch successfully');
    }

    public function unassignUser(Branch $branch, User $user)
    {
        $branch = $this->branchService->unassignUser($branch, $user);

        return $this->successResponse(new BranchResource($branch), 'User removed from branch successfully');
    }
}
