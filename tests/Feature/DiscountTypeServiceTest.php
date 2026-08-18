<?php

use App\Data\DiscountTypeData;
use App\Enums\StatusEnum;
use App\Models\DiscountType;
use App\Repositories\Contracts\DiscountTypeRepositoryInterface;
use App\Services\DiscountTypeService;
use Database\Seeders\DiscountTypeSeeder;

function discountTypes(): DiscountTypeService
{
    return app(DiscountTypeService::class);
}

beforeEach(function () {
    actingAsOwner();
    $this->seed(DiscountTypeSeeder::class);
});

it('creates a store-defined type with a derived slug', function () {
    $type = discountTypes()->store(DiscountTypeData::from(['name' => 'Fiesta Sale', 'percentage' => 5]));

    expect($type->slug)->toBe('fiesta-sale')
        ->and((string) $type->percentage)->toBe('5.00')
        ->and($type->status)->toBe(StatusEnum::ACTIVE)
        // Never settable through the API — a row becomes statutory by being
        // seeded, not by being asked for.
        ->and($type->is_system)->toBeFalse();
});

it('refuses to let the API create a statutory row', function () {
    $type = discountTypes()->store(DiscountTypeData::from([
        'name' => 'Fake Statutory',
        'is_system' => true,
    ]));

    expect($type->is_system)->toBeFalse();
});

it('resolves slug collisions', function () {
    discountTypes()->store(DiscountTypeData::from(['name' => 'Fiesta Sale']));
    $second = discountTypes()->store(DiscountTypeData::from(['name' => 'Fiesta Sale']));

    expect($second->slug)->toBe('fiesta-sale-2');
});

/*
|--------------------------------------------------------------------------
| The statutory guard
|--------------------------------------------------------------------------
| These are the tests the feature exists for. "CRUD works" would pass with
| every guard removed, so each one below asserts a refusal.
*/

it('refuses to delete the statutory senior discount', function () {
    discountTypes()->retire(DiscountType::where('slug', 'senior')->first());
})->throws(InvalidArgumentException::class, 'cannot be deleted');

it('refuses to delete the statutory pwd discount', function () {
    discountTypes()->retire(DiscountType::where('slug', 'pwd')->first());
})->throws(InvalidArgumentException::class, 'cannot be deleted');

it('refuses to re-rate the statutory senior discount', function () {
    discountTypes()->updateType(
        DiscountType::where('slug', 'senior')->first(),
        DiscountTypeData::from(['percentage' => 15]),
    );
})->throws(InvalidArgumentException::class, 'cannot have its percentage changed');

it('refuses to switch off the statutory logbook', function () {
    discountTypes()->updateType(
        DiscountType::where('slug', 'senior')->first(),
        DiscountTypeData::from(['requires_identification' => false]),
    );
})->throws(InvalidArgumentException::class, 'cannot have its requires_identification changed');

it('refuses to re-slug a statutory row', function () {
    discountTypes()->updateType(
        DiscountType::where('slug', 'senior')->first(),
        DiscountTypeData::from(['slug' => 'senior-old']),
    );
})->throws(InvalidArgumentException::class, 'cannot have its slug changed');

/*
| What a statutory row *may* do — the guard must not be so broad it makes the
| row unmanageable.
*/

it('allows renaming a statutory row', function () {
    $type = discountTypes()->updateType(
        DiscountType::where('slug', 'senior')->first(),
        DiscountTypeData::from(['name' => 'Senior Citizen (RA 9994)']),
    );

    expect($type->name)->toBe('Senior Citizen (RA 9994)')
        ->and((string) $type->percentage)->toBe('20.00');
});

it('allows deactivating a statutory row', function () {
    $type = discountTypes()->updateType(
        DiscountType::where('slug', 'senior')->first(),
        DiscountTypeData::from(['status' => StatusEnum::INACTIVE->value]),
    );

    expect($type->status)->toBe(StatusEnum::INACTIVE);
});

it('accepts an unchanged percentage on a statutory row', function () {
    // A form that round-trips every field must not trip the guard by
    // resubmitting the value it was given.
    $type = discountTypes()->updateType(
        DiscountType::where('slug', 'senior')->first(),
        DiscountTypeData::from(['name' => 'Senior', 'percentage' => '20.00']),
    );

    expect($type->name)->toBe('Senior');
});

/*
| Store-defined rows stay fully editable.
*/

it('retires a store-defined type without erasing it', function () {
    $type = discountTypes()->store(DiscountTypeData::from(['name' => 'Fiesta Sale']));

    discountTypes()->retire($type);

    expect(DiscountType::find($type->id))->toBeNull()
        ->and(DiscountType::withTrashed()->find($type->id))->not->toBeNull();
});

it('re-rates a store-defined type freely', function () {
    $type = discountTypes()->store(DiscountTypeData::from(['name' => 'Fiesta Sale', 'percentage' => 5]));

    $updated = discountTypes()->updateType($type, DiscountTypeData::from(['percentage' => 10]));

    expect((string) $updated->percentage)->toBe('10.00');
});

it('resolves only active types as applicable', function () {
    $type = discountTypes()->store(DiscountTypeData::from(['name' => 'Fiesta Sale', 'percentage' => 5]));
    $repository = app(DiscountTypeRepositoryInterface::class);

    expect($repository->findApplicable('fiesta-sale'))->not->toBeNull();

    discountTypes()->updateType($type, DiscountTypeData::from(['status' => StatusEnum::INACTIVE->value]));

    // A sale quoting a switched-off slug must fail loudly, not discount zero
    // and look correct.
    expect($repository->findApplicable('fiesta-sale'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Over HTTP
|--------------------------------------------------------------------------
| The guards have to hold at the edge, not just in the service — a 500 from an
| unhandled exception would be a leak, not a refusal.
*/

it('refuses to delete a statutory row over HTTP with a 422 envelope', function () {
    $senior = DiscountType::where('slug', 'senior')->first();

    $this->deleteJson("/api/v1/discount-type/{$senior->id}")
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The statutory senior discount cannot be deleted. Deactivate it instead.');

    expect(DiscountType::find($senior->id))->not->toBeNull();
});

it('refuses to re-rate a statutory row over HTTP', function () {
    $senior = DiscountType::where('slug', 'senior')->first();

    $this->patchJson("/api/v1/discount-type/{$senior->id}", ['percentage' => 15])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect((string) $senior->fresh()->percentage)->toBe('20.00');
});

it('creates and retires a store-defined type over HTTP', function () {
    $id = $this->postJson('/api/v1/discount-type', ['name' => 'Fiesta Sale', 'percentage' => 5])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'fiesta-sale')
        ->assertJsonPath('data.is_system', false)
        ->json('data.id');

    $this->deleteJson("/api/v1/discount-type/{$id}")->assertOk();

    expect(DiscountType::find($id))->toBeNull();
});

it('ignores an is_system flag sent by a client', function () {
    $this->postJson('/api/v1/discount-type', ['name' => 'Sneaky', 'is_system' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_system', false);
});

it('requires a write permission to manage discount types', function () {
    actingAsUserWith(['sales.view']);

    $this->postJson('/api/v1/discount-type', ['name' => 'Nope'])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
