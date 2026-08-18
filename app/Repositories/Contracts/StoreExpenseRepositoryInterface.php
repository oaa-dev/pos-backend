<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface StoreExpenseRepositoryInterface extends BaseRepositoryInterface
{
    public function betweenDates(int $branchId, string $from, string $to): Collection;
}
