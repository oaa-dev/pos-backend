<?php

namespace App\Services;

use App\Data\SupplierData;
use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Spatie\LaravelData\Optional;

class SupplierService extends BaseService
{
    public function __construct(
        protected readonly SupplierRepositoryInterface $suppliers
    ) {
        parent::__construct($suppliers);
    }

    public function store(SupplierData $data): Supplier
    {
        return $this->suppliers->create($this->attributes($data))->load('address');
    }

    public function updateSupplier(Supplier $supplier, SupplierData $data): Supplier
    {
        $values = $this->attributes($data);

        if ($values === []) {
            return $supplier->load('address');
        }

        return $this->suppliers->update($supplier, $values)->load('address');
    }

    /**
     * `Optional` means "not submitted" and is dropped; an explicit null is a
     * real value — clearing the notes — and is kept. Filtering on `!== null`
     * instead would make a field impossible to clear.
     *
     * @return array<string, mixed>
     */
    private function attributes(SupplierData $data): array
    {
        return array_filter([
            'store_id' => $data->store_id,
            'name' => $data->name,
            'contact_person' => $data->contact_person,
            'phone' => $data->phone,
            'email' => $data->email,
            'notes' => $data->notes,
            'status' => $data->status,
        ], fn ($value) => ! $value instanceof Optional);
    }
}
