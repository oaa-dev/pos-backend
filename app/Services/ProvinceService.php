<?php

namespace App\Services;

use App\Repositories\Contracts\ProvinceRepositoryInterface;

class ProvinceService extends BaseService
{
    public function __construct(ProvinceRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
