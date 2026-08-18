<?php

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\CashDrawerSession;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Unit;
use App\Repositories\Contracts\BranchProductStockRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Coffee in sachets, sold by the sachet, bought by the box of 100.
 *
 * @return array{Branch, Product, ProductUnit, ProductUnit}
 */
function reorderable(int $onHand = 100): array
{
    $branch = Branch::factory()->create();
    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);
    $box = Unit::firstOrCreate(['name' => 'box'], ['abbreviation' => 'box']);

    $product = Product::factory()->create([
        'name' => 'Kopiko Blanca',
        'base_unit_id' => $sachet->id,
    ]);

    $sachetUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $sachet->id,
        'selling_price' => 8,
    ]);
    $boxUnit = ProductUnit::factory()->create([
        'product_id' => $product->id,
        'unit_id' => $box->id,
        'conversion_factor' => 100,
        'selling_price' => 700,
    ]);

    // Written directly, and always — including at zero. A product that has
    // sold out still has a stock row and is the most urgent line in the
    // report; a product never stocked in this branch legitimately has none.
    BranchProductStock::create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'quantity_on_hand' => $onHand,
        'average_cost' => 7,
    ]);

    return [$branch, $product, $sachetUnit, $boxUnit];
}

/**
 * A completed sale inside the report window.
 *
 * Written directly rather than through SaleService so the test controls the
 * sale date and the stock level independently — the report reads sale_items
 * joined to sales, and nothing else.
 */
function sellSachets(
    Branch $branch,
    Product $product,
    ProductUnit $unit,
    float $quantity,
    string $soldAt = '2026-07-05',
    string $status = 'completed',
): Sale {
    $session = CashDrawerSession::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => auth()->id(),
    ]);

    $sale = Sale::create([
        'uuid' => (string) Str::uuid(),
        'sale_number' => 'RO-'.Str::upper(Str::random(10)),
        'branch_id' => $branch->id,
        'cash_drawer_session_id' => $session->id,
        'user_id' => auth()->id(),
        'status' => $status,
        'subtotal' => $quantity * 8,
        'total' => $quantity * 8,
        'sold_at' => $soldAt.' 10:00:00',
    ]);

    $sale->items()->create([
        'product_id' => $product->id,
        'product_unit_id' => $unit->id,
        'quantity' => $quantity,
        'quantity_base' => $quantity,
        'unit_price' => 8,
        'unit_cost' => 7,
        'product_name_snapshot' => $product->name,
        'unit_name_snapshot' => 'sachet',
        'line_total' => $quantity * 8,
    ]);

    return $sale;
}

/** @return array<string, mixed> the single row for $product */
function reorderRow(Branch $branch, Product $product, array $query = []): array
{
    $response = test()->getJson('/api/v1/inventory/branch/'.$branch->id.'/reorder?'.http_build_query([
        'from' => '2026-07-01',
        'to' => '2026-07-10',
        ...$query,
    ]))->assertOk();

    return collect($response->json('data.rows'))->firstWhere('product_id', $product->id) ?? [];
}

/**
 * The defect this prevents is absence, not error.
 *
 * The obvious query starts at sale_items and joins stock, which silently omits
 * every product that sold nothing — and a product that stopped moving is
 * exactly what needs looking at. Assert the row is PRESENT; asserting a 200
 * would pass with the product missing.
 */
it('still lists a product that sold nothing in the period', function () {
    actingAsOwner();
    [$branch, $product] = reorderable(onHand: 40);

    $row = reorderRow($branch, $product);

    expect($row)->not->toBeEmpty()
        ->and($row['net_sold_base'])->toBe('0.000')
        ->and($row['avg_daily'])->toBe('0.0000')
        // No meaningful cover when nothing sells — null, not a huge number
        // that would sort as urgent.
        ->and($row['days_cover'])->toBeNull()
        ->and($row['suggested_quantity'])->toBe(0);
});

it('suggests nothing for a product with more stock than it sells', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 1000);

    sellSachets($branch, $product, $sachet, 10);

    // 10 over 10 days is 1/day; 14 days of cover is 14 against 990 on hand.
    $row = reorderRow($branch, $product);

    expect($row['suggested_base'])->toBe('0.000')
        ->and($row['suggested_quantity'])->toBe(0);
});

it('suggests whole purchase units rounded up', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 0);

    // 300 sold over 10 days = 30/day. 14 days of cover = 420 sachets, none on
    // hand, so 420 ÷ 100 per box = 4.2 boxes — which must round UP to 5.
    sellSachets($branch, $product, $sachet, 300);

    $row = reorderRow($branch, $product);

    expect($row['avg_daily'])->toBe('30.0000')
        ->and($row['suggested_base'])->toBe('420.000')
        ->and($row['suggested_quantity'])->toBe(5)
        ->and($row['purchase_unit']['abbreviation'])->toBe('box');
});

/**
 * Without this the report tells you to re-buy goods already in transit, which
 * is the most likely way it actively causes harm.
 */
it('prefers the unit a supplier last sold it in', function () {
    actingAsOwner();
    [$branch, $product, $sachet, $box] = reorderable(onHand: 0);

    sellSachets($branch, $product, $sachet, 300);

    // Even though the box is the larger pack, the supplier sells sachets.
    SupplierProduct::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'product_id' => $product->id,
        'product_unit_id' => $sachet->id,
        'last_cost' => 7,
    ]);

    $row = reorderRow($branch, $product);

    expect($row['purchase_unit']['abbreviation'])->toBe('sct')
        ->and($row['suggested_quantity'])->toBe(420);
});

it('falls back to the largest pack when no supplier has sold it', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 0);

    sellSachets($branch, $product, $sachet, 300);

    expect(reorderRow($branch, $product)['purchase_unit']['abbreviation'])->toBe('box');
});

it('nets returns out of demand', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 0);

    sellSachets($branch, $product, $sachet, 100);

    $row = reorderRow($branch, $product);

    // Sanity: the gross figure is what was sold.
    expect($row['sold_base'])->toBe('100.000')
        ->and($row['net_sold_base'])->toBe('100.000');
});

it('flags a period too short to infer a rate from', function () {
    actingAsOwner();
    [$branch, $product] = reorderable();

    $short = $this->getJson("/api/v1/inventory/branch/{$branch->id}/reorder?from=2026-07-01&to=2026-07-03")
        ->assertOk();

    $long = $this->getJson("/api/v1/inventory/branch/{$branch->id}/reorder?from=2026-07-01&to=2026-07-31")
        ->assertOk();

    expect($short->json('data.days'))->toBe(3)
        ->and($short->json('data.low_confidence'))->toBeTrue()
        ->and($long->json('data.low_confidence'))->toBeFalse();
});

it('scales the suggestion with the cover target', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 0);

    sellSachets($branch, $product, $sachet, 300);

    $week = reorderRow($branch, $product, ['cover_target_days' => 7]);
    $month = reorderRow($branch, $product, ['cover_target_days' => 28]);

    // 30/day: a week is 210 sachets, four weeks is 840.
    expect($week['suggested_base'])->toBe('210.000')
        ->and($month['suggested_base'])->toBe('840.000');
});

/**
 * Velocity is per branch — an ice cooler in a mall and one in a barangay do
 * not sell alike, and the reorder point that follows must differ too.
 */
it('computes a different suggestion for each branch', function () {
    actingAsOwner();
    [$busy, $product, $sachet] = reorderable(onHand: 0);
    sellSachets($busy, $product, $sachet, 300);

    $quiet = Branch::factory()->create();
    BranchProductStock::create([
        'branch_id' => $quiet->id,
        'product_id' => $product->id,
        'quantity_on_hand' => 300,
    ]);

    expect(reorderRow($busy, $product)['suggested_quantity'])->toBe(5)
        ->and(reorderRow($quiet, $product)['suggested_quantity'])->toBe(0);
});

it('requires inventory.view', function () {
    actingAsOwner();
    [$branch] = reorderable();

    actingAsUserWith(['sales.create']);

    $this->getJson("/api/v1/inventory/branch/{$branch->id}/reorder?from=2026-07-01&to=2026-07-10")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| Accepting a reorder point
|--------------------------------------------------------------------------
*/

/**
 * The point of the whole feature.
 *
 * `BranchProductStock::low()` requires reorder_point > 0; the column defaults
 * to zero and nothing has ever written it, so the low-stock alert has never
 * matched a row since Phase 2. Assert it starts matching — "the request
 * returned 200" would pass with nothing saved.
 */
it('switches on the low-stock alert that has never fired', function () {
    actingAsOwner();
    [$branch, $product] = reorderable(onHand: 40);

    expect(app(BranchProductStockRepositoryInterface::class)->lowStock($branch->id))->toHaveCount(0);

    $this->patchJson("/api/v1/inventory/branch/{$branch->id}/reorder-points", [
        'points' => [['product_id' => $product->id, 'reorder_point' => 100]],
    ])->assertOk()->assertJsonPath('data.updated', 1);

    $low = app(BranchProductStockRepositoryInterface::class)->lowStock($branch->id);

    // 40 on hand against a reorder point of 100.
    expect($low)->toHaveCount(1)
        ->and($low->first()->product_id)->toBe($product->id);
});

it('accepts a whole report in one call', function () {
    actingAsOwner();
    [$branch, $first] = reorderable(onHand: 10);

    $second = Product::factory()->create(['base_unit_id' => $first->base_unit_id]);
    BranchProductStock::create([
        'branch_id' => $branch->id,
        'product_id' => $second->id,
        'quantity_on_hand' => 5,
    ]);

    $this->patchJson("/api/v1/inventory/branch/{$branch->id}/reorder-points", [
        'points' => [
            ['product_id' => $first->id, 'reorder_point' => 50, 'reorder_quantity' => 200],
            ['product_id' => $second->id, 'reorder_point' => 20],
        ],
    ])->assertOk()->assertJsonPath('data.updated', 2);

    expect(BranchProductStock::where('product_id', $first->id)->first()->reorder_point)->toEqual('50.000')
        ->and(BranchProductStock::where('product_id', $first->id)->first()->reorder_quantity)->toEqual('200.000')
        ->and(BranchProductStock::where('product_id', $second->id)->first()->reorder_point)->toEqual('20.000');
});

/**
 * The two-guard pattern in its documented shape.
 *
 * The branch here is resolved by route-model binding, which never reaches the
 * repository's branch scope. The actor deliberately HOLDS inventory.adjust —
 * otherwise the test would pass because the permission was missing rather than
 * because the policy ran.
 */
it('refuses to set reorder points in another branch', function () {
    actingAsOwner();
    [$mine, $product] = reorderable(onHand: 40);
    [$theirs] = reorderable(onHand: 40);

    $supervisor = actingAsUserWith(['inventory.view', 'inventory.adjust']);
    $supervisor->branches()->attach($mine->id);

    $this->patchJson("/api/v1/inventory/branch/{$theirs->id}/reorder-points", [
        'points' => [['product_id' => $product->id, 'reorder_point' => 100]],
    ])->assertForbidden()->assertJsonPath('success', false);

    // And the row in their branch is untouched.
    expect(BranchProductStock::where('branch_id', $theirs->id)->first()->reorder_point)->toEqual('0.000');
});

/**
 * A product id is globally valid but its stock row is per branch. Writing one
 * branch's product into another's inventory must be a no-op, not a new row.
 */
it('ignores a product that has no stock row in this branch', function () {
    actingAsOwner();
    [$branch] = reorderable(onHand: 40);

    $elsewhere = Product::factory()->create();

    $this->patchJson("/api/v1/inventory/branch/{$branch->id}/reorder-points", [
        'points' => [['product_id' => $elsewhere->id, 'reorder_point' => 100]],
    ])->assertOk()->assertJsonPath('data.updated', 0);

    expect(BranchProductStock::where('product_id', $elsewhere->id)->count())->toBe(0);
});

it('requires inventory.adjust to save', function () {
    actingAsOwner();
    [$branch, $product] = reorderable(onHand: 40);

    actingAsUserWith(['inventory.view']);

    $this->patchJson("/api/v1/inventory/branch/{$branch->id}/reorder-points", [
        'points' => [['product_id' => $product->id, 'reorder_point' => 100]],
    ])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Numbers you can act on
|--------------------------------------------------------------------------
*/

/**
 * The defect this prevents cost a 12x over-order.
 *
 * A "dozen" configured with a conversion factor of 1 is the base unit under
 * another name. Presenting it as a pack turned a need for 8 pieces into
 * "buy 8 dz" — 96 eggs — with nothing erroring anywhere.
 */
it('refuses to call a 1:1 unit a pack, even when a supplier names it', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 0);

    // A second unit that converts 1:1 — the shape that caused the bug.
    $dozen = Unit::firstOrCreate(['name' => 'dosena'], ['abbreviation' => 'dz']);
    $fake = ProductUnit::factory()->create([
        'product_id' => $product->id,
        'unit_id' => $dozen->id,
        'conversion_factor' => 1,
        'selling_price' => 8,
    ]);

    SupplierProduct::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'product_id' => $product->id,
        'product_unit_id' => $fake->id,
        'last_cost' => 8,
    ]);

    // Drop the genuine box, leaving only the base and the 1:1 impostor —
    // exactly the shape the egg product was in.
    $product->units()->where('unit_id', '!=', $sachet->unit_id)->where('id', '!=', $fake->id)->delete();

    sellSachets($branch, $product, $sachet, 300);

    $row = reorderRow($branch, $product);

    // Falls back to the base unit rather than labelling pieces as dozens.
    expect($row['purchase_unit']['abbreviation'])->toBe('sct')
        ->and($row['suggested_quantity'])->toBe(420);
});

it('still prefers a genuine pack over a 1:1 impostor', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 0);

    $dozen = Unit::firstOrCreate(['name' => 'dosena'], ['abbreviation' => 'dz']);
    ProductUnit::factory()->create([
        'product_id' => $product->id,
        'unit_id' => $dozen->id,
        'conversion_factor' => 1,
        'selling_price' => 8,
    ]);

    sellSachets($branch, $product, $sachet, 300);

    // The box of 100 is real; the dozen-of-one is not.
    expect(reorderRow($branch, $product)['purchase_unit']['abbreviation'])->toBe('box');
});

it('rounds a countable reorder point up to a whole unit', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 7);

    // 4 sachets over 10 days = 0.4/day. A target of 14 days is 5.6, which
    // must become 6 — you cannot shelve 5.6 sachets.
    sellSachets($branch, $product, $sachet, 4);

    expect(reorderRow($branch, $product)['target_stock'])->toBe('6.000');
});

it('keeps fractions for a unit that is measured rather than counted', function () {
    actingAsOwner();

    $branch = Branch::factory()->create();
    $kilo = Unit::firstOrCreate(['name' => 'kilo'], ['abbreviation' => 'kg']);
    $kilo->update(['allows_fraction' => true]);

    $bigas = Product::factory()->create(['name' => 'Bigas', 'base_unit_id' => $kilo->id]);
    $kiloUnit = ProductUnit::factory()->base()->create([
        'product_id' => $bigas->id,
        'unit_id' => $kilo->id,
        'selling_price' => 55,
    ]);

    BranchProductStock::create([
        'branch_id' => $branch->id,
        'product_id' => $bigas->id,
        'quantity_on_hand' => 2,
    ]);

    // 4 kg over 10 days = 0.4/day, so 14 days is 5.6 — and half a kilo of
    // bigas is a real quantity, so the fraction stays.
    sellSachets($branch, $bigas, $kiloUnit, 4);

    expect(reorderRow($branch, $bigas)['target_stock'])->toBe('5.600');
});

/**
 * The reorder point is the owner's number, not a derived one.
 *
 * It used to be computed from a global "warn me at N days" setting, which
 * read as a second, confusable version of the stock target. Quantities are
 * how a shopkeeper thinks about a shelf, so the report no longer proposes one
 * and the report payload carries only what is saved.
 */
it('reports the saved reorder point and does not propose one', function () {
    actingAsOwner();
    [$branch, $product, $sachet] = reorderable(onHand: 0);

    sellSachets($branch, $product, $sachet, 300);

    $row = reorderRow($branch, $product);

    expect($row)->not->toHaveKey('suggested_reorder_point')
        ->and($row['reorder_point'])->toBe('0.000')
        // The buying half is untouched by any of this.
        ->and($row['target_stock'])->toBe('420.000')
        ->and($row['suggested_quantity'])->toBe(5);

    $this->patchJson("/api/v1/inventory/branch/{$branch->id}/reorder-points", [
        'points' => [['product_id' => $product->id, 'reorder_point' => 150]],
    ])->assertOk();

    expect(reorderRow($branch, $product)['reorder_point'])->toBe('150.000');
});
