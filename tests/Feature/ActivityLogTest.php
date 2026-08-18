<?php

use App\Enums\ActivityActionEnum;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * @return array{Product, ProductUnit}
 */
function auditableProduct(): array
{
    $unit = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);

    $product = Product::factory()->create([
        'store_id' => testStore()->id,
        'name' => 'Kopiko Blanca',
        'base_unit_id' => $unit->id,
    ]);

    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 8,
    ]);

    return [$product, $productUnit];
}

it('records a model change with its before and after values', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    ActivityLog::query()->delete();

    $product->update(['name' => 'Kopiko Brown']);

    $entry = ActivityLog::latest('id')->first();

    expect(ActivityLog::count())->toBe(1)
        ->and($entry->action)->toBe('product.updated')
        ->and($entry->old_values)->toBe(['name' => 'Kopiko Blanca'])
        ->and($entry->new_values)->toBe(['name' => 'Kopiko Brown'])
        ->and($entry->store_id)->toBe(testStore()->id);
});

/**
 * Without the guard, every `touch()` and every save that moved only unwatched
 * columns writes an entry with two empty objects.
 */
it('records nothing when no watched attribute changed', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    ActivityLog::query()->delete();

    $product->touch();

    expect(ActivityLog::count())->toBe(0);
});

/**
 * `old_values` and `new_values` are raw JSON columns — `$hidden` does not reach
 * them. `password` is in `User::$fillable`, so a diff built from that instead of
 * from the allow-list would persist the hash.
 */
it('never records a password or an approval pin', function () {
    actingAsOwner();

    ActivityLog::query()->delete();

    $user = User::factory()->create(['name' => 'Tindera One']);
    $user->update(['name' => 'Tindera Two', 'password' => bcrypt('secret-value')]);

    $recorded = ActivityLog::all()
        ->flatMap(fn ($row) => array_merge(
            array_keys($row->old_values ?? []),
            array_keys($row->new_values ?? []),
        ))
        ->unique();

    expect($recorded)->not->toContain('password')
        ->and($recorded)->not->toContain('approval_pin')
        ->and($recorded)->toContain('name');
});

/**
 * Only `products`, `branches` and `stores` carry `store_id`. A blanket
 * `$this->store_id` would write null for everything else and quietly make the
 * log's only cross-customer filter useless.
 */
it('resolves the store through a parent when the model has no store_id', function () {
    actingAsOwner();
    [, $productUnit] = auditableProduct();

    ActivityLog::query()->delete();

    $productUnit->update(['selling_price' => 10]);

    expect(ActivityLog::latest('id')->first()->store_id)->toBe(testStore()->id);
});

it('records a branch-owned model against its branch store', function () {
    actingAsOwner();

    $branch = Branch::factory()->create(['store_id' => testStore()->id]);

    ActivityLog::query()->delete();
    $branch->update(['name' => 'Renamed Branch']);

    expect(ActivityLog::latest('id')->first()->store_id)->toBe(testStore()->id);
});

/**
 * The trait fires in seeders, queued jobs and artisan commands, where there is
 * no causer. Writing those as "system" would fill the table on every
 * `migrate:fresh --seed`.
 */
it('records nothing when there is no authenticated user', function () {
    [$product] = auditableProduct();

    auth()->forgetGuards();
    ActivityLog::query()->delete();

    $product->update(['name' => 'Unattributed']);

    expect(ActivityLog::count())->toBe(0);
});

/**
 * The logger runs inside the caller's transaction. If it throws, it rolls back
 * the business operation it was only supposed to describe.
 */
it('never lets a logging failure break the thing it records', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    // A value that cannot be JSON-encoded, forced through the write path.
    app(ActivityLogger::class)->record(
        ActivityActionEnum::SALE_VOIDED,
        subject: $product,
        new: ['broken' => fopen('php://memory', 'r')],
    );

    // Reaching here at all is the assertion: no exception escaped.
    expect(true)->toBeTrue();
});

it('leaves no entry behind when the surrounding transaction rolls back', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    ActivityLog::query()->delete();

    try {
        DB::transaction(function () use ($product) {
            $product->update(['name' => 'Rolled Back']);

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(ActivityLog::count())->toBe(0)
        ->and($product->fresh()->name)->toBe('Kopiko Blanca');
});

/**
 * A bare static toggle left set by an exception silently disables logging for
 * the rest of the request — the one failure an audit trail cannot afford.
 */
it('restores model logging after an exception inside the suppression closure', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    $logger = app(ActivityLogger::class);

    try {
        $logger->withoutModelLogging(function () {
            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
        // expected
    }

    ActivityLog::query()->delete();
    $product->update(['name' => 'Still Logged']);

    expect(ActivityLog::count())->toBe(1);
});

it('suppresses the mechanical row while the closure runs', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    ActivityLog::query()->delete();

    app(ActivityLogger::class)->withoutModelLogging(
        fn () => $product->update(['name' => 'Quietly Changed']),
    );

    expect(ActivityLog::count())->toBe(0)
        ->and($product->fresh()->name)->toBe('Quietly Changed');
});

/**
 * The specific guard, not the derived one.
 *
 * `CatalogueSeedTest` checks every module in PLATFORM_MODULES generically —
 * which means dropping `activity-logs` from that constant would make it stop
 * *looking* rather than start failing. This names the permission, so forgetting
 * the one line that keeps the platform-wide trail off every store owner is red
 * rather than silent.
 */
it('keeps the activity log off the seeded owner role', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $owner = Role::where('slug', 'owner')->with('permissions')->first();
    $superadmin = Role::where('slug', 'superadmin')->with('permissions')->first();

    expect($owner->permissions->pluck('name'))->not->toContain('activity-logs.view')
        ->and($superadmin->permissions->pluck('name'))->toContain('activity-logs.view');
});

it('refuses the listing to anyone without the platform permission', function () {
    // Not actingAsOwner(): that helper syncs the whole catalogue onto its role,
    // so it is a superuser. The real seeded `owner` is excluded by
    // PLATFORM_MODULES, which `CatalogueSeedTest` asserts separately.
    actingAsUserWith(['reports.view', 'sales.view']);

    $this->getJson('/api/v1/activity-logs')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('serves the listing to a holder of activity-logs.view', function () {
    actingAsUserWith(['activity-logs.view']);

    $this->getJson('/api/v1/activity-logs')
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data', 'links', 'meta']);
});

it('filters the listing by store, action and date', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    ActivityLog::query()->delete();
    $product->update(['name' => 'Filtered']);

    $storeId = testStore()->id;

    expect($this->getJson("/api/v1/activity-logs?filter[store_id]={$storeId}")->json('data'))
        ->toHaveCount(1);

    expect($this->getJson('/api/v1/activity-logs?filter[store_id]=999999')->json('data'))
        ->toHaveCount(0);

    expect($this->getJson('/api/v1/activity-logs?filter[action]=product.updated')->json('data'))
        ->toHaveCount(1);

    expect($this->getJson('/api/v1/activity-logs?filter[occurred_from]='.now()->addDay()->toDateString())->json('data'))
        ->toHaveCount(0);
});

it('prunes only entries older than the given window', function () {
    actingAsOwner();
    [$product] = auditableProduct();

    ActivityLog::query()->delete();

    $product->update(['name' => 'Recent']);
    $old = ActivityLog::latest('id')->first();
    $old->forceFill(['created_at' => now()->subDays(400)])->save();

    $product->update(['name' => 'Newer']);

    expect(ActivityLog::count())->toBe(2);

    $this->artisan('activity:prune', ['--days' => 365])->assertSuccessful();

    expect(ActivityLog::count())->toBe(1)
        ->and(ActivityLog::first()->new_values)->toBe(['name' => 'Newer']);
});

it('refuses to prune with a window that would delete everything', function () {
    $this->artisan('activity:prune', ['--days' => 0])->assertFailed();
});
