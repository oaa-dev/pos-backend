<?php

namespace App\Services;

use App\Data\CustomerData;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Spatie\LaravelData\Optional;

class CustomerService extends BaseService
{
    public function __construct(
        protected readonly CustomerRepositoryInterface $customers
    ) {
        parent::__construct($customers);
    }

    public function store(CustomerData $data): Customer
    {
        return $this->customers->create($this->attributes($data))->load('branch');
    }

    public function updateCustomer(Customer $customer, CustomerData $data): Customer
    {
        $values = $this->attributes($data);

        if ($values !== []) {
            $customer = $this->customers->update($customer, $values);
        }

        return $customer->load('branch');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(CustomerData $data): array
    {
        return array_filter([
            'store_id' => $data->store_id,
            'name' => $data->name,
            'nickname' => $data->nickname,
            'phone' => $data->phone,
            'branch_id' => $data->branch_id,
            'is_blocked' => $data->is_blocked,
            'notes' => $data->notes,
        ], fn ($value) => ! $value instanceof Optional);
    }
}
