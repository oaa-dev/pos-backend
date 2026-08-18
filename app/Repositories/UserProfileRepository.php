<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\UserProfile;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

class UserProfileRepository extends BaseRepository implements UserProfileRepositoryInterface
{
    protected function model(): string
    {
        return UserProfile::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['firstname', 'lastname'])),
            'firstname',
            'lastname',
            'gender',
            'user_id',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'lastname', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['user', 'address'];
    }

    public function updateOrCreate(array $attributes, array $values): UserProfile
    {
        return UserProfile::updateOrCreate($attributes, $values);
    }

    public function findByUserId(int $userId): ?UserProfile
    {
        return UserProfile::where('user_id', $userId)->first();
    }
}
