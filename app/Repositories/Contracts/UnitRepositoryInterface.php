<?php

namespace App\Repositories\Contracts;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

interface UnitRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Every live unit, ordered by name, for a select.
     *
     * @return Collection<int, Unit>
     */
    public function dropdown(): Collection;
}
