<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Gate::before resolves abilities from users.role_id. These cases pin the
 * three outcomes that matter: granted, denied, and denied-by-default.
 *
 * Every denial asserts on the response **body**, not just the status. A 403
 * that escapes the ApiResponse envelope still passes assertForbidden() while
 * leaking a stack trace — that exact mismatch is documented in
 * docs/knowledge/solutions/authorization-issues/.
 */
it('denies a user who holds no role at all', function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => null]));

    $this->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('exception');
});

it('denies a user whose role lacks the ability', function () {
    actingAsUserWith(['sales.create']);

    $this->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('exception');
});

it('allows a user whose role holds the ability', function () {
    actingAsUserWith(['users.view']);

    $this->getJson('/api/v1/users')->assertOk();
});

/**
 * Read and write are separate abilities — a tindera-shaped role that can list
 * staff must not thereby be able to create them.
 */
it('separates read from write on the same module', function () {
    actingAsUserWith(['users.view']);

    $this->getJson('/api/v1/users')->assertOk();

    $this->postJson('/api/v1/user', [
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('keeps PSGC reference data open to any authenticated user', function () {
    actingAsUserWith();

    $this->getJson('/api/v1/regions')->assertOk();
    $this->getJson('/api/v1/provinces')->assertOk();
    $this->getJson('/api/v1/cities')->assertOk();
    $this->getJson('/api/v1/barangays')->assertOk();
});

it('leaves the authenticated identity endpoints ungated', function () {
    actingAsUserWith();

    $this->getJson('/api/v1/auth/me')->assertOk();
});

it('still refuses an unauthenticated request before reaching the gate', function () {
    app()['auth']->forgetGuards();

    $this->getJson('/api/v1/users')
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});

/**
 * The permission catalogue drives the role editor, so it is gated behind role
 * administration rather than being readable by anyone with a token.
 */
it('gates the permission catalogue behind roles.view', function () {
    actingAsUserWith(['users.view']);

    $this->getJson('/api/v1/permissions')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
