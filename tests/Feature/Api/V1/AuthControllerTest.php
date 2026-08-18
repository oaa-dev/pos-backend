<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemRoleSeeder;
use Laravel\Sanctum\Sanctum;

// Registration attaches the registrant to the global `owner` role. Roles are
// no longer seeded per store, so they have to exist before `/auth/register` is
// called at all — provisioning refuses outright rather than handing back an
// owner with no permissions.
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);
});

it('logs in a user with correct credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'profile',
                    'store',
                    'status',
                    'created_at',
                ],
                'token',
            ],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

// --- B1: /auth/me used to 500 by eager-loading a relation that never existed

it('returns the authenticated user with its role permissions', function () {
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::factory()->count(3)->create()->pluck('id'));

    Sanctum::actingAs(User::factory()->create(['role_id' => $role->id]));

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.role.slug', $role->slug)
        ->assertJsonCount(3, 'data.role.permissions');
});

it('returns a user who has no role', function () {
    Sanctum::actingAs(User::factory()->create(['role_id' => null]));

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.role', null);
});

// --- B6: registration used to ignore the submitted password ----------------

/**
 * The registrant, with the permissions they were provisioned with.
 *
 * Users are global — there is no scope to reach across — but the role and its
 * permissions still need naming, because a registrant arriving with `role:
 * null` in a default-deny API is the failure these tests exist to catch.
 */
function registeredUser(string $email): ?User
{
    return User::with('role.permissions')->where('email', $email)->first();
}

it('registers a user with the submitted password', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ]);

    $response->assertCreated()->assertJsonPath('data.user.name', 'Cruz Bea');

    $user = registeredUser('bea@example.test');

    expect(Hash::check('Str0ng!Passw0rd', $user->password))->toBeTrue()
        ->and(Hash::check(config('users.default_password'), $user->password))->toBeFalse();
});

/**
 * Registration opens a store, so it cannot leave the registrant role-less the
 * way it used to — the API is default-deny, and an owner with no role owns a
 * shop they are locked out of. What must survive is the security property the
 * old test guarded: they get *their* store's owner role, never one they named.
 */
it('makes the registrant the owner of the store it opens', function () {
    // The catalogue is shared and must already exist: provisioning draws the
    // new store's roles from it, so an unseeded catalogue yields an owner with
    // zero permissions and no error — the same ordering contract
    // SystemRoleSeeder has always had.
    $this->seed(PermissionSeeder::class);

    $this->postJson('/api/v1/auth/register', [
        'store_name' => 'Aling Nena Store',
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ])->assertCreated();

    $user = registeredUser('bea@example.test');
    $store = Store::where('name', 'Aling Nena Store')->first();

    // Membership is the pivot now — `users` has no `store_id`, and the role
    // is one of the three global ones rather than a copy made per store.
    expect($store)->not->toBeNull()
        ->and($user->store?->id)->toBe($store->id)
        ->and($user->ownsStore())->toBeTrue()
        ->and($user->role?->slug)->toBe('owner')
        ->and($user->hasPermissionTo('products.cost'))->toBeTrue();
});

/**
 * Asserted on the *response*, not the database. This is what the client acts
 * on, and an owner whose role is missing from it lands in an app that is
 * default-deny and hides every screen.
 *
 * It is also the eager-load trap the helper above documents from the other
 * side: roles are tenant-scoped, so resolving the relation outside the store
 * provisioning just opened comes back null with no error anywhere. Move the
 * `load()` in StoreProvisioningService back out of the closure and this
 * goes red.
 */
it('returns the new owner with their role and permissions', function () {
    $this->seed(PermissionSeeder::class);

    $response = $this->postJson('/api/v1/auth/register', [
        'store_name' => 'Aling Nena Store',
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.role.slug', 'owner')
        ->assertJsonPath('data.user.store.name', 'Aling Nena Store');

    expect($response->json('data.user.role.permissions'))->not->toBeEmpty();
});

/**
 * `Store::owner()` reads the `store_users` pivot rather than inferring
 * ownership from a role slug. Registration is the path that writes it, and a
 * missing row is silent: `$store->owner` simply resolves to null and the
 * signup response comes back without a user.
 */
it('records the registrant as the store owner on the pivot', function () {
    $this->seed(PermissionSeeder::class);

    $this->postJson('/api/v1/auth/register', [
        'store_name' => 'Aling Nena Store',
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ])->assertCreated();

    $store = Store::where('name', 'Aling Nena Store')->first();
    $user = registeredUser('bea@example.test');

    $this->assertDatabaseHas('store_users', [
        'store_id' => $store->id,
        'user_id' => $user->id,
        'is_owner' => true,
    ]);

    expect($store->fresh()->owner?->id)->toBe($user->id);
});

it('returns a token the registrant can immediately act with', function () {
    $token = $this->postJson('/api/v1/auth/register', [
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
    ])->assertCreated()->json('data.token');

    expect($token)->toBeString()->not->toBeEmpty();

    // Signing up and then being asked to log in is the failure this guards.
    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'bea@example.test');
});

it('carries the store on login and on me, not only on registration', function () {
    $user = actingAsUserWith(['sales.create']);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.store.id', testStore()->id);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->assertJsonPath('data.user.store.id', testStore()->id);
});

it('ignores a role_id submitted at registration', function () {
    $role = Role::factory()->create(['slug' => 'smuggled']);

    $this->postJson('/api/v1/auth/register', [
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
        'role_id' => $role->id,
    ])->assertCreated();

    // The submitted role belongs to another store entirely; taking it would be
    // a cross-tenant privilege grant, not merely the wrong role.
    expect(registeredUser('bea@example.test')->role_id)->not->toBe($role->id);
});

it('rejects a registration whose password confirmation does not match', function () {
    $this->postJson('/api/v1/auth/register', [
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'something-else',
    ])->assertStatus(422);
});

it('rejects a registration with no password', function () {
    $this->postJson('/api/v1/auth/register', [
        'firstname' => 'Bea',
        'lastname' => 'Cruz',
        'email' => 'bea@example.test',
    ])->assertStatus(422);
});
