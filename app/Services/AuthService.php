<?php

namespace App\Services;

use App\Data\LoginData;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * The relations every authenticated response carries.
     *
     * Login, registration and me() draw on one list, so a client gets the same
     * user shape however it arrived.
     */
    /**
     * `store` is in here because the shell renders the store name off the
     * authenticated user, and `UserResource` uses the callback form of
     * `whenLoaded` — an unloaded relation drops the key entirely rather than
     * serialising as null.
     */
    private const RELATIONS = ['profile', 'profile.address', 'role.permissions', 'store'];

    public function login(LoginData $data): array
    {
        $user = $this->userRepository->findByEmail($data->email);

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        return $this->issueFor($user);
    }

    /**
     * Mint a token and build the authenticated payload.
     *
     * Registration ends here too, so one code path issues tokens and both
     * entry points return an identical envelope.
     *
     * `loadMissing`, not `load`: provisioning has already resolved the new
     * owner's role and store, and re-querying them here would discard that
     * work on the one request where it matters most.
     */
    public function issueFor(User $user): array
    {
        return [
            'user' => $user->loadMissing(self::RELATIONS),
            'token' => $this->userRepository->createToken($user),
        ];
    }

    public function logout(): void
    {
        auth()->user()->currentAccessToken()->delete();
    }

    public function changePassword(string $currentPassword, string $newPassword): void
    {
        $user = auth()->user();

        if (! Hash::check($currentPassword, $user->password)) {
            throw new InvalidCredentialsException;
        }

        $this->userRepository->update($user, [
            'password' => bcrypt($newPassword),
        ]);
    }

    public function me(): User
    {
        $user = auth()->user();

        return $user->load(self::RELATIONS);
    }
}
