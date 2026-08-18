<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Customer;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerRepository extends BaseRepository implements BranchScopedInterface, CustomerRepositoryInterface
{
    protected function model(): string
    {
        return Customer::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            'store_id',
            // Nickname first in spirit: the tindera searches by palayaw.
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'nickname', 'phone'])),
            'name',
            'nickname',
            'is_blocked',
            'branch_id',
            AllowedFilter::callback('owing', fn ($query) => $query->owing()),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'current_balance', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['branch'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with('branch');
    }

    public function adjustBalance(Customer $customer, string $delta): Customer
    {
        return $this->setBalance(
            $customer,
            bcadd((string) $customer->current_balance, $delta, 2),
        );
    }

    /**
     * `current_balance` is a cache of the credit ledger, the same way
     * `branch_product_stocks` caches the stock ledger. Only CreditService
     * writes it, always inside the transaction that wrote the ledger row.
     */
    public function setBalance(Customer $customer, string $balance): Customer
    {
        $customer->current_balance = $balance;
        $customer->save();

        return $customer;
    }

    public function lockFor(int $customerId): Customer
    {
        return $this->builder()->whereKey($customerId)->lockForUpdate()->firstOrFail();
    }
}
