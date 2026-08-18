<?php

namespace App\Services;

use App\Repositories\Contracts\BarangayRepositoryInterface;

class BarangayService extends BaseService
{
    public function __construct(BarangayRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
