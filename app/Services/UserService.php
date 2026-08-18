<?php

namespace App\Services;

use App\Data\ResetUserPasswordData;
use App\Data\UserData;
use App\Enums\AccountStatusEnum;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

class UserService extends BaseService
{
    public function __construct(
        protected readonly UserRepositoryInterface $userRepository
    ) {
        parent::__construct($userRepository);
    }

    public function paginate(int $perPage = 5): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage);
    }

    /**
     * Create an account and attach it to a store.
     *
     * The `store_users` row is not optional bookkeeping — it is the *only*
     * record that this account belongs anywhere, since `users` carries no
     * `store_id`. Without it `$user->store` is null, `GET /store` 404s, and
     * the account appears in no store's staff list.
     *
     * Deliberately **not** routed through `StoreService::addOwner()`, which
     * refuses a second owner: this writes `is_owner = false`, so a store can
     * have as many tinderas as it likes.
     */
    public function store(UserData $data): User
    {
        $firstname = $this->resolve($data->firstname, '');
        $lastname = $this->resolve($data->lastname, '');
        $storeId = $this->resolve($data->store_id, null);

        return DB::transaction(function () use ($data, $firstname, $lastname, $storeId) {
            $user = $this->userRepository->createWithProfile([
                'name' => trim($lastname.' '.$firstname),
                'email' => $this->resolve($data->email, ''),
                'phone_number' => $this->resolve($data->phone_number, null),
                'password' => $this->resolve($data->password, $this->defaultPassword()),
                'role_id' => $this->resolve($data->role_id, null),
                'status' => AccountStatusEnum::ACTIVE->value,
            ], [
                'firstname' => $firstname,
                'lastname' => $lastname,
            ]);

            if ($storeId !== null) {
                // syncWithoutDetaching, not attach: `store_users` is unique on
                // (store_id, user_id) and a second write would throw.
                $user->stores()->syncWithoutDetaching([$storeId => ['is_owner' => false]]);
            }

            // Loaded before returning. `UserResource` uses the callback form of
            // `whenLoaded`, so an unloaded relation drops its key entirely
            // rather than serialising as null.
            return $user->load(['profile', 'role.permissions', 'store']);
        });
    }

    /**
     * A partial update. `users.name` is derived from the profile's first and
     * last name, so it is recomputed whenever either of them changes.
     */
    public function updateUser(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->loadMissing('profile');

            $firstname = $this->resolve($data->firstname, $user->profile?->firstname);
            $lastname = $this->resolve($data->lastname, $user->profile?->lastname);

            $userValues = [];

            if (! $data->email instanceof Optional) {
                $userValues['email'] = $data->email;
            }

            if (! $data->phone_number instanceof Optional) {
                $userValues['phone_number'] = $data->phone_number;
            }

            if (! $data->role_id instanceof Optional) {
                $userValues['role_id'] = $data->role_id;
            }

            $nameChanged = ! $data->firstname instanceof Optional || ! $data->lastname instanceof Optional;

            if ($nameChanged) {
                $userValues['name'] = trim($lastname.' '.$firstname);
            }

            $profileValues = collect([
                'firstname' => $data->firstname,
                'lastname' => $data->lastname,
                'birthdate' => $data->birthdate,
            ])->reject(fn ($value) => $value instanceof Optional)->all();

            return $this->userRepository->updateWithProfile($user, $userValues, $profileValues);
        });
    }

    public function resetPassword(User $user, ResetUserPasswordData $data): void
    {
        $this->userRepository->update($user, ['password' => $data->new_password]);
    }

    public function updateStatus(User $user, AccountStatusEnum $status): User
    {
        $this->userRepository->update($user, [
            'status' => $status->value,
        ]);

        return $user;
    }

    public function showUserWithProfile(int $id): User
    {
        return $this->userRepository->showUserWith($id, ['profile', 'profile.address', 'role'])
            ?? throw new ModelNotFoundException('User not found');
    }

    private function resolve(mixed $value, mixed $fallback): mixed
    {
        return $value instanceof Optional ? $fallback : $value;
    }

    /**
     * Only reached by the admin-side create path; /auth/register always
     * submits its own password.
     */
    private function defaultPassword(): string
    {
        return config('users.default_password');
    }
}
