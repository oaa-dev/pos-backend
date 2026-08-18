<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\ResetUserPasswordData;
use App\Data\StoreRegistrationData;
use App\Data\UserData;
use App\Enums\AccountStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\StoreProvisioningService;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(protected UserService $userService) {}

    public function store(StoreUserRequest $request)
    {
        $data = UserData::from($request->validated());

        $user = $this->userService->store($data);

        return $this->successResponse(
            new UserResource($user->load(['profile', 'role'])),
            'User created successfully',
            201,
        );
    }

    /**
     * Public self-registration — which, under tenancy, opens a store.
     *
     * Distinct from store(): it honours a submitted password, and it creates
     * the tenant the new account lives in. There is no such thing as a user
     * without a store, so this cannot be a bare user insert.
     *
     * Returns `{user, token}` — the same envelope as /auth/login. Signing up
     * and then being asked to log in is a poor first five minutes, and there
     * is no reason for a client to hold two shapes for the same fact.
     */
    public function register(
        RegisterRequest $request,
        StoreProvisioningService $provisioning,
        AuthService $auth,
    ) {
        $store = $provisioning->provision(
            StoreRegistrationData::from($request->validated())
        );

        return $this->successResponse(
            new LoginResource($auth->issueFor($store->owner)),
            'Registration successful',
            201,
        );
    }

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            UserResource::collection($this->userService->paginate($request->input('per_page', 15))),
        );
    }

    public function show(User $user)
    {
        return $this->successResponse(
            new UserResource($user->load(['profile', 'profile.address', 'role'])),
        );
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = UserData::from($request->validated());

        $user = $this->userService->updateUser($user, $data);

        return $this->successResponse(
            new UserResource($user->load(['profile', 'profile.address', 'role'])),
            'User updated successfully',
        );
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user)
    {
        $this->userService->resetPassword(
            $user,
            ResetUserPasswordData::from($request->validated()),
        );

        return $this->successResponse(null, 'Password reset successfully');
    }

    public function updateStatus(User $user, UpdateUserStatusRequest $request)
    {
        $status = AccountStatusEnum::from($request->validated('status'));

        $user = $this->userService->updateStatus($user, $status);

        return $this->successResponse(new UserResource($user), 'User status updated successfully');
    }
}
