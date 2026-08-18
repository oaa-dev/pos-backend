<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\LoginData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;

    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $data = LoginData::from($request->validated());

        $result = $this->authService->login($data);

        return $this->successResponse(new LoginResource($result), 'Login successful');
    }

    public function logout()
    {
        $this->authService->logout();

        return $this->successResponse(null, 'Logout successful');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $data = $request->validated();

        $this->authService->changePassword($data['current_password'], $data['new_password']);

        return $this->successResponse(null, 'Password changed successfully');
    }

    public function me()
    {
        return $this->successResponse(new UserResource($this->authService->me()), 'User retrieved successfully');
    }
}
