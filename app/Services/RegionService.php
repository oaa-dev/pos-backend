<?php

namespace App\Services;

use App\Repositories\Contracts\RegionRepositoryInterface;

class RegionService extends BaseService
{
    public function __construct(RegionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
