<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * The permission catalogue, keyed by module.
     *
     * Permission names are `module.action`, so the module key is always the
     * prefix of every permission it owns. The CRUD action list is deliberately
     * sparse — only actions that mean something for the module are seeded — so
     * the role editor can disable inapplicable cells rather than offer
     * permissions that could never be granted.
     *
     * The catalogue is seeded whole, including modules whose tables do not
     * exist yet (products, sales, credit…). Permissions are inert without
     * routes to guard, and seeding the full taxonomy once means this seeder
     * and its test are rewritten once rather than at every phase — the same
     * reasoning that previously kept `organization`/`branches` after their
     * tables were dropped in ce64ab8.
     *
     * Two entries carry the owner/tindera split and should never be granted
     * to a cashier: `products.cost` (exposes markup) and `branches.view-all`
     * (the escape hatch from repository branch scoping).
     *
     * @var array<string, array{string, list<string>, array<string, string>}>
     */
    private const MODULES = [
        'branches' => ['Store branches', ['create', 'update', 'delete', 'view'], [
            'view-all' => 'View data across all branches',
        ]],
        'users' => ['Staff accounts', ['create', 'update', 'delete', 'view'], []],
        'roles' => ['Roles and permissions', ['update', 'view'], []],
        'products' => ['Product catalogue and pricing', ['create', 'update', 'delete', 'view'], [
            'cost' => 'View product cost and margin',
        ]],

        // The three screens that support the product catalogue without being
        // it. They had no permissions of their own and were gated on
        // `products.view` — which a tindera must hold to work the till, so
        // there was nothing an owner could untick to keep her out of them.
        'categories' => ['Product categories', ['create', 'update', 'delete', 'view'], []],
        'units' => ['Selling units', ['create', 'update', 'delete', 'view'], []],
        'discount-types' => ['Discount types', ['create', 'update', 'delete', 'view'], []],

        'inventory' => ['Stock levels and movements', ['view'], [
            'adjust' => 'Adjust stock levels',
            'transfer' => 'Transfer stock between branches',
            'count' => 'Perform physical stock counts',
            'personal-use' => 'Record stock consumed by the owner',
        ]],
        'sales' => ['Point of sale transactions', ['create', 'view'], [
            'void' => 'Void a completed sale',
            'refund' => 'Refund a completed sale',
            'return' => 'Accept a customer return or exchange',
            'suspend' => 'Suspend and resume an in-progress sale',
            'discount' => 'Apply senior, PWD, or manual discounts',
            'override-price' => 'Override an item price at the counter',
        ]],
        'customers' => ['Suki records', ['create', 'update', 'delete', 'view'], []],
        'credit' => ['Utang ledger', ['view'], [
            'collect' => 'Record a payment against a customer balance',
            'writeoff' => 'Write off an outstanding balance',
        ]],
        'shifts' => ['Cash drawer sessions', ['view'], [
            'open' => 'Open a cash drawer session',
            'close' => 'Close a cash drawer session',
            'view-all' => 'View cash drawer sessions belonging to other staff',
        ]],
        'suppliers' => ['Suppliers', ['create', 'update', 'delete', 'view'], []],
        'expenses' => ['Operating expenses', ['create', 'update', 'delete', 'view'], [
            'approve' => 'Approve a recorded expense',
        ]],
        'reports' => ['Sales and inventory reporting', ['view'], [
            'export' => 'Export report data',
        ]],

        // Renaming the business is neither a branch action nor a product one,
        // so it gets its own module. Owner-level by intent: a branch
        // supervisor runs a branch, not the business, and neither enumerated
        // role lists these.
        'settings' => ['Store settings', ['view', 'update'], []],

        // Store-level, so `owner` picks all three up from its wildcard and
        // `tindera` — an explicit list — gets none. No `delete`: a ticket is
        // closed by status, never erased.
        'support' => ['Support tickets', ['create', 'update', 'view'], []],

        // Platform-only, like `stores`. The trail spans every customer, so it
        // is the operator's to read and nobody else's — see PLATFORM_MODULES,
        // which is the single line keeping it off the `owner` wildcard.
        'activity-logs' => ['System activity log', ['view'], []],

        // Managing the *tenants* — every paying customer's store, not one's
        // own. Platform-level, and the only module in this catalogue that
        // crosses the tenant boundary. See PLATFORM_MODULES.
        'stores' => ['Store management', ['create', 'update', 'delete', 'view'], []],
    ];

    /**
     * Modules kept off the `owner` wildcard, for two different reasons.
     *
     * `stores` and `activity-logs` are **cross-tenant**: they reach every
     * customer's data, so handing them to a store owner would make each paying
     * customer an operator.
     *
     * `units`, `discount-types` and `roles` are **global rows every store
     * shares**. Nothing here crosses a tenant boundary by reading — it crosses
     * by *writing*: one owner renaming "sachet", editing a discount rate, or
     * retuning a role changes it for every other customer at once. Different
     * reason, same conclusion. Say so, or someone reasonably concludes they do
     * not belong beside `stores` and moves them back.
     *
     * `SystemRoleSeeder` excludes all of them from the `'*'` wildcard, so
     * `owner` does not pick them up while `superadmin` still does. Without
     * that, adding a module here would grant every store owner cross-tenant
     * access on the next `db:seed` — silently, with no code change.
     *
     * Note this is the only enforcement. `RoleService` has **no** escalation
     * guard: an owner cannot tick these for herself only because the role
     * editor is behind `roles.update`, which she no longer holds.
     *
     * @var list<string>
     */
    public const PLATFORM_MODULES = ['stores', 'activity-logs', 'units', 'discount-types', 'roles'];

    /**
     * Modules kept off the **operator**, the mirror of PLATFORM_MODULES.
     *
     * These only mean something to someone who *has* a store. `/store`
     * (singular) resolves through `store_users` and `superadmin` belongs to no
     * store, so `settings.*` bought them a nav entry that 404s. Excluded from
     * the `'**'` wildcard rather than removed from the catalogue, because
     * `owner` needs them.
     *
     * @var list<string>
     */
    public const STORE_ONLY_MODULES = ['settings'];

    /**
     * Wording for the four CRUD actions; `%s` is filled with the module name.
     */
    private const ACTION_DESCRIPTIONS = [
        'create' => 'Create %s',
        'update' => 'Update %s',
        'delete' => 'Delete %s',
        'view' => 'View %s',
    ];

    public function run(): void
    {
        $seededGroups = [];
        $seededPermissions = [];

        foreach (self::MODULES as $module => [$description, $actions, $specialActions]) {
            $group = PermissionGroup::updateOrCreate(
                ['name' => $module],
                ['description' => $description, 'status' => 'active'],
            );

            $seededGroups[] = $group->id;

            foreach ($actions as $action) {
                $seededPermissions[] = $this->upsertPermission(
                    $group->id,
                    "{$module}.{$action}",
                    sprintf(self::ACTION_DESCRIPTIONS[$action], $module),
                );
            }

            foreach ($specialActions as $action => $actionDescription) {
                $seededPermissions[] = $this->upsertPermission(
                    $group->id,
                    "{$module}.{$action}",
                    $actionDescription,
                );
            }
        }

        $this->prune($seededGroups, $seededPermissions);
    }

    private function upsertPermission(int $groupId, string $name, string $description): int
    {
        return Permission::updateOrCreate(
            ['name' => $name],
            [
                'permission_group_id' => $groupId,
                'description' => $description,
                'status' => 'active',
            ],
        )->id;
    }

    /**
     * Remove catalogue rows that are no longer part of the taxonomy.
     *
     * Groups are pruned as well as permissions: `updateOrCreate` matches on
     * `name`, so renaming a group (branch → branches) creates a new row and
     * strands the old one rather than updating it. Deleting a stale group
     * cascades to its permissions via `permissions.permission_group_id`; the
     * second delete then catches any permission that outlived its group.
     *
     * @param  list<int>  $seededGroups
     * @param  list<int>  $seededPermissions
     */
    private function prune(array $seededGroups, array $seededPermissions): void
    {
        PermissionGroup::whereNotIn('id', $seededGroups)->delete();
        Permission::whereNotIn('id', $seededPermissions)->delete();
    }
}
