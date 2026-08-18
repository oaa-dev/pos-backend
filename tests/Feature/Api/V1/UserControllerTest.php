<?php

use App\Enums\AccountStatusEnum;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;

it('lists users when authenticated', function () {
    actingAsOwner();
    User::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/users');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(4, 'data');
});

it('shows a single user when authenticated', function () {
    actingAsOwner();
    $user = User::factory()->create();

    $response = $this->getJson("/api/v1/user/{$user->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

it('includes the profile and role on the index without an N+1', function () {
    actingAsOwner();

    $role = Role::factory()->create();
    $users = User::factory()->count(3)->create(['role_id' => $role->id]);

    foreach ($users as $user) {
        UserProfile::factory()->create(['user_id' => $user->id]);
    }

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/users');
    $queryCount = count(DB::getQueryLog());

    $response->assertOk();

    // The row query plus its eager loads — not one query per row.
    expect($queryCount)->toBeLessThan(10);

    $listed = collect($response->json('data'))->firstWhere('id', $users->first()->id);

    expect($listed['profile'])->not->toBeNull()
        ->and($listed['role']['slug'])->toBe($role->slug);
});

it('renders a null role for a user who has none', function () {
    actingAsOwner();
    $user = User::factory()->create(['role_id' => null]);

    $this->getJson("/api/v1/user/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.role', null);
});

it('formats created_at with minutes rather than the month', function () {
    actingAsOwner();
    $user = User::factory()->create(['created_at' => '2026-03-09 13:04:05']);

    $this->getJson("/api/v1/user/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.created_at', '2026-03-09 01:04:05 pm');
});

it('returns a 404 envelope for a user that does not exist', function () {
    actingAsOwner();

    $this->getJson('/api/v1/user/999999')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

// --- B5: the created_at date-range filter the users page ships -------------

it('accepts the created_at range filter the datatable sends', function () {
    actingAsOwner();
    User::factory()->create(['created_at' => '2020-06-01 09:00:00']);
    User::factory()->create(['created_at' => '2026-06-01 09:00:00']);

    $this->getJson('/api/v1/users?filter[created_at_from]=2026-01-01')
        ->assertOk();

    $this->getJson('/api/v1/users?filter[created_at_from]=2026-01-01&filter[created_at_to]=2026-12-31')
        ->assertOk();
});

it('narrows the result set by the created_at range', function () {
    actingAsOwner(['created_at' => '2026-06-01 09:00:00']);
    User::factory()->create(['created_at' => '2020-06-01 09:00:00']);

    $this->getJson('/api/v1/users?filter[created_at_from]=2030-01-01')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('still rejects a filter that is not allowed', function () {
    actingAsOwner();

    $this->getJson('/api/v1/users?filter[not_a_filter]=x')->assertStatus(400);
});

// --- B4: status validation -------------------------------------------------

it('accepts every account status', function (string $status) {
    actingAsOwner();
    $user = User::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}/status", ['status' => $status])
        ->assertOk()
        ->assertJsonPath('data.status', $status);

    expect($user->fresh()->status)->toBe(AccountStatusEnum::from($status));
})->with(['active', 'inactive', 'suspended', 'blocked']);

it('rejects an unknown status with a 422 rather than a 500', function () {
    actingAsOwner();
    $user = User::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}/status", ['status' => 'garbage'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('rejects a missing status', function () {
    actingAsOwner();
    $user = User::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}/status", [])->assertStatus(422);
});

// --- B3: PATCH /user/{user} ------------------------------------------------

it('updates a user and recomputes the derived name', function () {
    actingAsOwner();
    $user = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $user->id,
        'firstname' => 'Ana',
        'lastname' => 'Reyes',
    ]);

    $this->patchJson("/api/v1/user/{$user->id}", ['lastname' => 'Santos'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Santos Ana');

    expect($user->fresh()->profile->lastname)->toBe('Santos')
        ->and($user->fresh()->profile->firstname)->toBe('Ana');
});

it('updates the profile birthdate with the basic user details', function () {
    actingAsOwner();
    $user = User::factory()->create();
    UserProfile::factory()->create(['user_id' => $user->id]);

    $this->patchJson("/api/v1/user/{$user->id}", [
        'firstname' => 'Ana',
        'lastname' => 'Santos',
        'birthdate' => '1994-07-12',
    ])
        ->assertOk()
        ->assertJsonPath('data.profile.firstname', 'Ana')
        ->assertJsonPath('data.profile.lastname', 'Santos')
        ->assertJsonPath('data.profile.birthdate', '1994-07-12');
});

it('resets a selected user password', function () {
    actingAsOwner();
    $user = User::factory()->create(['password' => 'OldPassw0rd!']);

    $this->patchJson("/api/v1/user/{$user->id}/password", [
        'new_password' => 'NewPassw0rd!',
        'new_password_confirmation' => 'NewPassw0rd!',
    ])->assertOk();

    expect(Hash::check('NewPassw0rd!', $user->fresh()->password))->toBeTrue();
});

it('validates password reset confirmation', function () {
    actingAsOwner();
    $user = User::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}/password", [
        'new_password' => 'NewPassw0rd!',
        'new_password_confirmation' => 'different',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('new_password');
});

it('guards password and photo changes with users update permission', function () {
    actingAsUserWith(['users.view']);
    $user = User::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}/password", [
        'new_password' => 'NewPassw0rd!',
        'new_password_confirmation' => 'NewPassw0rd!',
    ])->assertForbidden();

    $this->postJson("/api/v1/user/{$user->id}/photo", [
        'photo' => UploadedFile::fake()->image('avatar.jpg'),
    ])->assertForbidden();

    $this->deleteJson("/api/v1/user/{$user->id}/photo")->assertForbidden();
});

it('assigns and clears a role through the update endpoint', function () {
    actingAsOwner();
    $user = User::factory()->create();
    $role = Role::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}", ['role_id' => $role->id])->assertOk();
    expect($user->fresh()->role_id)->toBe($role->id);

    $this->patchJson("/api/v1/user/{$user->id}", ['role_id' => null])->assertOk();
    expect($user->fresh()->role_id)->toBeNull();
});

it('lets a user keep its own email', function () {
    actingAsOwner();
    $user = User::factory()->create(['email' => 'keep@example.test']);

    $this->patchJson("/api/v1/user/{$user->id}", ['email' => 'keep@example.test'])
        ->assertOk();
});

it('rejects an email already taken by another user', function () {
    actingAsOwner();
    User::factory()->create(['email' => 'taken@example.test']);
    $user = User::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}", ['email' => 'taken@example.test'])
        ->assertStatus(422);
});

it('rejects a role that does not exist', function () {
    actingAsOwner();
    $user = User::factory()->create();

    $this->patchJson("/api/v1/user/{$user->id}", ['role_id' => 999999])
        ->assertStatus(422);
});

it('does not expose a delete endpoint', function () {
    actingAsOwner();
    $user = User::factory()->create();

    $this->deleteJson("/api/v1/user/{$user->id}")->assertStatus(405);
});

// --- B6: admin-side creation ----------------------------------------------

it('creates a user with the configured default password and a role', function () {
    actingAsOwner();
    $role = Role::factory()->create();

    $this->postJson('/api/v1/user', [
        'store_id' => testStore()->id,
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'role_id' => $role->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Cruz Bea')
        ->assertJsonPath('data.role.id', $role->id);

    $created = User::where('email', 'bea@example.test')->first();

    expect(Hash::check(config('users.default_password'), $created->password))->toBeTrue();
});

it('rejects a role that does not exist on create', function () {
    actingAsOwner();

    $this->postJson('/api/v1/user', [
        'store_id' => testStore()->id,
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea2@example.test',
        'role_id' => 999999,
    ])->assertStatus(422);
});
