<?php

use App\Models\Branch;
use App\Models\CashDrawerSession;
use App\Models\DiscountType;
use App\Models\Sale;
use App\Models\SaleDiscount;
use Database\Seeders\DiscountTypeSeeder;
use Illuminate\Support\Str;

it('seeds the six kinds that used to be enum cases', function () {
    $this->seed(DiscountTypeSeeder::class);

    expect(DiscountType::count())->toBe(6)
        ->and(DiscountType::pluck('slug')->all())
        ->toContain('senior', 'pwd', 'manual', 'promotional', 'employee', 'wholesale');
});

it('seeds senior and pwd as protected statutory rows', function () {
    $this->seed(DiscountTypeSeeder::class);

    // orderBy('id'), because the assertion below is about the seeder's
    // declared order. Unordered, MySQL now returns these in slug order — the
    // composite `(store_id, slug)` unique is the cheapest index for a scoped
    // read, so `pwd` sorts ahead of `senior` and the test fails on nothing.
    $statutory = DiscountType::where('is_system', true)->orderBy('id')->get();

    expect($statutory->pluck('slug')->all())->toBe(['senior', 'pwd'])
        ->and($statutory->every(fn ($type) => $type->requires_identification))->toBeTrue()
        ->and($statutory->every(fn ($type) => (string) $type->percentage === '20.00'))->toBeTrue();
});

it('leaves the other four editable', function () {
    $this->seed(DiscountTypeSeeder::class);

    expect(DiscountType::where('is_system', false)->orderBy('id')->pluck('slug')->all())
        ->toBe(['manual', 'promotional', 'employee', 'wholesale']);
});

/**
 * The seeder is the repair path as well as the setup path: if a statutory rate
 * is edited directly in the database, re-running must put it back.
 */
it('restores a statutory rate that was tampered with', function () {
    $this->seed(DiscountTypeSeeder::class);

    DiscountType::where('slug', 'senior')->update(['percentage' => '15.00']);

    $this->seed(DiscountTypeSeeder::class);

    expect((string) DiscountType::where('slug', 'senior')->first()->percentage)->toBe('20.00');
});

it('is idempotent across repeated runs', function () {
    $this->seed(DiscountTypeSeeder::class);
    $this->seed(DiscountTypeSeeder::class);

    expect(DiscountType::count())->toBe(6);
});

/**
 * `sale_discounts.type` already stores exactly the slug being seeded, so
 * historical rows adopt the new foreign key without a data migration.
 */
it('backfills historical sale discounts by slug', function () {
    actingAsOwner();

    $branch = Branch::factory()->create();
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
        'status' => 'completed',
        'subtotal' => 100,
        'discount_total' => 20,
        'total' => 80,
        'amount_tendered' => 80,
        'change_due' => 0,
        'credit_amount' => 0,
        'sold_at' => now(),
    ]);

    // A row written before discount_types existed.
    $discount = SaleDiscount::create([
        'sale_id' => $sale->id,
        'type' => 'senior',
        'percentage' => 20,
        'amount' => 20,
        'amount_before' => 100,
        'amount_after' => 80,
    ]);

    expect($discount->discount_type_id)->toBeNull();

    $this->seed(DiscountTypeSeeder::class);

    $senior = DiscountType::where('slug', 'senior')->first();

    expect($discount->fresh()->discount_type_id)->toBe($senior->id)
        // `type` is the record of what was applied and must not move.
        ->and($discount->fresh()->getRawOriginal('type'))->toBe('senior');
});
