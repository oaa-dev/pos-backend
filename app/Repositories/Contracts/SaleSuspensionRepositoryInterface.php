<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface SaleSuspensionRepositoryInterface extends BaseRepositoryInterface
{
    public function suspendedForBranch(int $branchId): Collection;
}
