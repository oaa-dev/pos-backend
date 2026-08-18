<?php

use App\Data\SupplierData;
use App\Enums\StatusEnum;
use App\Models\Supplier;
use App\Services\SupplierService;

/**
 * `SupplierService` had no direct test before the DTO conversion — supplier
 * rows appeared only as fixtures inside `PurchasingControllerTest` and
 * `ReorderReportTest`, so nothing asserted what the service itself writes.
 *
 * These were written first, against the array signature, so the conversion had
 * something to be measured against rather than only "the suite is still green".
 */
function suppliers(): SupplierService
{
    return app(SupplierService::class);
}

it('persists the store the request names', function () {
    $store = testStore();

    $supplier = suppliers()->store(SupplierData::from([
        'store_id' => $store->id,
        'name' => 'Mang Tonyo Distribution',
    ]));

    expect($supplier->store_id)->toBe($store->id)
        ->and($supplier->name)->toBe('Mang Tonyo Distribution');
});

it('defaults a new supplier to active', function () {
    $supplier = suppliers()->store(SupplierData::from([
        'store_id' => testStore()->id,
        'name' => 'Kaunlaran Trading',
    ]));

    expect($supplier->status)->toBe(StatusEnum::ACTIVE);
});

/**
 * The `Optional` contract: a field the request did not submit must be left
 * alone, not nulled. This is what stops a PATCH of one field wiping the rest.
 */
it('leaves omitted fields untouched on update', function () {
    $supplier = Supplier::factory()->create([
        'store_id' => testStore()->id,
        'name' => 'Before',
        'contact_person' => 'Aling Nena',
        'phone' => '09171234567',
        'notes' => 'Delivers Tuesdays',
    ]);

    $updated = suppliers()->updateSupplier($supplier, SupplierData::from([
        'name' => 'After',
    ]));

    expect($updated->name)->toBe('After')
        ->and($updated->contact_person)->toBe('Aling Nena')
        ->and($updated->phone)->toBe('09171234567')
        ->and($updated->notes)->toBe('Delivers Tuesdays');
});

it('clears a field that is explicitly sent as null', function () {
    $supplier = Supplier::factory()->create([
        'store_id' => testStore()->id,
        'notes' => 'Delivers Tuesdays',
    ]);

    $updated = suppliers()->updateSupplier($supplier, SupplierData::from([
        'notes' => null,
    ]));

    expect($updated->notes)->toBeNull();
});

it('deactivates a supplier without touching its other fields', function () {
    $supplier = Supplier::factory()->create([
        'store_id' => testStore()->id,
        'name' => 'Kaunlaran Trading',
    ]);

    $updated = suppliers()->updateSupplier($supplier, SupplierData::from([
        'status' => StatusEnum::INACTIVE->value,
    ]));

    expect($updated->status)->toBe(StatusEnum::INACTIVE)
        ->and($updated->name)->toBe('Kaunlaran Trading');
});
