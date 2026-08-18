<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\RoleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(protected RoleService $roleService) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            RoleResource::collection($this->roleService->paginate($request->input('per_page', 15))),
        );
    }

    /**
     * The role list a select needs, unpaginated and unguarded.
     *
     * Separate from index() so `roles.view` keeps guarding the Roles screen
     * while the staff forms — which name a role without administering one —
     * can still fill their picker. See routes/api.php.
     */
    public function dropdown()
    {
        return $this->successResponse(
            RoleResource::collection($this->roleService->dropdown()),
        );
    }

    public function show(Role $role)
    {
        return $this->successResponse(
            new RoleResource($role->load('permissions')->loadCount('permissions')),
        );
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $data = RoleData::from($request->validated());

        $role = $this->roleService->updateRole($role, $data);

        return $this->successResponse(new RoleResource($role), 'Role updated successfully');
    }
}
