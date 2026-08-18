<?php

use App\Enums\StatusEnum;
use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\SaleAlreadyVoidedException;
use App\Exceptions\ShiftNotOpenException;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Customer;
use App\Models\DiscountType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\CashDrawerService;
use App\Services\SaleService;
use App\Services\StockService;
use Database\Seeders\DiscountTypeSeeder;
use Illuminate\Support\Str;

/**
 * Discounts resolve against the `discount_types` table rather than an enum, so
 * the seeded rows have to exist before a sale can carry one.
 */
beforeEach(function () {
    $this->seed(DiscountTypeSeeder::class);
});

function sales(): SaleService
{
    return app(SaleService::class);
}

/**
 * A branch with an open shift and Kopiko in stock: sachet base, strip = 10.
 *
 * @return array{Branch, Product, ProductUnit, ProductUnit}
 */
function sellable(int $sachets = 100, float $cost = 7): array
{
    $branch = Branch::factory()->create();
    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);
    $strip = Unit::firstOrCreate(['name' => 'strip'], ['abbreviation' => 'stp']);

    $product = Product::factory()->create([
        'name' => 'Kopiko Blanca',
        'base_unit_id' => $sachet->id,
    ]);

    $sachetUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $sachet->id,
        'selling_price' => 8,
    ]);
    $stripUnit = ProductUnit::factory()->create([
        'product_id' => $product->id,
        'unit_id' => $strip->id,
        'conversion_factor' => 10,
        'selling_price' => 75,
    ]);

    app(StockService::class)->openingBalance($branch, $product, $sachetUnit, $sachets, unitCost: $cost);
    app(CashDrawerService::class)->open($branch, 1000);

    return [$branch, $product, $sachetUnit, $stripUnit];
}

function cartPayload(Branch $branch, ProductUnit $unit, array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'branch_id' => $branch->id,
        'items' => [
            ['product_id' => $unit->product_id, 'product_unit_id' => $unit->id, 'quantity' => 2],
        ],
        'payments' => [['method' => 'cash', 'amount' => 16]],
        'amount_tendered' => 20,
    ], $overrides);
}

it('records a sale and deducts stock', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet));

    expect($sale->subtotal)->toEqual('16.00')
        ->and($sale->total)->toEqual('16.00')
        ->and($sale->change_due)->toEqual('4.00')
        ->and(BranchProductStock::first()->quantity_on_hand)->toEqual('98.000');
});

it('numbers sales per branch with the branch code', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet));

    expect($sale->sale_number)->toBe($branch->code.'-000001');
});

it('converts the selling unit to base units', function () {
    actingAsOwner();
    [$branch, $product, $sachet, $strip] = sellable();

    // 3 strips = 30 sachets.
    sales()->create(cartPayload($branch, $strip, [
        'items' => [['product_id' => $strip->product_id, 'product_unit_id' => $strip->id, 'quantity' => 3]],
        'payments' => [['method' => 'cash', 'amount' => 225]],
        'amount_tendered' => 225,
    ]));

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('70.000');
});

// --- idempotency -----------------------------------------------------------

/**
 * The defect this prevents is charging a suki twice for one basket, which is
 * worse than failing outright.
 */
it('returns the original sale when the same uuid is submitted twice', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $payload = cartPayload($branch, $sachet);

    $first = sales()->create($payload);
    $second = sales()->create($payload);

    expect($second->id)->toBe($first->id)
        ->and(Sale::count())->toBe(1)
        // Stock moved once, not twice.
        ->and(BranchProductStock::first()->quantity_on_hand)->toEqual('98.000');
});

// --- costing ---------------------------------------------------------------

/**
 * The highest-risk silent defect in the phase. StockService::issue() returns
 * one movement per batch drawn; taking the first movement's cost would
 * misprice every sale that crosses a batch boundary, and nothing would fail.
 */
it('records a quantity-weighted unit cost across two batches', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);

    $product = Product::factory()->create(['base_unit_id' => $sachet->id]);
    $unit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $sachet->id,
        'selling_price' => 10,
    ]);

    $stock = app(StockService::class);
    $stock->openingBalance($branch, $product, $unit, 10, unitCost: 7, expiryDate: '2026-09-01');
    $stock->openingBalance($branch, $product, $unit, 10, unitCost: 9, expiryDate: '2026-12-01');
    app(CashDrawerService::class)->open($branch, 0);

    // 15 sachets: 10 at ₱7 then 5 at ₱9 = ₱115 over 15 = ₱7.6667.
    $sale = sales()->create(cartPayload($branch, $unit, [
        'items' => [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'quantity' => 15]],
        'payments' => [['method' => 'cash', 'amount' => 150]],
        'amount_tendered' => 150,
    ]));

    expect($sale->items->first()->unit_cost)->toEqual('7.6667');
});

it('reports gross profit from the issued cost', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable(100, 7);

    $sale = sales()->create(cartPayload($branch, $sachet));

    // 2 sachets at ₱8 = ₱16, cost 2 × ₱7 = ₱14.
    expect($sale->items->first()->grossProfit())->toEqual('2.00');
});

it('snapshots the product name so a reprint never changes', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet));
    $product->update(['name' => 'Renamed Later']);

    expect($sale->items->first()->product_name_snapshot)->toBe('Kopiko Blanca');
});

// --- shift binding ---------------------------------------------------------

it('refuses to sell when no shift is open', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);
    $product = Product::factory()->create(['base_unit_id' => $sachet->id]);
    $unit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $sachet->id,
    ]);
    app(StockService::class)->openingBalance($branch, $product, $unit, 10, unitCost: 7);

    sales()->create(cartPayload($branch, $unit));
})->throws(ShiftNotOpenException::class);

it('binds the sale to the open session', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet));

    expect($sale->cash_drawer_session_id)->not->toBeNull();
});

// --- payments --------------------------------------------------------------

it('accepts a mixed cash and utang payment', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();
    $customer = Customer::factory()->create();

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'customer_id' => $customer->id,
        'payments' => [
            ['method' => 'cash', 'amount' => 10],
            ['method' => 'credit', 'amount' => 6],
        ],
        'amount_tendered' => 10,
    ]));

    expect($sale->credit_amount)->toEqual('6.00')
        ->and($sale->payments)->toHaveCount(2)
        ->and($customer->fresh()->current_balance)->toEqual('6.00');
});

it('refuses a payment total below the sale total', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    sales()->create(cartPayload($branch, $sachet, [
        'payments' => [['method' => 'cash', 'amount' => 5]],
    ]));
})->throws(InvalidArgumentException::class);

it('refuses utang without a customer', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    sales()->create(cartPayload($branch, $sachet, [
        'payments' => [['method' => 'credit', 'amount' => 16]],
    ]));
})->throws(InvalidArgumentException::class);

it('refuses utang from a blocked customer', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();
    $customer = Customer::factory()->blocked()->create();

    sales()->create(cartPayload($branch, $sachet, [
        'customer_id' => $customer->id,
        'payments' => [['method' => 'credit', 'amount' => 16]],
    ]));
})->throws(CreditLimitExceededException::class);

// --- discounts -------------------------------------------------------------

/**
 * Per-product eligibility was dropped on 2026-08-06: a statutory discount now
 * covers the whole cart. This is the same scenario as the old "only eligible
 * lines" test, asserting the opposite — 20% of everything, not of the flagged
 * subset.
 */
it('applies a statutory discount to the whole cart', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sigarilyo = Product::factory()->create([
        'base_unit_id' => $product->base_unit_id,
    ]);
    $sigarilyoUnit = ProductUnit::factory()->base()->create([
        'product_id' => $sigarilyo->id,
        'unit_id' => $product->base_unit_id,
        'selling_price' => 100,
    ]);
    app(StockService::class)->openingBalance($branch, $sigarilyo, $sigarilyoUnit, 10, unitCost: 90);

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $sachet->id, 'quantity' => 2],
            ['product_id' => $sigarilyo->id, 'product_unit_id' => $sigarilyoUnit->id, 'quantity' => 1],
        ],
        'discounts' => [[
            'type' => 'senior',
            'id_number' => 'SC-12345',
            'customer_name' => 'Lola Nena',
        ]],
        'payments' => [['method' => 'cash', 'amount' => 92.80]],
        'amount_tendered' => 100,
    ]));

    // 20% of the full ₱116, where it used to be 20% of the ₱16 eligible lines.
    expect($sale->discount_total)->toEqual('23.20')
        ->and($sale->subtotal)->toEqual('116.00')
        ->and($sale->total)->toEqual('92.80');
});

it('keeps the senior discount logbook fields', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'discounts' => [[
            'type' => 'pwd',
            'id_number' => 'PWD-99',
            'customer_name' => 'Mang Tonyo',
        ]],
        'payments' => [['method' => 'cash', 'amount' => 12.80]],
        'amount_tendered' => 20,
    ]));

    $discount = $sale->discounts->first();

    expect($discount->id_number)->toBe('PWD-99')
        ->and($discount->customer_name)->toBe('Mang Tonyo')
        ->and($discount->percentage)->toEqual('20.00')
        ->and($discount->cashier_id)->not->toBeNull();
});

it('applies a manual discount to the whole cart regardless of eligibility', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'discounts' => [['type' => 'manual', 'amount' => 5, 'reason' => 'Suki']],
        'payments' => [['method' => 'cash', 'amount' => 11]],
        'amount_tendered' => 11,
    ]));

    expect($sale->discount_total)->toEqual('5.00')
        ->and($sale->total)->toEqual('11.00');
});

it('never discounts below zero', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'discounts' => [['type' => 'manual', 'amount' => 9999]],
        'payments' => [['method' => 'cash', 'amount' => 1]],
        'amount_tendered' => 1,
    ]));

    expect($sale->total)->toEqual('0.00');
});

// --- void ------------------------------------------------------------------

it('returns stock and clears utang on void', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();
    $customer = Customer::factory()->create();

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'customer_id' => $customer->id,
        'payments' => [['method' => 'credit', 'amount' => 16]],
    ]));

    expect($customer->fresh()->current_balance)->toEqual('16.00');

    $voided = sales()->void($sale, 'Nagkamali ng entry');

    expect($voided->status)->toBe('voided')
        ->and($voided->void_reason)->toBe('Nagkamali ng entry')
        ->and($customer->fresh()->current_balance)->toEqual('0.00')
        // Stock came back.
        ->and(BranchProductStock::first()->quantity_on_hand)->toEqual('100.000');
});

it('reverses rather than deletes', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet));
    sales()->void($sale, 'Test');

    expect(Sale::count())->toBe(1)
        ->and(StockMovement::where('type', 'sale_void')->count())->toBe(1)
        ->and(StockMovement::where('type', 'sale')->count())->toBe(1);
});

it('refuses to void twice', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet));
    sales()->void($sale, 'First');
    sales()->void($sale->fresh(), 'Second');
})->throws(SaleAlreadyVoidedException::class);

// --- atomicity -------------------------------------------------------------

it('leaves nothing behind when a sale fails partway', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable(100);

    try {
        sales()->create(cartPayload($branch, $sachet, [
            'items' => [
                ['product_id' => $product->id, 'product_unit_id' => $sachet->id, 'quantity' => 2],
                // More than is on hand — fails after the first line succeeded.
                ['product_id' => $product->id, 'product_unit_id' => $sachet->id, 'quantity' => 500],
            ],
        ]));
    } catch (Throwable) {
        // expected
    }

    expect(Sale::count())->toBe(0)
        ->and(StockMovement::where('type', 'sale')->count())->toBe(0)
        ->and(BranchProductStock::first()->quantity_on_hand)->toEqual('100.000');
});

/*
|--------------------------------------------------------------------------
| Discounts resolve from the table, not an enum
|--------------------------------------------------------------------------
| Each of these would have behaved differently — or been impossible — while
| SaleDiscountTypeEnum was the source of truth.
*/

it('applies a discount type the owner defined at runtime', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    // Impossible under the enum: this slug did not exist when the code shipped.
    $fiesta = DiscountType::create([
        'name' => 'Fiesta Sale',
        'slug' => 'fiesta',
        'percentage' => '10.00',
    ]);

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $sachet->id, 'quantity' => 2],
        ],
        'discounts' => [['type' => 'fiesta']],
        'payments' => [['method' => 'cash', 'amount' => 14.40]],
        'amount_tendered' => 20,
    ]));

    // 10% of ₱16.
    expect($sale->discount_total)->toEqual('1.60')
        ->and($sale->discounts->first()->discount_type_id)->toBe($fiesta->id)
        // The slug is the historical record and must not move on a rename.
        ->and($sale->discounts->first()->type)->toBe('fiesta');
});

it('refuses a discount type that does not exist', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    sales()->create(cartPayload($branch, $sachet, [
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $sachet->id, 'quantity' => 2],
        ],
        'discounts' => [['type' => 'invented']],
        'payments' => [['method' => 'cash', 'amount' => 16]],
        'amount_tendered' => 20,
    ]));
})->throws(InvalidArgumentException::class, "no active discount called 'invented'");

/**
 * The failure this prevents is the quiet one: an owner switches a promo off,
 * and the till keeps accepting it while discounting zero — a sale that looks
 * correct and is not.
 */
it('refuses a discount type that has been switched off', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    DiscountType::create([
        'name' => 'Expired Promo',
        'slug' => 'expired-promo',
        'percentage' => '10.00',
        'status' => StatusEnum::INACTIVE,
    ]);

    sales()->create(cartPayload($branch, $sachet, [
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $sachet->id, 'quantity' => 2],
        ],
        'discounts' => [['type' => 'expired-promo']],
        'payments' => [['method' => 'cash', 'amount' => 16]],
        'amount_tendered' => 20,
    ]));
})->throws(InvalidArgumentException::class, "no active discount called 'expired-promo'");

it('reads the statutory rate from the row rather than a constant', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = sellable();

    $sale = sales()->create(cartPayload($branch, $sachet, [
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $sachet->id, 'quantity' => 2],
        ],
        'discounts' => [[
            'type' => 'senior',
            'id_number' => 'SC-001',
            'customer_name' => 'Lola Nena',
        ]],
        'payments' => [['method' => 'cash', 'amount' => 12.80]],
        'amount_tendered' => 20,
    ]));

    // 20.00 comes from discount_types.percentage now, not a constant.
    expect((string) $sale->discounts->first()->percentage)->toBe('20.00')
        ->and($sale->discount_total)->toEqual('3.20');
});
