<?php

namespace App\Services;

use App\Data\UserPhotoData;
use App\Data\UserProfileData;
use App\Models\User;
use App\Models\UserProfile;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\LaravelData\Optional;
use Throwable;

class UserProfileService extends BaseService
{
    public function __construct(
        protected readonly UserProfileRepositoryInterface $userProfileRepository,
        protected readonly AddressService $addressService,
    ) {
        parent::__construct($userProfileRepository);
    }

    public function findForUser(User $user): ?UserProfile
    {
        return $this->userProfileRepository->findByUserId($user->id);
    }

    public function upsertFor(User $user, UserProfileData $data): UserProfile
    {
        return DB::transaction(function () use ($user, $data) {
            $existing = $this->userProfileRepository->findByUserId($user->id);

            $values = [
                'firstname' => $this->resolve($data->firstname, $existing?->firstname),
                'lastname' => $this->resolve($data->lastname, $existing?->lastname),
                'middlename' => $this->resolve($data->middlename, $existing?->middlename),
                'suffix' => $this->resolve($data->suffix, $existing?->suffix),
                'salutation' => $this->resolve($data->salutation, $existing?->salutation),
                'gender' => $this->resolve($data->gender, $existing?->gender),
                'birthdate' => $this->resolve($data->birthdate, $existing?->birthdate?->toDateString()),
            ];

            $profile = $this->userProfileRepository->updateOrCreate(['user_id' => $user->id], $values);

            if (! $data->address instanceof Optional) {
                $this->addressService->upsertFor($profile, $data->address);
            }

            return $profile->fresh(['address']);
        });
    }

    private function resolve(mixed $value, mixed $fallback): mixed
    {
        return $value instanceof Optional ? $fallback : $value;
    }

    /**
     * Store the new file before changing the row, then compensate if the row
     * update fails. The previous file is removed only after the new path is
     * durable, so a failed upload never leaves the user without a photo.
     */
    public function updatePhoto(User $user, UserPhotoData $data): UserProfile
    {
        // Created on demand rather than assumed. A `user_profiles` row is
        // written by `upsertFor()`, not by user creation, so an account that
        // has never opened the profile screen has none — and dereferencing the
        // null 500s on what is otherwise a valid upload.
        // `firstname`/`lastname` are NOT NULL, so the placeholder row is seeded
        // from the account name the user was created with.
        $profile = $this->findForUser($user) ?? $this->userProfileRepository->create([
            'user_id' => $user->id,
            'firstname' => Str::before($user->name, ' ') ?: $user->name,
            'lastname' => Str::contains($user->name, ' ') ? Str::after($user->name, ' ') : '',
        ]);

        $path = $data->photo->store("profile-photos/{$profile->id}", 'public');

        if ($path === false) {
            throw new RuntimeException('Could not store the profile photo.');
        }

        $previous = $profile->profile_photo_path;

        try {
            $this->userProfileRepository->update($profile, ['profile_photo_path' => $path]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $profile->refresh();
    }

    public function deletePhoto(User $user): UserProfile
    {
        $profile = $this->findForUser($user);

        $previous = $profile->profile_photo_path;

        $this->userProfileRepository->update($profile, ['profile_photo_path' => null]);

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        return $profile->refresh();
    }
}
