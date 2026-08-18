<?php

namespace App\Repositories\Contracts;

use App\Models\UserProfile;

interface UserProfileRepositoryInterface extends BaseRepositoryInterface
{
    public function updateOrCreate(array $attributes, array $values): UserProfile;

    public function findByUserId(int $userId): ?UserProfile;
}
