<?php

use App\Models\Permission;
use App\Models\Role;

beforeEach(function () {
    actingAsOwner();
});

it('lists roles with a permission count', function () {
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::factory()->count(3)->create()->pluck('id'));
    Role::factory()->count(2)->create();

    $response = $this->getJson('/api/v1/roles');

    // 3 created here, plus the acting owner's own role.
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(4, 'data');

    $listed = collect($response->json('data'))->firstWhere('id', $role->id);

    expect($listed['permissions_count'])->toBe(3);
});

it('shows a single role with its permissions', function () {
    $role = Role::factory()->create();
    $permissions = Permission::factory()->count(2)->create();
    $role->permissions()->sync($permissions->pluck('id'));

    $response = $this->getJson("/api/v1/role/{$role->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $role->id)
        ->assertJsonPath('data.slug', $role->slug)
        ->assertJsonCount(2, 'data.permissions');
});

it('returns a 404 envelope for a role that does not exist', function () {
    $this->getJson('/api/v1/role/999999')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('updates a role name and re-syncs its permissions', function () {
    $role = Role::factory()->create();
    $original = Permission::factory()->count(3)->create();
    $role->permissions()->sync($original->pluck('id'));

    alsoGrantActor($original->pluck('id'));

    $keep = $original->first()->id;

    $response = $this->patchJson("/api/v1/role/{$role->id}", [
        'name' => 'Renamed Role',
        'permissions' => [$keep],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Renamed Role')
        ->assertJsonPath('data.permissions_count', 1);

    expect($role->fresh()->permissions->pluck('id')->all())->toBe([$keep]);
});

it('leaves permissions untouched when the update omits them', function () {
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::factory()->count(2)->create()->pluck('id'));

    $this->patchJson("/api/v1/role/{$role->id}", ['name' => 'Just A Rename'])
        ->assertOk()
        ->assertJsonPath('data.permissions_count', 2);
});

it('refuses to rename a system role', function () {
    $role = Role::factory()->system()->create(['name' => 'Admin']);

    $this->patchJson("/api/v1/role/{$role->id}", ['name' => 'Hacked'])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect($role->fresh()->name)->toBe('Admin');
});

/**
 * SystemRoleSeeder matches on slug, so a renamed system role would be orphaned
 * and duplicated on the next seed.
 */
it('refuses to change a system role slug', function () {
    $role = Role::factory()->system()->create(['slug' => 'owner']);

    $this->patchJson("/api/v1/role/{$role->id}", ['slug' => 'not-owner'])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect($role->fresh()->slug)->toBe('owner');
});

/**
 * The whole point of the change: every seeded role is is_system, so locking
 * them entirely left no role editable by anyone.
 */
it('allows re-syncing a system role permissions', function () {
    $role = Role::factory()->system()->create();
    $original = Permission::factory()->count(3)->create();
    $role->permissions()->sync($original->pluck('id'));

    alsoGrantActor($original->pluck('id'));

    $keep = $original->first()->id;

    $this->patchJson("/api/v1/role/{$role->id}", ['permissions' => [$keep]])
        ->assertOk()
        ->assertJsonPath('data.permissions_count', 1);

    expect($role->fresh()->permissions->pluck('id')->all())->toBe([$keep]);
});

it('still refuses a permission change from a user without roles.update', function () {
    $role = Role::factory()->system()->create();

    actingAsUserWith(['roles.view']);

    $this->patchJson("/api/v1/role/{$role->id}", ['permissions' => []])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('requires authentication', function () {
    app()['auth']->forgetGuards();

    $this->getJson('/api/v1/roles')->assertUnauthorized();
});

/**
 * The dropdown is the one role read that carries no permission, and it exists
 * because a staff form names a role without administering one: Users
 * create/edit and the branch "create and assign a tindera" dialog all need the
 * list, and an owner may hold `users.create` without `roles.view`.
 *
 * Both halves are asserted. If only the first were, moving the guard back onto
 * the dropdown would still pass for a holder of `roles.view`.
 */
it('serves the role dropdown without any roles permission', function () {
    Role::factory()->create(['name' => 'Zebra', 'slug' => 'zebra']);
    Role::factory()->create(['name' => 'Alpha', 'slug' => 'alpha']);

    actingAsUserWith(['users.create']);

    $response = $this->getJson('/api/v1/roles/dropdown')->assertOk();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Alpha', 'Zebra')
        ->and($names->search('Alpha'))->toBeLessThan($names->search('Zebra'))
        ->and($response->json('data.0'))->toHaveKeys(['id', 'name', 'slug', 'status']);
});

it('keeps the roles screen gated for a holder of the dropdown', function () {
    $role = Role::factory()->create();

    actingAsUserWith(['users.create']);

    $this->getJson('/api/v1/roles')->assertForbidden();
    $this->getJson("/api/v1/role/{$role->id}")->assertForbidden();
    $this->patchJson("/api/v1/role/{$role->id}", ['name' => 'Renamed'])->assertForbidden();
});

/**
 * The payload is a picker's, not the screen's. `permissions` would hand the
 * whole grant list to anyone with a token; `permissions_count` would put a
 * subquery on a request that runs on three screens.
 */
it('omits permissions and their count from the dropdown', function () {
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::factory()->count(3)->create()->pluck('id'));

    actingAsUserWith(['users.create']);

    $row = collect($this->getJson('/api/v1/roles/dropdown')->assertOk()->json('data'))
        ->firstWhere('id', $role->id);

    expect($row)->not->toBeNull()
        ->and(array_key_exists('permissions', $row))->toBeFalse()
        ->and(array_key_exists('permissions_count', $row))->toBeFalse();
});

/**
 * Roles are seeded, fixed, and global — `roles.create` and `roles.delete` are
 * not in the catalogue, so there is nothing to guard these with. Asserting the
 * route is absent is what stops it being quietly reintroduced.
 */
it('exposes no create or delete route for roles', function () {
    actingAsOwner();

    $this->postJson('/api/v1/role', ['name' => 'Front Desk Lead'])->assertNotFound();
    $this->deleteJson('/api/v1/role/1')->assertStatus(405);
});

it('refuses to rename a system role but allows its permissions to change', function () {
    actingAsOwner();

    $role = Role::factory()->create(['is_system' => true, 'slug' => 'tindera-test']);
    $permission = Permission::factory()->create();
    alsoGrantActor([$permission->id]);

    $this->patchJson("/api/v1/role/{$role->id}", ['name' => 'Renamed'])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    $this->patchJson("/api/v1/role/{$role->id}", ['permissions' => [$permission->id]])
        ->assertOk()
        ->assertJsonPath('data.permissions_count', 1);
});
