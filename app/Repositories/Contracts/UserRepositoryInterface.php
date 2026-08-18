<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function createToken(User $user): string;

    public function revokeToken(User $user): void;

    public function createWithProfile(array $userData, array $profileData = []): User;

    public function updateWithProfile(User $user, array $userData, array $profileData = []): User;

    /**
     * @param  list<string>  $relationship
     */
    public function showUserWith(int $id, array $relationship = []): ?User;
}
