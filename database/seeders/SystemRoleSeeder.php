<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemRoleSeeder extends Seeder
{
    /**
     * Everything except `PermissionSeeder::STORE_ONLY_MODULES`.
     *
     * Not quite the whole catalogue any more: the operator has no store, so
     * the permissions that only make sense when you own one are withheld.
     */
    private const EVERYTHING = '**';

    /** Everything except `PermissionSeeder::PLATFORM_MODULES`. */
    private const STORE_LEVEL = '*';

    /**
     * Must run after PermissionSeeder. If it doesn't, `$allPermissions` is
     * empty and every role syncs to zero permissions without raising an error.
     *
     * These slugs are also the target of the 2026_08_05_100000 rename
     * migration (admin → owner, agent → tindera). Roles are never pruned
     * here: `users.role_id` is `nullOnDelete()`, so deleting one silently
     * unassigns every user holding it.
     *
     * @var array<string, array{string, string|list<string>}>
     */
    private const TEMPLATES = [
        // The platform operator: `stores.*` and every other platform module,
        // but not `settings.*` — they belong to no store, so their own store
        // settings are a screen that 404s. The only role that can manage other
        // people's stores, and assigned to one seeded account, never a customer.
        'superadmin' => ['Super Admin', self::EVERYTHING],

        // The person who owns one sari-sari. Everything inside a store, and
        // deliberately nothing across stores.
        'owner' => ['Owner', self::STORE_LEVEL],

        // Runs a branch day to day: full stock and sales control,
        // but no staff or role administration, no cross-branch visibility, and
        // cannot forgive debt.
        // 'branch-supervisor' => ['Branch Supervisor', [
        //     'branches.update', 'branches.view',
        //     'products.create', 'products.update', 'products.delete', 'products.view', 'products.cost',
        //     'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count',
        //     'sales.create', 'sales.view', 'sales.void', 'sales.refund', 'sales.return',
        //     'sales.suspend', 'sales.discount', 'sales.override-price',
        //     'customers.create', 'customers.update', 'customers.delete', 'customers.view',
        //     'credit.view', 'credit.collect',
        //     'shifts.view', 'shifts.open', 'shifts.close', 'shifts.view-all',
        //     'suppliers.create', 'suppliers.update', 'suppliers.delete', 'suppliers.view',
        //     'expenses.create', 'expenses.update', 'expenses.view',
        //     'reports.view', 'reports.export',
        // ]],

        // The cashier at the counter. Deliberately excluded: products.cost
        // (must not see the markup), sales.void / sales.refund / sales.return
        // / sales.override-price (owner approval), shifts.view-all,
        // credit.writeoff, expenses.approve, and inventory.personal-use.
        //
        // She *can* record an expense (bumili ng yelo mula sa kahon) and sell
        // load — both are routine counter work — but the owner approves the
        // expense afterwards, which is what makes a fabricated one visible.
        'tindera' => ['Tindera', [
            'products.view',
            'inventory.view',
            'sales.create', 'sales.view', 'sales.suspend', 'sales.discount',
            'customers.create', 'customers.update', 'customers.view',
            'credit.view', 'credit.collect',
            'shifts.view', 'shifts.open', 'shifts.close',
            'expenses.create', 'expenses.view',
        ]],
    ];

    /**
     * Three global roles, seeded once.
     *
     * The `owner` / `superadmin` split is now the whole point of having both.
     * When roles were copied into every store, neither could hold `stores.*` —
     * a wildcard that swept up platform permissions would have made every
     * paying customer an operator. Roles are global now, so the split moves to
     * where it belongs:
     *
     * - **`owner`** — every *store-level* permission. Runs one sari-sari.
     * - **`superadmin`** — the whole catalogue, platform modules included. The
     *   operator account, and the only role that can reach `/stores`.
     *
     * `PermissionSeeder::PLATFORM_MODULES` is what separates them, so adding a
     * platform module still stays off `owner` automatically.
     */
    public function run(): void
    {
        $allPermissions = Permission::pluck('id', 'name');

        $storeLevel = $allPermissions->reject(
            fn (int $id, string $name) => in_array(
                Str::before($name, '.'),
                PermissionSeeder::PLATFORM_MODULES,
                strict: true,
            ),
        );

        // The operator's set. `owner` is narrowed from the other end, so the
        // two rejections are deliberately not combined — a permission may be
        // withheld from one role, the other, both, or neither.
        $everything = $allPermissions->reject(
            fn (int $id, string $name) => in_array(
                Str::before($name, '.'),
                PermissionSeeder::STORE_ONLY_MODULES,
                strict: true,
            ),
        );

        foreach (self::TEMPLATES as $slug => [$name, $permissions]) {
            // Keyed on slug: roles are global, so this is the one row.
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true, 'status' => 'active'],
            );

            $ids = match ($permissions) {
                self::EVERYTHING => $everything->values()->all(),
                self::STORE_LEVEL => $storeLevel->values()->all(),
                default => $allPermissions->only($permissions)->values()->all(),
            };

            $role->permissions()->sync($ids);
        }
    }
}
