<?php

namespace App\Repositories\Contracts;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface BranchRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * The branches a select may offer the current actor, ordered by name.
     *
     * @return Collection<int, Branch>
     */
    public function dropdown(?int $storeId = null): Collection;

    public function findByCode(string $code): ?Branch;

    public function assignUser(Branch $branch, User $user, bool $isPrimary = false): Branch;

    public function unassignUser(Branch $branch, User $user): Branch;
}
