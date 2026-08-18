<?php

use App\Models\DiscountType;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemRoleSeeder;
use Database\Seeders\UnitSeeder;

it('seeds the catalogue as 19 modules and 73 permissions', function () {
    $this->seed(PermissionSeeder::class);

    expect(PermissionGroup::count())->toBe(19)
        ->and(Permission::count())->toBe(73);
});

it('names every permission module.action', function () {
    $this->seed(PermissionSeeder::class);

    expect(Permission::where('name', 'not like', '%.%')->count())->toBe(0);
});

/**
 * SystemRoleSeeder reads what PermissionSeeder writes. Run out of order it
 * syncs every role to zero permissions and raises no error, so the count is
 * the only thing that catches it.
 */
it('gives every system role a non-empty permission set', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $roles = Role::withCount('permissions')->get();

    expect($roles)->toHaveCount(3);

    foreach ($roles as $role) {
        expect($role->permissions_count)->toBeGreaterThan(0, "role {$role->slug} seeded with no permissions");
        expect($role->is_system)->toBeTrue();
    }
});

/**
 * `'*'` means every *store-level* permission, not the whole catalogue.
 *
 * `owner` and `superadmin` are seeded into every store, so a wildcard that
 * swept up PLATFORM_MODULES would hand every paying customer the ability to
 * manage every other customer's store — silently, on the next `db:seed`, with
 * no code change anywhere.
 */
it('grants the owner and superadmin roles every store-level permission', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $platform = Permission::whereHas(
        'permissionGroup',
        fn ($group) => $group->whereIn('name', PermissionSeeder::PLATFORM_MODULES),
    )->count();

    $storeLevel = Permission::count() - $platform;

    expect($platform)->toBeGreaterThan(0, 'platform modules seeded nothing — this test would pass vacuously');

    $owner = Role::where('slug', 'owner')->withCount('permissions')->first();
    expect($owner->permissions_count)->toBe($storeLevel, 'owner holds the wrong permission count');

    // superadmin is the operator, so it holds the platform modules too — that
    // difference is the entire reason both roles exist now that they are
    // global rather than copied into every store.
    //
    // Derived rather than hardcoded, and narrowed from the other end: the
    // operator has no store, so STORE_ONLY_MODULES is withheld from them the
    // way PLATFORM_MODULES is withheld from the owner.
    $storeOnly = Permission::whereHas(
        'permissionGroup',
        fn ($group) => $group->whereIn('name', PermissionSeeder::STORE_ONLY_MODULES),
    )->count();

    expect($storeOnly)->toBeGreaterThan(0, 'store-only modules seeded nothing — this test would pass vacuously');

    $superadmin = Role::where('slug', 'superadmin')->withCount('permissions')->first();
    expect($superadmin->permissions_count)->toBe(Permission::count() - $storeOnly);
});

/**
 * Named explicitly rather than derived from STORE_ONLY_MODULES, for the reason
 * `ActivityLogTest` names `activity-logs.view`: a check derived from the
 * constant stops *looking* when an entry is removed from it, instead of
 * starting to fail. The operator regaining a store-settings screen that 404s
 * should break a test.
 */
it('keeps store settings off the platform operator', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $held = Role::where('slug', 'superadmin')
        ->with('permissions')
        ->first()
        ->permissions
        ->pluck('name');

    expect($held)->not->toContain('settings.view')
        ->and($held)->not->toContain('settings.update')
        // The operator keeps everything else that is platform-level.
        ->and($held)->toContain('stores.view')
        ->and($held)->toContain('activity-logs.view');
});

/**
 * `tindera` lost `branches.view` when the till moved to `/branches/dropdown`,
 * which is narrowed to the caller and carries no permission. Restoring the
 * grant would look harmless — it is not, it is a listing of every branch in
 * the store she is not assigned to.
 */
it('keeps the branch listing away from the tindera', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $held = Role::where('slug', 'tindera')
        ->with('permissions')
        ->first()
        ->permissions
        ->pluck('name');

    expect($held)->not->toContain('branches.view')
        ->and($held)->toHaveCount(16);
});

it('keeps platform permissions off every store-level role', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    // Derived from PLATFORM_MODULES rather than hardcoded, so a module added
    // there is covered the moment it is added. The previous hardcoded
    // `stores.*` list would have passed unchanged while a new platform module
    // leaked to every owner.
    $platform = Permission::query()
        ->where(function ($query) {
            foreach (PermissionSeeder::PLATFORM_MODULES as $module) {
                $query->orWhere('name', 'like', $module.'.%');
            }
        })
        ->pluck('name');

    expect($platform)->not->toBeEmpty();

    // superadmin is excluded on purpose: it is the operator role and holding
    // the platform modules is its job.
    foreach (['owner', 'tindera'] as $slug) {
        $names = Role::where('slug', $slug)->with('permissions')->first()->permissions->pluck('name');

        foreach ($platform as $permission) {
            expect($names)->not->toContain($permission, "role {$slug} was granted {$permission}");
        }
    }
});

/**
 * The seeded test user is useless without a role: `can:` guards every route,
 * and a null role_id resolves to zero permissions.
 */
/**
 * The seeded platform operator.
 *
 * Belongs to no store — `DatabaseSeeder` no longer creates a development one,
 * because nothing is seeded per store any more. `stores.*` is what this
 * account is for, and it is held through the global `superadmin` role.
 */
function seededDevelopmentUser(): ?User
{
    return User::with('role.permissions')->where('email', 'test@example.com')->first();
}

it('assigns the superadmin role to the seeded test user', function () {
    $this->seed();

    $user = seededDevelopmentUser();

    expect($user)->not->toBeNull()
        ->and($user->store)->toBeNull()
        ->and($user->role?->slug)->toBe('superadmin')
        ->and($user->hasPermissionTo('sales.void'))->toBeTrue()
        ->and($user->hasPermissionTo('products.cost'))->toBeTrue()
        ->and($user->hasPermissionTo('stores.view'))->toBeTrue()
        ->and($user->hasPermissionTo('stores.create'))->toBeTrue()
        ->and($user->hasPermissionTo('stores.update'))->toBeTrue()
        ->and($user->hasPermissionTo('stores.delete'))->toBeTrue();
});

it('keeps store management permissions off the owner role', function () {
    $this->seed();

    $permissions = Role::where('slug', 'owner')
        ->with('permissions')
        ->firstOrFail()
        ->permissions
        ->pluck('name');

    expect($permissions)
        ->not->toContain('stores.view')
        ->not->toContain('stores.create')
        ->not->toContain('stores.update')
        ->not->toContain('stores.delete');
});

it('repairs a test user that was seeded before the role existed', function () {
    $this->seed();

    seededDevelopmentUser()->forceFill(['role_id' => null])->save();

    $this->seed();

    expect(seededDevelopmentUser()->role?->slug)->toBe('superadmin');
});

it('prunes catalogue rows that fall outside the taxonomy', function () {
    $group = PermissionGroup::create(['name' => 'legacy-group', 'description' => 'x', 'status' => 'active']);
    Permission::create([
        'permission_group_id' => $group->id,
        'name' => 'manage-branches',
        'description' => 'legacy flat name',
        'status' => 'active',
    ]);

    $this->seed(PermissionSeeder::class);

    expect(PermissionGroup::where('name', 'legacy-group')->exists())->toBeFalse()
        ->and(Permission::where('name', 'manage-branches')->exists())->toBeFalse()
        ->and(PermissionGroup::count())->toBe(19)
        ->and(Permission::count())->toBe(73);
});

it('is idempotent across repeated runs', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);
    $this->seed(UnitSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);
    $this->seed(UnitSeeder::class);

    expect(PermissionGroup::count())->toBe(19)
        ->and(Permission::count())->toBe(73)
        ->and(Role::count())->toBe(3)
        ->and(Unit::count())->toBe(10);
});

/**
 * The whole DatabaseSeeder chain, twice. Once always passes — the second run
 * is what catches a non-idempotent statement anywhere in the chain, and
 * seeders are not transactional as a unit, so a late failure leaves earlier
 * work committed.
 */
it('runs the whole seeder chain twice without failing', function () {
    $this->seed();
    $this->seed();

    expect(PermissionGroup::count())->toBe(19)
        ->and(Permission::count())->toBe(73)
        ->and(Unit::count())->toBe(10)
        // Both are global and in the chain now, so a second run must not
        // duplicate them either.
        ->and(ExpenseCategory::count())->toBe(12)
        ->and(DiscountType::count())->toBe(6)
        ->and(seededDevelopmentUser())->not->toBeNull();
});

it('names the POS system roles', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    expect(Role::pluck('slug')->sort()->values()->all())
        ->toBe(['owner', 'superadmin', 'tindera']);
});

/**
 * The tindera must not see the markup, and must not be able to reverse a sale
 * on her own — these two exclusions are the whole point of the split.
 */
it('withholds cost visibility and voids from the tindera', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $tindera = Role::where('slug', 'tindera')->with('permissions')->first();
    $names = $tindera->permissions->pluck('name');

    expect($names)->not->toContain('products.cost')
        ->and($names)->not->toContain('sales.void')
        ->and($names)->not->toContain('sales.refund')
        ->and($names)->not->toContain('sales.return')
        ->and($names)->not->toContain('sales.override-price')
        ->and($names)->not->toContain('credit.writeoff')
        ->and($names)->not->toContain('branches.view-all')
        ->and($names)->not->toContain('shifts.view-all')
        ->and($names)->not->toContain('inventory.personal-use')
        // Recording an expense is routine counter work; approving it is not.
        ->and($names)->not->toContain('expenses.approve')
        ->and($names)->toContain('sales.create')
        ->and($names)->toContain('sales.suspend')
        ->and($names)->not->toContain('credit.charge')
        ->and($names)->toContain('credit.collect')
        ->and($names)->toContain('expenses.create')
        ->and($names)->toContain('expenses.view');
});

/**
 * Store settings are the business, not a branch of it. `owner` and
 * `superadmin` sync `'*'` and pick the new module up on their own; the other
 * two enumerate, so the split holds only as long as nobody adds these to their
 * lists by reflex.
 */
it('keeps store settings away from the tindera', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $held = fn (string $slug) => Role::where('slug', $slug)
        ->with('permissions')
        ->first()
        ->permissions
        ->pluck('name');

    expect($held('tindera'))->not->toContain('settings.view')
        ->and($held('tindera'))->not->toContain('settings.update')
        ->and($held('owner'))->toContain('settings.view')
        ->and($held('owner'))->toContain('settings.update');
});
