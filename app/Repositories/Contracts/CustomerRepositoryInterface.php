<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;

interface CustomerRepositoryInterface extends BaseRepositoryInterface
{
    public function adjustBalance(Customer $customer, string $delta): Customer;

    public function setBalance(Customer $customer, string $balance): Customer;

    public function lockFor(int $customerId): Customer;
}
