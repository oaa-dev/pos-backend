<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * `/store` carries the tenancy weight for this phase, so every test here builds
 * a *second* store and asserts it is unreachable. "GET /store returns 200"
 * passes with the boundary entirely removed and proves nothing.
 *
 * The route takes no id, which is the control itself: `Store` has no
 * `BelongsToStore` scope — it is the tenant — so `Store::find($id)` returns any
 * customer's row and route-model binding would resolve it before any scope or
 * repository could refuse. There is no id to tamper with.
 */
it('returns the actor’s own store and not another customer’s', function () {
    $other = anotherStore();
    $other->update(['name' => 'Rival Sari-Sari']);

    actingAsUserWith(['settings.view']);

    $response = $this->getJson('/api/v1/store');

    $response->assertOk()
        ->assertJsonPath('data.id', testStore()->id)
        ->assertJsonPath('data.name', testStore()->name);

    expect($response->getContent())->not->toContain('Rival Sari-Sari')
        ->and($response->json('data.id'))->not->toBe($other->id);
});

/**
 * Exact key list, so a field added to StoreResource has to be considered here
 * rather than appearing on the owner's own endpoint unnoticed. `owner` is
 * absent because this route never loads it — the owner is reading her own
 * store, not looking up who runs it.
 */
it('never exposes deleted_at', function () {
    actingAsUserWith(['settings.view']);

    expect(array_keys($this->getJson('/api/v1/store')->json('data')))
        ->toBe(['id', 'name', 'description', 'slug', 'owner_name', 'phone', 'logo_url', 'status', 'created_at']);
});

it('renames only the acting user’s store', function () {
    $other = anotherStore();
    $other->update(['name' => 'Rival Sari-Sari']);

    actingAsUserWith(['settings.view', 'settings.update']);

    $this->patchJson('/api/v1/store', ['name' => 'Aling Nena Store'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Aling Nena Store');

    expect(testStore()->fresh()->name)->toBe('Aling Nena Store')
        ->and($other->fresh()->name)->toBe('Rival Sari-Sari');
});

it('updates owner_name and phone', function () {
    actingAsUserWith(['settings.view', 'settings.update']);

    $this->patchJson('/api/v1/store', [
        'owner_name' => 'Cruz Bea',
        'phone' => '09171234567',
    ])->assertOk()
        ->assertJsonPath('data.owner_name', 'Cruz Bea')
        ->assertJsonPath('data.phone', '09171234567');
});

/**
 * The slug is the one globally unique handle a store has, and status is
 * suspension — a store must not be able to un-suspend itself. Both are absent
 * from UpdateStoreRequest's rules and from StoreData, so a submitted value has
 * nowhere to land. Asserting it is *ignored*, not rejected: a 422 here would
 * be a different design, and this test would catch either changing.
 */
it('ignores slug and status submitted in the payload', function () {
    actingAsUserWith(['settings.view', 'settings.update']);

    $before = testStore()->fresh();

    $this->patchJson('/api/v1/store', [
        'name' => 'Renamed',
        'slug' => 'hijacked-slug',
        'status' => 'inactive',
    ])->assertOk();

    $after = testStore()->fresh();

    expect($after->name)->toBe('Renamed')
        ->and($after->slug)->toBe($before->slug)
        ->and($after->status)->toBe($before->status);
});

it('leaves the store untouched when the payload carries nothing updatable', function () {
    actingAsUserWith(['settings.view', 'settings.update']);

    $before = testStore()->fresh();

    $this->patchJson('/api/v1/store', [])
        ->assertOk()
        ->assertJsonPath('data.name', $before->name);

    expect(testStore()->fresh()->updated_at->eq($before->updated_at))->toBeTrue();
});

it('uploads, replaces, and removes the acting store logo', function () {
    Storage::fake('public');
    actingAsUserWith(['settings.view', 'settings.update']);

    $first = UploadedFile::fake()->image('first-logo.png');

    $this->post('/api/v1/store/logo', ['logo' => $first], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.id', testStore()->id);

    $firstPath = testStore()->fresh()->logo_path;
    Storage::disk('public')->assertExists($firstPath);

    $second = UploadedFile::fake()->image('second-logo.webp');

    $this->post('/api/v1/store/logo', ['logo' => $second], ['Accept' => 'application/json'])
        ->assertOk();

    $secondPath = testStore()->fresh()->logo_path;
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    $this->deleteJson('/api/v1/store/logo')
        ->assertOk()
        ->assertJsonPath('data.logo_url', null);

    expect(testStore()->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

it('validates store logos and protects logo changes with settings.update', function () {
    Storage::fake('public');
    actingAsUserWith(['settings.view']);

    $this->post('/api/v1/store/logo', [
        'logo' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertForbidden();

    $this->deleteJson('/api/v1/store/logo')->assertForbidden();
});

// --- the guards ------------------------------------------------------------

it('refuses a tindera holding sales permissions but not settings.view', function () {
    actingAsUserWith(['sales.create', 'sales.view']);

    $this->getJson('/api/v1/store')->assertForbidden();
});

it('refuses PATCH to a holder of settings.view alone', function () {
    actingAsUserWith(['settings.view']);

    $this->patchJson('/api/v1/store', ['name' => 'Should Not Land'])
        ->assertForbidden();

    expect(testStore()->fresh()->name)->not->toBe('Should Not Land');
});

it('requires authentication', function () {
    app()['auth']->forgetGuards();

    $this->getJson('/api/v1/store')->assertUnauthorized();
    $this->patchJson('/api/v1/store', ['name' => 'x'])->assertUnauthorized();
});

it('has no route that takes a store id', function () {
    actingAsUserWith(['settings.view', 'settings.update']);

    $other = anotherStore();

    $this->getJson("/api/v1/store/{$other->id}")->assertNotFound();
    $this->patchJson("/api/v1/store/{$other->id}", ['name' => 'x'])->assertNotFound();

    expect($other->fresh()->name)->not->toBe('x');
});

it('rejects a name longer than the column allows', function () {
    actingAsUserWith(['settings.view', 'settings.update']);

    $this->patchJson('/api/v1/store', ['name' => str_repeat('a', 256)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('404s when the store has been closed out from under the account', function () {
    actingAsUserWith(['settings.view']);

    // Reachable, unlike a hard delete: `stores` is soft-deleted, so an operator
    // closing a shop leaves its users pointing at a row `$user->store` now
    // resolves to null. That must render as a 404 envelope, not a 500.
    testStore()->delete();

    $this->getJson('/api/v1/store')->assertNotFound();
});
