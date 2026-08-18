<?php

use App\Models\Branch;
use App\Models\User;

/**
 * The users module lost its store awareness when `users.store_id` was dropped.
 * `store_users` became the only record of membership, and nothing wrote or read
 * it outside registration.
 *
 * Every assertion here is the negative one on purpose. "My store's users are in
 * the list" passes with the bug present; "the other store's user is *not*"
 * does not.
 */
it('attaches a created account to the store the request names', function () {
    actingAsOwner();

    $response = $this->postJson('/api/v1/user', [
        'store_id' => testStore()->id,
        'firstname' => 'Maria',
        'lastname' => 'Santos',
        'email' => 'maria@example.test',
    ])->assertCreated();

    $user = User::where('email', 'maria@example.test')->firstOrFail();

    $this->assertDatabaseHas('store_users', [
        'store_id' => testStore()->id,
        'user_id' => $user->id,
        'is_owner' => false,
    ]);

    // Without the pivot row this is null and the account's own `GET /store`
    // 404s — a user belonging nowhere.
    expect($user->store?->id)->toBe(testStore()->id)
        ->and($response->json('data.store.id'))->toBe(testStore()->id);
});

it('does not make a created account an owner', function () {
    actingAsOwner();

    $this->postJson('/api/v1/user', [
        'store_id' => testStore()->id,
        'firstname' => 'Maria',
        'lastname' => 'Santos',
        'email' => 'maria@example.test',
    ])->assertCreated();

    $user = User::where('email', 'maria@example.test')->firstOrFail();

    expect($user->ownsStore())->toBeFalse();
});

it('excludes another store’s staff from a store-filtered listing', function () {
    actingAsOwner();

    $other = anotherStore();
    $outsider = User::factory()->create(['email' => 'outsider@example.test']);
    $outsider->stores()->syncWithoutDetaching([$other->id => ['is_owner' => false]]);

    $emails = collect(
        $this->getJson('/api/v1/users?filter[store_id]='.testStore()->id)->json('data')
    )->pluck('email');

    expect($emails)->not->toContain('outsider@example.test');
});

it('refuses to assign a user from another store to a branch', function () {
    actingAsOwner();

    $branch = Branch::factory()->create(['store_id' => testStore()->id]);

    $other = anotherStore();
    $outsider = User::factory()->create();
    $outsider->stores()->syncWithoutDetaching([$other->id => ['is_owner' => false]]);

    // Asserting the body, not only the status: Laravel rewrites
    // AuthorizationException before the render callbacks run, so a bare status
    // check can pass on the wrong envelope.
    $this->postJson("/api/v1/branch/{$branch->id}/users", ['user_id' => $outsider->id])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->assertDatabaseMissing('branch_user', [
        'branch_id' => $branch->id,
        'user_id' => $outsider->id,
    ]);
});

it('still assigns a user who belongs to the branch’s store', function () {
    actingAsOwner();

    $branch = Branch::factory()->create(['store_id' => testStore()->id]);

    $insider = User::factory()->create();
    $insider->stores()->syncWithoutDetaching([testStore()->id => ['is_owner' => false]]);

    $this->postJson("/api/v1/branch/{$branch->id}/users", ['user_id' => $insider->id])
        ->assertOk();

    $this->assertDatabaseHas('branch_user', [
        'branch_id' => $branch->id,
        'user_id' => $insider->id,
    ]);
});
