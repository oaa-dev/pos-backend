<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * `/stores` is the only place in this application that deliberately crosses the
 * tenant boundary, and the only place that takes a store id. `Store` carries no
 * `BelongsToStore` scope, so route-model binding resolves any customer's row
 * with nothing narrowing it — `can:stores.*` is the entire guard.
 *
 * Every test here is written to be able to fail. "A holder sees the list"
 * passes with the guard removed and proves nothing; the load-bearing tests are
 * the ones where an owner holding *every store-level permission* is refused.
 */

/** An operator: holds the platform module, nothing store-specific. */
function actingAsStoreManager(): User
{
    test()->seed(PermissionSeeder::class);
    // The three global roles have to exist before a store can be opened —
    // `addOwner()` assigns `owner` and refuses outright when it is missing.
    test()->seed(SystemRoleSeeder::class);

    return actingAsUserWith(['stores.view', 'stores.create', 'stores.update', 'stores.delete']);
}

/** A customer at full power inside her own shop — and no further. */
function actingAsFullOwner(): User
{
    test()->seed(PermissionSeeder::class);

    return actingAsUserWith(
        Permission::whereDoesntHave(
            'permissionGroup',
            fn ($group) => $group->whereIn('name', PermissionSeeder::PLATFORM_MODULES),
        )->pluck('name')->all(),
    );
}

// --- the guard ------------------------------------------------------------

it('refuses every route to an owner holding all store-level permissions', function () {
    actingAsFullOwner();

    $other = anotherStore();

    $this->getJson('/api/v1/stores')->assertForbidden();
    $this->postJson('/api/v1/stores', ['name' => 'Sneaky'])->assertForbidden();
    $this->getJson("/api/v1/stores/{$other->id}")->assertForbidden();
    $this->patchJson("/api/v1/stores/{$other->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->deleteJson("/api/v1/stores/{$other->id}")->assertForbidden();
    $this->postJson("/api/v1/stores/{$other->id}/owner", [])->assertForbidden();

    expect($other->fresh()->name)->not->toBe('Renamed')
        ->and(Store::where('name', 'Sneaky')->exists())->toBeFalse();
});

it('requires authentication', function () {
    app()['auth']->forgetGuards();

    $this->getJson('/api/v1/stores')->assertUnauthorized();
    $this->postJson('/api/v1/stores', ['name' => 'x'])->assertUnauthorized();
});

it('enforces each verb separately', function () {
    test()->seed(PermissionSeeder::class);
    actingAsUserWith(['stores.view']);

    $store = anotherStore();

    $this->getJson('/api/v1/stores')->assertOk();
    $this->postJson('/api/v1/stores', ['name' => 'Nope'])->assertForbidden();
    $this->patchJson("/api/v1/stores/{$store->id}", ['name' => 'Nope'])->assertForbidden();
    $this->deleteJson("/api/v1/stores/{$store->id}")->assertForbidden();
    $this->postJson("/api/v1/stores/{$store->id}/owner", [])->assertForbidden();
});

// --- listing --------------------------------------------------------------

it('lists stores belonging to more than one tenant', function () {
    actingAsStoreManager();

    $other = anotherStore();
    $other->update(['name' => 'Rival Sari-Sari']);

    $response = $this->getJson('/api/v1/stores')->assertOk();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Rival Sari-Sari')
        ->and($names)->toContain(testStore()->name);
});

it('hides closed stores from the listing', function () {
    actingAsStoreManager();

    $closed = anotherStore();
    $closed->update(['name' => 'Shuttered']);
    $closed->delete();

    expect(collect($this->getJson('/api/v1/stores')->json('data'))->pluck('name'))
        ->not->toContain('Shuttered');
});

it('searches by name', function () {
    actingAsStoreManager();

    anotherStore()->update(['name' => 'Aling Nena Sari-Sari']);

    $names = collect($this->getJson('/api/v1/stores?filter[q]=Aling')->json('data'))->pluck('name');

    expect($names)->toContain('Aling Nena Sari-Sari')
        ->and($names)->not->toContain(testStore()->name);
});

// --- open, then add an owner ----------------------------------------------

it('opens a store with no owner, inactive, and its own catalogue', function () {
    actingAsStoreManager();

    $response = $this->postJson('/api/v1/stores', [
        'name' => 'Mang Tonyo Store',
        'description' => 'Corner of Rizal and Mabini',
    ])->assertCreated();

    // Present and null, not absent — the callback form of whenLoaded. The
    // screen keys off this to offer "Add owner", so a dropped key reads as a
    // finished store.
    expect($response->json('data'))->toHaveKey('owner')
        ->and($response->json('data.owner'))->toBeNull()
        ->and($response->json('data.status'))->toBe('inactive')
        ->and($response->json('data.description'))->toBe('Corner of Rizal and Mabini');

    // Opening a store seeds nothing — roles are global and already exist.
    // What matters is that no per-store copies were made.
    $store = Store::where('name', 'Mang Tonyo Store')->first();

    expect($store)->not->toBeNull()
        ->and(Role::system()->pluck('slug')->sort()->values()->all())
        ->toBe(['owner', 'superadmin', 'tindera']);
});

/**
 * Asserted on the **response body**, not the database. Roles are tenant-scoped,
 * so a relation resolved outside the new store's scope comes back null with no
 * error — the failure that has already cost one owner every permission they
 * were meant to hold.
 */
it('opens a store with the login account that will run it', function () {
    actingAsStoreManager();

    $response = $this->postJson('/api/v1/stores', [
        'name' => 'Mang Tonyo Store',
        'description' => 'Corner of Rizal and Mabini',
        'account' => [
            'firstname' => 'Tonyo',
            'lastname' => 'Reyes',
            'email' => 'tonyo@example.test',
            'phone_number' => '09171234567',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ],
    ])->assertCreated();

    $response
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.owner_name', 'Reyes Tonyo')
        ->assertJsonPath('data.phone', '09171234567')
        ->assertJsonPath('data.owner.email', 'tonyo@example.test')
        ->assertJsonPath('data.owner.profile.firstname', 'Tonyo')
        ->assertJsonPath('data.owner.profile.lastname', 'Reyes');

    $store = Store::where('name', 'Mang Tonyo Store')->firstOrFail();
    $account = User::where('email', 'tonyo@example.test')
        ->with(['profile', 'role.permissions'])
        ->firstOrFail();

    expect($account->role?->slug)->toBe('owner')
        ->and($account->role?->permissions)->not->toBeEmpty()
        ->and($account->profile?->firstname)->toBe('Tonyo')
        ->and($account->profile?->lastname)->toBe('Reyes');

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $account->id,
        'firstname' => 'Tonyo',
        'lastname' => 'Reyes',
    ]);

    $this->assertDatabaseHas('store_users', [
        'store_id' => $store->id,
        'user_id' => $account->id,
        'is_owner' => true,
    ]);

    // The operator's list crosses tenants, but it must still expose the
    // profile belonging to this store's owner rather than the actor's tenant.
    $listedStore = collect($this->getJson('/api/v1/stores')->json('data'))
        ->firstWhere('id', $store->id);

    expect(data_get($listedStore, 'owner.profile.firstname'))->toBe('Tonyo')
        ->and(data_get($listedStore, 'owner.profile.lastname'))->toBe('Reyes');

    app()['auth']->forgetGuards();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'tonyo@example.test',
        'password' => 'Str0ng!Passw0rd',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.store.id', $store->id)
        ->assertJsonPath('data.user.role.slug', 'owner');
});

it('rolls back the store when its login account cannot be created', function () {
    actingAsStoreManager();

    $taken = User::first()->email;

    $this->postJson('/api/v1/stores', [
        'name' => 'Must Not Remain',
        'account' => [
            'firstname' => 'Tonyo',
            'lastname' => 'Reyes',
            'email' => $taken,
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account.email');

    expect(Store::where('name', 'Must Not Remain')->exists())->toBeFalse();
});

it('adds an owner who holds that store’s owner role with permissions', function () {
    actingAsStoreManager();

    $store = Store::find(
        $this->postJson('/api/v1/stores', ['name' => 'Mang Tonyo Store'])->json('data.id')
    );

    $response = $this->postJson("/api/v1/stores/{$store->id}/owner", [
        'firstname' => 'Tonyo',
        'lastname' => 'Reyes',
        'email' => 'tonyo@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ])->assertCreated();

    $response->assertJsonPath('data.owner.role.slug', 'owner')
        ->assertJsonPath('data.store.owner.email', 'tonyo@example.test');

    expect($response->json('data.owner.role.permissions'))->not->toBeEmpty();

    $owner = User::where('email', 'tonyo@example.test')->first();

    expect($owner->store?->id)->toBe($store->id);

    $this->assertDatabaseHas('store_users', [
        'store_id' => $store->id,
        'user_id' => $owner->id,
        'is_owner' => true,
    ]);
});

it('rejects an owner email already in use and leaves nothing behind', function () {
    actingAsStoreManager();

    $store = Store::find(
        $this->postJson('/api/v1/stores', ['name' => 'Mang Tonyo Store'])->json('data.id')
    );

    $taken = User::first()->email;

    $this->postJson("/api/v1/stores/{$store->id}/owner", [
        'firstname' => 'Tonyo',
        'lastname' => 'Reyes',
        'email' => $taken,
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseMissing('store_users', ['store_id' => $store->id, 'is_owner' => true]);
});

// --- update and close ------------------------------------------------------

it('renames only the store it was given', function () {
    actingAsStoreManager();

    $other = anotherStore();
    $other->update(['name' => 'Rival Sari-Sari']);

    $this->patchJson("/api/v1/stores/{$other->id}", ['name' => 'Renamed Rival'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Rival');

    expect($other->fresh()->name)->toBe('Renamed Rival')
        ->and(testStore()->fresh()->name)->not->toBe('Renamed Rival');
});

it('suspends a store by status', function () {
    actingAsStoreManager();

    $store = anotherStore();

    $this->patchJson("/api/v1/stores/{$store->id}", ['status' => 'inactive'])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    expect($store->fresh()->status->value)->toBe('inactive');
});

it('ignores a slug submitted in the payload', function () {
    actingAsStoreManager();

    $store = anotherStore();
    $before = $store->slug;

    $this->patchJson("/api/v1/stores/{$store->id}", [
        'name' => 'Renamed',
        'slug' => 'hijacked-slug',
    ])->assertOk();

    expect($store->fresh()->slug)->toBe($before);
});

it('closes a store without erasing it', function () {
    actingAsStoreManager();

    $store = anotherStore();

    $this->deleteJson("/api/v1/stores/{$store->id}")->assertOk();

    expect(Store::find($store->id))->toBeNull()
        ->and(Store::withTrashed()->find($store->id))->not->toBeNull();
});

it('404s on a store that does not exist', function () {
    actingAsStoreManager();

    $this->getJson('/api/v1/stores/999999')->assertNotFound();
});

it('replaces an operator-managed store logo and removes the previous file', function () {
    Storage::fake('public');
    actingAsStoreManager();

    $store = anotherStore();

    $this->postJson("/api/v1/stores/{$store->id}/logo", [
        'logo' => UploadedFile::fake()->image('first.jpg', 320, 320),
    ])->assertOk();

    $first = $store->fresh()->logo_path;
    expect($first)->not->toBeNull();
    Storage::disk('public')->assertExists($first);

    $this->postJson("/api/v1/stores/{$store->id}/logo", [
        'logo' => UploadedFile::fake()->image('second.png', 320, 320),
    ])->assertOk();

    $second = $store->fresh()->logo_path;

    expect($second)->not->toBe($first);
    // The previous file is deleted only once the new path is durable.
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $this->deleteJson("/api/v1/stores/{$store->id}/logo")->assertOk();

    expect($store->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

it('refuses a store logo upload without stores.update', function () {
    Storage::fake('public');
    actingAsUserWith(['stores.view']);

    $store = anotherStore();

    $this->postJson("/api/v1/stores/{$store->id}/logo", [
        'logo' => UploadedFile::fake()->image('nope.jpg', 320, 320),
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
