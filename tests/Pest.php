<?php

use App\Models\Branch;
use App\Models\CashDrawerSession;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\CreditService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // RefreshDatabase rolls the schema back between tests, so the memo
        // below would otherwise hand out a Store that no longer exists.
        $GLOBALS['__testStore'] = null;
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * The store this test runs inside.
 *
 * There is no ambient tenant any more: `store_id` is whatever the request
 * sends, and listings are narrowed only when a test passes
 * `filter[store_id]`. So this is a convenience — one store to hang an actor
 * and their fixtures on — not a scope. A fixture built without naming a store
 * simply gets its own from the factory, and still shows up in an unfiltered
 * listing.
 */
function testStore(): Store
{
    return $GLOBALS['__testStore'] ??= Store::factory()->create();
}

/**
 * A second store — the other side of an isolation test.
 *
 * Never memoised, so nothing reaches it by accident. Pass its id explicitly:
 * `Product::factory()->create(['store_id' => anotherStore()->id])`.
 */
function anotherStore(): Store
{
    return Store::factory()->create();
}

/**
 * Routes are guarded with `can:`, and Gate resolves abilities from the single
 * role on `users.role_id`. A bare `User::factory()->create()` therefore holds
 * no permissions and is refused everywhere — every authenticated test needs an
 * actor with an explicit role.
 *
 * The actor is attached to `testStore()` through `store_users`, which is the
 * only record of membership now that `users` carries no `store_id`. Without
 * it `$user->store` is null and `GET /store` 404s.
 *
 * @param  list<string>  $abilities  permission names, e.g. ['users.view']
 */
function actingAsUserWith(array $abilities = [], array $attributes = []): User
{
    $role = Role::factory()->create();

    if ($abilities !== []) {
        // One shared catalogue, so firstOrCreate matches across tests rather
        // than colliding on the unique name.
        $group = PermissionGroup::factory()->create();

        $ids = collect($abilities)->map(fn (string $name) => Permission::firstOrCreate(
            ['name' => $name],
            ['permission_group_id' => $group->id, 'description' => $name, 'status' => 'active'],
        )->id);

        $role->permissions()->sync($ids);
    }

    $user = User::factory()->create([...$attributes, 'role_id' => $role->id]);

    testStore()->members()->syncWithoutDetaching([$user->id => ['is_owner' => false]]);

    Sanctum::actingAs($user);

    return $user;
}

/**
 * An actor holding the entire seeded catalogue, owning `testStore()`.
 *
 * Seeds PermissionSeeder but deliberately **not** SystemRoleSeeder: the owner
 * gets an ad-hoc role instead, so tests that count roles see one extra row
 * rather than four.
 */
function actingAsOwner(array $attributes = []): User
{
    test()->seed(PermissionSeeder::class);

    $role = Role::factory()->create(['name' => 'Owner (test)']);
    $role->permissions()->sync(Permission::pluck('id'));

    $user = User::factory()->create([...$attributes, 'role_id' => $role->id]);

    testStore()->members()->syncWithoutDetaching([$user->id => ['is_owner' => true]]);

    Sanctum::actingAs($user);

    return $user;
}

/**
 * Grant the acting user extra permissions after they have been authenticated.
 *
 * `actingAsOwner()` syncs the actor's role to whatever the catalogue held *at
 * that moment*, so permissions a factory creates afterwards are not in it.
 *
 * @param  iterable<int>  $permissionIds
 */
function alsoGrantActor(iterable $permissionIds): void
{
    $user = auth()->user();

    $user->role->permissions()->syncWithoutDetaching($permissionIds);

    // permissionNames() is memoised per instance, and the guard reads it.
    $user->forgetCachedPermissions();
}

/**
 * Create a persisted POS sale before charging a customer. Tests use this
 * helper so they exercise the same invariant as production: no sale means no
 * new utang.
 */
function posCreditCharge(
    Customer $customer,
    string|float|int $amount,
    ?string $dueDate = null,
    ?string $note = null,
): CreditTransaction {
    $branch = $customer->branch_id
        ? Branch::findOrFail($customer->branch_id)
        : Branch::factory()->create(['store_id' => $customer->store_id]);

    if ($customer->branch_id === null) {
        $customer->update(['branch_id' => $branch->id]);
    }

    $session = CashDrawerSession::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => auth()->id(),
    ]);

    $sale = Sale::create([
        'uuid' => (string) Str::uuid(),
        'sale_number' => 'TEST-'.Str::upper(Str::random(12)),
        'branch_id' => $branch->id,
        'cash_drawer_session_id' => $session->id,
        'user_id' => auth()->id(),
        'customer_id' => $customer->id,
        'status' => 'completed',
        'subtotal' => $amount,
        'discount_total' => 0,
        'total' => $amount,
        'amount_tendered' => 0,
        'change_due' => 0,
        'credit_amount' => $amount,
        'sold_at' => now(),
    ]);

    return app(CreditService::class)->charge(
        customer: $customer,
        sale: $sale,
        amount: $amount,
        dueDate: $dueDate,
        note: $note ?? 'POS sale '.$sale->sale_number,
    );
}
