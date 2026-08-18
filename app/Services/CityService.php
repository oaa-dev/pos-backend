<?php

namespace App\Services;

use App\Repositories\Contracts\CityRepositoryInterface;

class CityService extends BaseService
{
    public function __construct(CityRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
