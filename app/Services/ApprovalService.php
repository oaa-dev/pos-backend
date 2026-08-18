<?php

namespace App\Services;

use App\Enums\AccountStatusEnum;
use App\Exceptions\InvalidApprovalPinException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Counter-side approval: the owner types a PIN on the tindera's terminal
 * rather than logging her out mid-sale.
 *
 * The PIN is checked against catalogue permissions directly via
 * hasPermissionTo(), never through Gate. Gate::before grants any ability the
 * *acting* user holds, so routing this through Gate would let a tindera
 * approve her own void.
 */
class ApprovalService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * The user whose PIN this is, provided they may perform the action.
     *
     * @throws InvalidApprovalPinException
     */
    public function resolveApprover(string $pin, string $ability, ?string $throttleKey = null): User
    {
        $key = 'approval-pin:'.($throttleKey ?? 'global');

        // A 4-to-6 digit PIN is trivially brute-forced without this.
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new InvalidApprovalPinException(
                sprintf('Too many failed approval attempts. Try again in %d seconds.', RateLimiter::availableIn($key)),
                429,
            );
        }

        // Only users who could perform the action themselves are candidates,
        // so a matching PIN alone never authorises anything.
        $candidates = User::query()
            ->whereNotNull('approval_pin')
            ->where('status', AccountStatusEnum::ACTIVE)
            ->with('role.permissions')
            ->get();

        foreach ($candidates as $candidate) {
            if (! Hash::check($pin, $candidate->approval_pin)) {
                continue;
            }

            if (! $candidate->hasPermissionTo($ability)) {
                continue;
            }

            RateLimiter::clear($key);

            return $candidate;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        throw new InvalidApprovalPinException;
    }

    /**
     * Hashing is the model's `hashed` cast, not this method — doing it here
     * as well would hash the hash.
     */
    public function setPin(User $user, string $pin): User
    {
        $user->approval_pin = $pin;
        $user->save();

        return $user;
    }
}
