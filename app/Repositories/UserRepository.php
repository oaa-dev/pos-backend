<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'name',
                'email',
                'phone_number',
            ])),
            'name',
            'email',
            'phone_number',
            'status',

            // `users` has no `store_id` — membership lives on `store_users`,
            // so this reaches it through the `stores` relation. Unfiltered,
            // this listing returns every customer's staff; that is the
            // operator's view and the same accepted risk the rest of the app
            // carries.
            AllowedFilter::callback(
                'store_id',
                fn (Builder $query, $value) => $query->whereHas(
                    'stores',
                    fn (Builder $stores) => $stores->where('stores.id', $value),
                ),
            ),

            // The users page ships a "Created At" date-range field, and the
            // datatable toolbar serialises it as two keys — `<id>_from` and
            // `<id>_to` — so both must be allowed by those exact names or
            // Spatie throws InvalidFilterQuery (400).
            AllowedFilter::callback(
                'created_at_from',
                fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'created_at_to',
                fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'email', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['profile', 'role'];
    }

    /**
     * UserResource puts `profile` and `role` behind whenLoaded(), so the index
     * has to eager-load them or the list silently comes back without either —
     * and lazily loading them per row would be an N+1.
     */
    protected function query(): QueryBuilder
    {
        return parent::query()->with(['profile', 'role']);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function createToken(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    public function revokeToken(User $user): void
    {
        $user->tokens()->delete();
    }

    public function createWithProfile(array $userData, array $profileData = []): User
    {
        $user = User::create($userData);

        if (! empty($profileData)) {
            $user->profile()->create($profileData);
        }

        return $user;
    }

    public function updateWithProfile(User $user, array $userData, array $profileData = []): User
    {
        if ($userData !== []) {
            $user->update($userData);
        }

        if ($profileData !== []) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData);
        }

        return $user->refresh();
    }

    /**
     * @param  list<string>  $relationship
     */
    public function showUserWith(int $id, array $relationship = []): ?User
    {
        return $this->builder()->with($relationship)->find($id);
    }
}
