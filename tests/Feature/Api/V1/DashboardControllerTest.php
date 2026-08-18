<?php

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\CashDrawerSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Unit;
use Illuminate\Support\Str;

/**
 * A completed sale today, written directly so the test owns the figures.
 */
function dashboardSale(
    Branch $branch,
    Product $product,
    ProductUnit $unit,
    float $quantity,
    float $price,
    float $cost,
    string $status = 'completed',
): Sale {
    $session = CashDrawerSession::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => auth()->id(),
    ]);

    $sale = Sale::create([
        'uuid' => (string) Str::uuid(),
        'sale_number' => 'DB-'.Str::upper(Str::random(10)),
        'branch_id' => $branch->id,
        'cash_drawer_session_id' => $session->id,
        'user_id' => auth()->id(),
        'status' => $status,
        'subtotal' => $quantity * $price,
        'total' => $quantity * $price,
        'sold_at' => now(),
    ]);

    $sale->items()->create([
        'product_id' => $product->id,
        'product_unit_id' => $unit->id,
        'quantity' => $quantity,
        'quantity_base' => $quantity,
        'unit_price' => $price,
        'unit_cost' => $cost,
        'product_name_snapshot' => $product->name,
        'unit_name_snapshot' => 'piraso',
        'line_total' => $quantity * $price,
    ]);

    return $sale;
}

/** @return array{Branch, Product, ProductUnit} */
function dashboardSellable(string $name = 'Itlog'): array
{
    $branch = Branch::factory()->create();
    $piraso = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);

    $product = Product::factory()->create(['name' => $name, 'base_unit_id' => $piraso->id]);
    $unit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $piraso->id,
        'selling_price' => 9,
    ]);

    return [$branch, $product, $unit];
}

function overview(array $query = []): array
{
    return test()->getJson('/api/v1/dashboard/overview?'.http_build_query($query))
        ->assertOk()
        ->json('data');
}

it('totals today takings across every branch the owner can see', function () {
    actingAsOwner();
    [$one, $product, $unit] = dashboardSellable();
    $two = Branch::factory()->create();

    dashboardSale($one, $product, $unit, 10, 9, 6);
    dashboardSale($two, $product, $unit, 5, 9, 6);

    $data = overview();

    expect($data['sales_today']['total_sales'])->toBe('135.00')
        ->and($data['sales_today']['total_count'])->toBe(2)
        // 15 sold at a P3 margin.
        ->and($data['sales_today']['total_profit'])->toBe('45.00')
        ->and($data['sales_today']['branches'])->toHaveCount(2);
});

/**
 * The guard that matters here.
 *
 * This aggregates across branches rather than going through a scoped
 * repository, so without its own check a supervisor would read another
 * branch's takings. The actor deliberately holds reports.view — otherwise the
 * test would pass because the permission was missing.
 */
it('shows a supervisor only their own branch', function () {
    actingAsOwner();
    [$mine, $product, $unit] = dashboardSellable();
    $theirs = Branch::factory()->create();

    dashboardSale($mine, $product, $unit, 10, 9, 6);
    dashboardSale($theirs, $product, $unit, 100, 9, 6);

    $supervisor = actingAsUserWith(['reports.view']);
    $supervisor->branches()->attach($mine->id);

    $data = overview();

    expect($data['sales_today']['branches'])->toHaveCount(1)
        // 10 x 9, not 110 x 9.
        ->and($data['sales_today']['total_sales'])->toBe('90.00');
});

it('leaves a voided sale out of today', function () {
    actingAsOwner();
    [$branch, $product, $unit] = dashboardSellable();

    dashboardSale($branch, $product, $unit, 10, 9, 6);
    dashboardSale($branch, $product, $unit, 100, 9, 6, status: 'voided');

    expect(overview()['sales_today']['total_sales'])->toBe('90.00');
});

/**
 * Quantity and profit disagree, and an owner deciding what to push needs both
 * rather than whichever the query happened to sort by.
 */
it('ranks top products by quantity and by profit separately', function () {
    actingAsOwner();
    [$branch, $cheap, $cheapUnit] = dashboardSellable('Yelo');

    $piraso = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $rich = Product::factory()->create(['name' => 'Sigarilyo', 'base_unit_id' => $piraso->id]);
    $richUnit = ProductUnit::factory()->base()->create([
        'product_id' => $rich->id,
        'unit_id' => $piraso->id,
        'selling_price' => 100,
    ]);

    // Many sold, thin margin.
    dashboardSale($branch, $cheap, $cheapUnit, 100, 2, 1.5);
    // Few sold, fat margin.
    dashboardSale($branch, $rich, $richUnit, 5, 100, 40);

    $data = overview();

    expect($data['top_products']['by_quantity'][0]['product_name'])->toBe('Yelo')
        // 100 x 0.50 = 50 against 5 x 60 = 300.
        ->and($data['top_products']['by_profit'][0]['product_name'])->toBe('Sigarilyo');
});

/**
 * Low stock uses the store's own reorder points — the same rule Stock Levels
 * and the What to Buy tab use, so the three cannot disagree about a product.
 */
it('counts low stock only where a reorder point is set', function () {
    actingAsOwner();
    [$branch, $product] = dashboardSellable();

    $stock = BranchProductStock::create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity_on_hand' => 2,
    ]);

    // No reorder point yet: nothing has been configured, so nothing is overdue.
    expect(overview()['attention']['low_stock']['count'])->toBe(0);

    $stock->update(['reorder_point' => 10]);

    expect(overview()['attention']['low_stock']['count'])->toBe(1);
});

it('totals what the suki owe', function () {
    actingAsOwner();
    [$branch] = dashboardSellable();

    Customer::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Aling Nena',
        'current_balance' => 250,
    ]);
    Customer::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Mang Tino',
        'current_balance' => 0,
    ]);

    $data = overview();

    expect($data['utang']['total'])->toBe('250.00')
        // Only those actually owing.
        ->and($data['utang']['customer_count'])->toBe(1);
});

it('narrows the movement window to the days asked for', function () {
    actingAsOwner();
    [$branch, $product, $unit] = dashboardSellable();

    $old = dashboardSale($branch, $product, $unit, 50, 9, 6);
    $old->update(['sold_at' => now()->subDays(20)]);

    dashboardSale($branch, $product, $unit, 1, 9, 6);

    // A week back excludes the 20-day-old sale; a month back includes it.
    expect(overview(['days' => 7])['top_products']['by_quantity'][0]['quantity_base'])->toBe('1.000')
        ->and(overview(['days' => 30])['top_products']['by_quantity'][0]['quantity_base'])->toBe('51.000');
});

it('requires reports.view', function () {
    actingAsUserWith(['sales.create']);

    $this->getJson('/api/v1/dashboard/overview')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
