<?php

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;

/**
 * The rename migration runs against an empty roles table under
 * RefreshDatabase, so the path that actually matters — an already-seeded
 * database carrying the pre-POS slugs — has to be reconstructed and the
 * migration re-run by hand.
 */
function runRenameMigration(): void
{
    $migration = require database_path('migrations/2026_08_05_100000_rename_system_roles_for_pos.php');
    $migration->up();
}

it('renames the pre-POS system roles in place', function () {
    $admin = Role::create(['name' => 'Admin', 'slug' => 'admin', 'is_system' => true, 'status' => 'active']);
    $agent = Role::create(['name' => 'Agent', 'slug' => 'agent', 'is_system' => true, 'status' => 'active']);

    runRenameMigration();

    expect($admin->fresh()->slug)->toBe('owner')
        ->and($admin->fresh()->name)->toBe('Owner')
        ->and($agent->fresh()->slug)->toBe('tindera')
        ->and($agent->fresh()->name)->toBe('Tindera');
});

/**
 * `users.role_id` is nullOnDelete(). Pruning instead of renaming would have
 * unassigned every user silently — this is the assertion that would have
 * caught it.
 */
it('preserves user assignments and role permissions across the rename', function () {
    $group = PermissionGroup::create(['name' => 'sales', 'description' => 'x', 'status' => 'active']);
    $permission = Permission::create([
        'permission_group_id' => $group->id,
        'name' => 'sales.create',
        'description' => 'x',
        'status' => 'active',
    ]);

    $admin = Role::create(['name' => 'Admin', 'slug' => 'admin', 'is_system' => true, 'status' => 'active']);
    $admin->permissions()->sync([$permission->id]);

    $user = User::factory()->create(['role_id' => $admin->id]);

    runRenameMigration();

    expect($user->fresh()->role_id)->toBe($admin->id)
        ->and($user->fresh()->role->slug)->toBe('owner')
        ->and($admin->fresh()->permissions)->toHaveCount(1);
});

it('is a no-op on a database that has no pre-POS roles', function () {
    runRenameMigration();

    expect(Role::count())->toBe(0);
});

/**
 * roles.slug is globally unique, so writing into a slug the seeder already
 * created would fail the migration outright.
 */
it('does not collide when the target slug already exists', function () {
    Role::create(['name' => 'Owner', 'slug' => 'owner', 'is_system' => true, 'status' => 'active']);
    $admin = Role::create(['name' => 'Admin', 'slug' => 'admin', 'is_system' => true, 'status' => 'active']);

    runRenameMigration();

    expect($admin->fresh()->slug)->toBe('admin')
        ->and(Role::where('slug', 'owner')->count())->toBe(1);
});
