<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\UserPhotoData;
use App\Data\UserProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserProfileRequest;
use App\Http\Requests\UpdateUserPhotoRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserProfileService;
use App\Traits\ApiResponse;

class UserProfileController extends Controller
{
    use ApiResponse;

    public function __construct(protected UserProfileService $userProfileService) {}

    public function show(User $user)
    {
        $profile = $this->userProfileService->findForUser($user);

        if (! $profile) {
            return $this->errorResponse('Profile not found', 404);
        }

        return $this->successResponse(new UserProfileResource($profile->load(['user', 'address'])));
    }

    public function store(StoreUserProfileRequest $request, User $user)
    {
        $data = UserProfileData::from($request->validated());

        $profile = $this->userProfileService->upsertFor($user, $data);

        return $this->successResponse(
            new UserProfileResource($profile->load(['user', 'address'])),
            'Profile created successfully',
            201,
        );
    }

    public function update(UpdateUserProfileRequest $request, User $user)
    {
        $data = UserProfileData::from($request->validated());

        $profile = $this->userProfileService->upsertFor($user, $data);

        return $this->successResponse(
            new UserProfileResource($profile->load(['user', 'address'])),
            'Profile updated successfully',
            200,
        );
    }

    public function updatePhoto(UpdateUserPhotoRequest $request, User $user)
    {
        $profile = $this->userProfileService->updatePhoto(
            $user,
            UserPhotoData::from(['photo' => $request->file('photo')]),
        );

        $user = $profile->user()->first();

        return $this->successResponse(
            new UserResource($user->load(['profile', 'profile.address', 'role'])),
            'Profile photo updated successfully',
        );
    }

    public function destroyPhoto(User $user)
    {
        $profile = $this->userProfileService->deletePhoto($user);

        $user = $profile->user()->first();

        return $this->successResponse(
            new UserResource($user->load(['profile', 'profile.address', 'role'])),
            'Profile photo removed successfully',
        );
    }
}
