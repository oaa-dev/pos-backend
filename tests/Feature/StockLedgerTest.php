<?php

use App\Enums\StockMovementTypeEnum;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\StockService;
use App\Support\Money;

/**
 * Coffee based in sachet: sachet = 1, strip = 10, box = 100.
 *
 * @return array{Branch, Product, ProductUnit, ProductUnit}
 */
function coffeeStock(): array
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

    return [$branch, $product, $sachetUnit, $stripUnit];
}

function stock(): StockService
{
    return app(StockService::class);
}

it('records a receipt in base units and moves the cache', function () {
    [$branch, $product, $sachet, $strip] = coffeeStock();

    // One box arriving as 10 strips = 100 sachets. A delivery, so the type is
    // named explicitly — this test asserts it.
    stock()->receive(
        $branch,
        $product,
        $strip,
        10,
        StockMovementTypeEnum::PURCHASE_RECEIPT,
        unitCost: 70,
    );

    $cache = BranchProductStock::first();

    expect($cache->quantity_on_hand)->toEqual('100.000')
        // ₱70 a strip over a factor of 10 is ₱7 a sachet.
        ->and($cache->average_cost)->toEqual('7.0000');

    $movement = StockMovement::first();

    expect($movement->quantity_entered)->toEqual('10.000')
        ->and($movement->quantity_base)->toEqual('100.000')
        ->and($movement->type)->toBe(StockMovementTypeEnum::PURCHASE_RECEIPT);
});

it('issues stock converted from the selling unit', function () {
    [$branch, $product, $sachet, $strip] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 100, unitCost: 7);
    stock()->issue($branch, $product, $strip, 3);

    // 3 strips = 30 sachets.
    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('70.000');
});

it('keeps the cache equal to the sum of the ledger', function () {
    [$branch, $product, $sachet, $strip] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 100, unitCost: 7);
    stock()->issue($branch, $product, $strip, 2);
    stock()->issue($branch, $product, $sachet, 5);
    stock()->openingBalance($branch, $product, $strip, 1, unitCost: 70);

    $ledgerSum = StockMovement::sum('quantity_base');

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual(number_format($ledgerSum, 3, '.', ''));
});

it('refuses to issue more than is on hand', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7);

    stock()->issue($branch, $product, $sachet, 11);
})->throws(InsufficientStockException::class);

it('leaves the ledger untouched when an issue fails', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7);
    $movementsBefore = StockMovement::count();

    try {
        stock()->issue($branch, $product, $sachet, 50);
    } catch (InsufficientStockException) {
        // expected
    }

    expect(StockMovement::count())->toBe($movementsBefore)
        ->and(BranchProductStock::first()->quantity_on_hand)->toEqual('10.000');
});

// --- FEFO ------------------------------------------------------------------

it('deducts from the earliest expiring batch first', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7, expiryDate: '2026-12-01');
    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 8, expiryDate: '2026-09-01');

    stock()->issue($branch, $product, $sachet, 5);

    $september = ProductBatch::whereDate('expiry_date', '2026-09-01')->first();
    $december = ProductBatch::whereDate('expiry_date', '2026-12-01')->first();

    expect($september->quantity_remaining)->toEqual('5.000')
        ->and($december->quantity_remaining)->toEqual('10.000');
});

/**
 * MySQL sorts NULL first on ASC, which would drain non-expiring stock ahead
 * of food about to turn. The scope forces nulls last.
 */
it('puts non-expiring batches last in FEFO order', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7);
    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 8, expiryDate: '2026-09-01');

    stock()->issue($branch, $product, $sachet, 5);

    $expiring = ProductBatch::whereNotNull('expiry_date')->first();
    $neverExpires = ProductBatch::whereNull('expiry_date')->first();

    expect($expiring->quantity_remaining)->toEqual('5.000')
        ->and($neverExpires->quantity_remaining)->toEqual('10.000');
});

it('spans multiple batches and costs each at its own batch cost', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7, expiryDate: '2026-09-01');
    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 9, expiryDate: '2026-12-01');

    $movements = stock()->issue($branch, $product, $sachet, 15);

    expect($movements)->toHaveCount(2)
        ->and($movements[0]->quantity_base)->toEqual('-10.000')
        ->and($movements[0]->unit_cost)->toEqual('7.0000')
        ->and($movements[1]->quantity_base)->toEqual('-5.000')
        ->and($movements[1]->unit_cost)->toEqual('9.0000');

    // The entered quantity belongs to the issue as a whole, so it is reported
    // once rather than split across the batch movements.
    expect($movements[1]->quantity_entered)->toEqual('0.000');
});

it('closes a batch once it is depleted', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7, expiryDate: '2026-09-01');
    stock()->issue($branch, $product, $sachet, 10);

    expect(ProductBatch::first()->status)->toBe('depleted');
});

// --- batch merge-on-match --------------------------------------------------

it('merges a receipt into an open batch with the same expiry and cost', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7, expiryDate: '2026-09-01');
    stock()->openingBalance($branch, $product, $sachet, 15, unitCost: 7, expiryDate: '2026-09-01');

    expect(ProductBatch::count())->toBe(1)
        ->and(ProductBatch::first()->quantity_remaining)->toEqual('25.000');
});

it('opens a new batch when the cost differs', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7, expiryDate: '2026-09-01');
    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 8, expiryDate: '2026-09-01');

    expect(ProductBatch::count())->toBe(2);
});

it('opens a new batch when the expiry differs', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7, expiryDate: '2026-09-01');
    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7, expiryDate: '2026-10-01');

    expect(ProductBatch::count())->toBe(2);
});

/**
 * The row-explosion mitigation: a non-perishable restocked repeatedly at the
 * same cost must not accumulate a batch per delivery.
 */
it('keeps a non-perishable at one batch across repeated restocks', function () {
    [$branch, $product, $sachet] = coffeeStock();

    foreach (range(1, 5) as $ignored) {
        stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7);
    }

    expect(ProductBatch::count())->toBe(1)
        ->and(ProductBatch::first()->quantity_remaining)->toEqual('50.000');
});

// --- precision -------------------------------------------------------------

it('handles fractional conversion without drift', function () {
    $branch = Branch::factory()->create();
    $kilo = Unit::firstOrCreate(['name' => 'kilo'], ['abbreviation' => 'kg']);
    $sako = Unit::firstOrCreate(['name' => 'sako'], ['abbreviation' => 'sk']);

    $rice = Product::factory()->create(['name' => 'Bigas', 'base_unit_id' => $kilo->id]);
    $kiloUnit = ProductUnit::factory()->base()->create(['product_id' => $rice->id, 'unit_id' => $kilo->id]);
    $sakoUnit = ProductUnit::factory()->create([
        'product_id' => $rice->id,
        'unit_id' => $sako->id,
        'conversion_factor' => 25.5,
    ]);

    stock()->openingBalance($branch, $rice, $sakoUnit, 4, unitCost: 1275);

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('102.000')
        // ₱1275 a sako over 25.5 kg is exactly ₱50 a kilo.
        ->and(BranchProductStock::first()->average_cost)->toEqual('50.0000');

    stock()->issue($branch, $rice, $kiloUnit, '0.250');

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('101.750');
});

// --- stock-only catalogue --------------------------------------------------

it('tracks stock even for a legacy row whose flag is false', function () {
    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create([
        'base_unit_id' => $unit->id,
        'track_stock' => false,
    ]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
    ]);

    stock()->openingBalance($branch, $product, $productUnit, 2, unitCost: 10);
    $movements = stock()->issue($branch, $product, $productUnit, 1);

    expect($movements)->toHaveCount(1)
        ->and(StockMovement::count())->toBe(2)
        ->and(BranchProductStock::first()->quantity_on_hand)->toEqual('1.000');
});

// --- reconcile -------------------------------------------------------------

it('recomputes the cache from the ledger and reports drift', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 100, unitCost: 7);

    // Corrupt the cache the way a missed update would.
    BranchProductStock::first()->update(['quantity_on_hand' => 80]);

    $result = stock()->reconcile($branch->id, $product->id);

    expect($result['quantity_before'])->toEqual('80.000')
        ->and($result['quantity_after'])->toEqual('100.000')
        ->and($result['drift'])->toEqual('20.000')
        ->and(BranchProductStock::first()->quantity_on_hand)->toEqual('100.000');
});

it('separates shrinkage from personal use', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 100, unitCost: 7);
    stock()->issue($branch, $product, $sachet, 5, StockMovementTypeEnum::SPOILAGE);
    stock()->issue($branch, $product, $sachet, 3, StockMovementTypeEnum::PERSONAL_USE);

    $shrinkage = StockMovement::get()->filter(fn ($m) => $m->type->isShrinkage());

    expect($shrinkage)->toHaveCount(1)
        ->and($shrinkage->first()->quantity_base)->toEqual('-5.000');
});

/**
 * bcmath truncates; it never rounds. Truncating the per-base-unit cost makes
 * every delivery cost fractionally less than was actually paid, and the
 * shortfall recurs on every receipt rather than cancelling out.
 */
it('rounds the per-unit cost so it multiplies back to what was paid', function () {
    [$branch, $product, $sachet, $strip] = coffeeStock();

    // A factor of 6, not the 12 the plan used: ₱100/12 is 8.33333…, whose
    // fifth decimal is 3, so truncating and rounding both give 8.3333 and the
    // case proves nothing. ₱100/6 is 16.66666… — truncation gives 16.6666,
    // rounding gives 16.6667. This is the assertion that fails against the
    // old bare bcdiv.
    $strip->update(['conversion_factor' => 6]);

    stock()->openingBalance($branch, $product, $strip->fresh(), 1, unitCost: 100);

    $batch = ProductBatch::first();

    expect($batch->unit_cost)->toEqual('16.6667');

    // Rounding lands 0.0002 from the ₱100.00 paid; truncation lands 0.0004
    // away, and always on the same side.
    $recovered = bcmul((string) $batch->unit_cost, '6', 4);

    expect(abs((float) $recovered - 100.0))->toBeLessThan(0.01);
});

it('leaves a conversion factor of one unchanged', function () {
    [$branch, $product, $sachet, $strip] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 1, unitCost: 11);

    expect(ProductBatch::first()->unit_cost)->toEqual('11.0000');
});

it('rounds half away from zero at the requested scale', function () {
    // The boundary the truncating version got wrong.
    expect(Money::round('8.33335', 4))->toBe('8.3334')
        ->and(Money::round('-8.33335', 4))->toBe('-8.3334')
        ->and(Money::round('8.33334', 4))->toBe('8.3333');
});

it('returns zero rather than dividing by zero', function () {
    expect(Money::divide('100', '0', 4))->toBe('0.0000');
});

/**
 * Two costs, for the same reason there are two quantities.
 *
 * `unit_cost` is per base unit because that is what FIFO costs against. But a
 * ledger row is reconciled against a supplier's invoice, and the invoice says
 * ₱11 a dosena — not ₱0.9167 a piraso. Deriving the entered figure back drifts
 * on the rounding, so it is recorded.
 */
it('records the cost as entered alongside the base cost', function () {
    [$branch, $product, $sachet, $strip] = coffeeStock();

    // A strip of 10, bought at ₱11 the strip.
    stock()->receive(
        $branch,
        $product,
        $strip,
        1,
        StockMovementTypeEnum::PURCHASE_RECEIPT,
        unitCost: 11,
    );

    $movement = StockMovement::first();

    expect($movement->unit_cost_entered)->toEqual('11.0000')
        // ₱11 over a factor of 10.
        ->and($movement->unit_cost)->toEqual('1.1000');
});

/**
 * Nobody types a cost for a sale — the batch decides it — so there is no
 * entered figure to record and the ledger falls back to the base one.
 */
it('leaves the entered cost null on an issue', function () {
    [$branch, $product, $sachet] = coffeeStock();

    stock()->openingBalance($branch, $product, $sachet, 10, unitCost: 7);
    stock()->issue($branch, $product, $sachet, 3, StockMovementTypeEnum::SALE);

    $issue = StockMovement::where('type', StockMovementTypeEnum::SALE)->first();

    expect($issue->unit_cost_entered)->toBeNull()
        ->and($issue->unit_cost)->toEqual('7.0000');
});
