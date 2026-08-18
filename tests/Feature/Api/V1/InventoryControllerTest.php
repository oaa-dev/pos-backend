<?php

use App\Enums\CashMovementTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\CashDrawerService;
use App\Services\StockService;

/**
 * @return array{Branch, Product, ProductUnit}
 */
function inventoryFixture(): array
{
    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create(['name' => 'Gatas Bear Brand', 'base_unit_id' => $unit->id]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 32,
    ]);

    return [$branch, $product, $productUnit];
}

it('receives stock through the API', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    $this->postJson('/api/v1/inventory/receive', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'product_unit_id' => $productUnit->id,
        'quantity' => 24,
        'unit_cost' => 28,
        'expiry_date' => '2026-11-30',
    ])->assertCreated()->assertJsonPath('data.quantity_base', '24.000');

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('24.000')
        ->and(ProductBatch::first()->expiry_date->toDateString())->toBe('2026-11-30');
});

/**
 * The stock-in screen is "Opening balance / stock in" — stock counted in with
 * no supplier and no money behind it. It used to call the generic
 * `StockService::receive()`, which defaulted the type to `PURCHASE_RECEIPT`, so
 * every opening balance was written to the ledger as a delivery.
 *
 * Asserting a movement was *created* passes either way. The type is the whole
 * assertion.
 */
it('records a stock-in as an opening balance, not a delivery', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    $this->postJson('/api/v1/inventory/receive', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'product_unit_id' => $productUnit->id,
        'quantity' => 10,
        'unit_cost' => 7,
        'batch_code' => 'OB-0001',
        'note' => 'Counted in with Aling Nena',
    ])->assertCreated();

    $movement = StockMovement::latest('id')->first();

    expect($movement->type)->toBe(StockMovementTypeEnum::OPENING_BALANCE)
        // Carried through rather than dropped: `openingBalance()` took neither
        // of these until it was wired up, and losing them silently would be the
        // same class of bug as the mislabelling.
        ->and($movement->note)->toBe('Counted in with Aling Nena')
        ->and(ProductBatch::latest('id')->first()->batch_code)->toBe('OB-0001');
});

/**
 * The other half of the pair. With purchasing gone there is one screen, so the
 * distinction is what the owner fills in rather than which endpoint she hit:
 * naming a supplier or a payment makes it a purchase.
 */
it('records a stock-in with a supplier as a purchase receipt', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    $supplier = Supplier::factory()->create(['store_id' => testStore()->id]);

    $this->postJson('/api/v1/inventory/receive', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'product_unit_id' => $productUnit->id,
        'quantity' => 5,
        'unit_cost' => 20,
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    expect(StockMovement::latest('id')->first()->type)
        ->toBe(StockMovementTypeEnum::PURCHASE_RECEIPT);
});

/**
 * The money is the reason this path exists rather than a bare stock movement.
 * A delivery paid from the till has to reach the shift, or the drawer comes up
 * short at close and reads as a shortage.
 */
it('charges the drawer when a stock-in is paid from it', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(CashDrawerService::class)->open($branch, 5000);

    $this->postJson('/api/v1/inventory/receive', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'product_unit_id' => $productUnit->id,
        'quantity' => 5,
        'unit_cost' => 20,
        'paid_from' => 'drawer',
    ])->assertCreated();

    expect(CashMovement::where('type', CashMovementTypeEnum::SUPPLIER_PAYMENT->value)->count())->toBe(1);
});

it('leaves the drawer alone for a plain opening balance', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(CashDrawerService::class)->open($branch, 5000);

    $this->postJson('/api/v1/inventory/receive', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'product_unit_id' => $productUnit->id,
        'quantity' => 5,
        'unit_cost' => 20,
    ])->assertCreated();

    expect(CashMovement::where('type', CashMovementTypeEnum::SUPPLIER_PAYMENT->value)->count())->toBe(0)
        ->and(StockMovement::latest('id')->first()->type)
        ->toBe(StockMovementTypeEnum::OPENING_BALANCE);
});

/**
 * The envelope matters as much as the status. A domain exception that escapes
 * the ApiResponse shape leaks a stack trace while still passing a status-only
 * assertion.
 */
it('returns an envelope when there is not enough stock', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 5, unitCost: 28);

    $this->postJson('/api/v1/inventory/adjust', [
        'branch_id' => $branch->id,
        'reason' => 'spoilage',
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => -50],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('exception');

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('5.000');
});

it('records an adjustment and maps the reason to a movement type', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 20, unitCost: 28);

    $this->postJson('/api/v1/inventory/adjust', [
        'branch_id' => $branch->id,
        'reason' => 'expired',
        'note' => 'Nag-expire ang gatas',
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => -3],
        ],
    ])->assertCreated();

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('17.000')
        ->and(StockMovement::latest('id')->first()->type->value)->toBe('expiry_writeoff');
});

it('adds stock back on a positive adjustment at the current average cost', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 10, unitCost: 28);

    $this->postJson('/api/v1/inventory/adjust', [
        'branch_id' => $branch->id,
        'reason' => 'physical_count',
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => 4],
        ],
    ])->assertCreated();

    $stock = BranchProductStock::first();

    expect($stock->quantity_on_hand)->toEqual('14.000')
        // A recount must not devalue the stock it restores.
        ->and($stock->average_cost)->toEqual('28.0000');
});

it('rejects an adjustment with a zero quantity', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    $this->postJson('/api/v1/inventory/adjust', [
        'branch_id' => $branch->id,
        'reason' => 'correction',
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => 0],
        ],
    ])->assertStatus(422);
});

it('rejects an unknown adjustment reason', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    $this->postJson('/api/v1/inventory/adjust', [
        'branch_id' => $branch->id,
        'reason' => 'kinain-ko',
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => -1],
        ],
    ])->assertStatus(422);
});

it('lists low stock against the reorder point', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 5, unitCost: 28);
    BranchProductStock::first()->update(['reorder_point' => 10]);

    $response = $this->getJson("/api/v1/inventory/branch/{$branch->id}/low-stock")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.is_low'))->toBeTrue();
});

it('does not treat a product with no reorder point as low', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 0.5, unitCost: 28);

    expect($this->getJson("/api/v1/inventory/branch/{$branch->id}/low-stock")->json('data'))->toHaveCount(0);
});

it('lists batches expiring within the window', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    $stock = app(StockService::class);
    $stock->openingBalance($branch, $product, $productUnit, 5, unitCost: 28, expiryDate: now()->addDays(10)->toDateString());
    $stock->openingBalance($branch, $product, $productUnit, 5, unitCost: 29, expiryDate: now()->addDays(90)->toDateString());

    $soon = $this->getJson("/api/v1/inventory/branch/{$branch->id}/expiring?days=30")->assertOk();

    expect($soon->json('data'))->toHaveCount(1)
        ->and($soon->json('data.0.days_to_expiry'))->toBe(10);
});

// --- permissions and scoping ----------------------------------------------

it('hides unit cost from a user without products.cost', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();
    app(StockService::class)->openingBalance($branch, $product, $productUnit, 10, unitCost: 28);

    // The owner sees cost.
    expect($this->getJson('/api/v1/inventory/stocks')->json('data.0'))->toHaveKey('average_cost');

    // A tindera must not — it is the markup in disguise.
    $tindera = actingAsUserWith(['inventory.view', 'branches.view']);
    $tindera->branches()->attach($branch->id);

    $row = $this->getJson('/api/v1/inventory/stocks')->assertOk()->json('data.0');

    expect($row)->not->toHaveKey('average_cost');
});

/**
 * stock_movements is the first repository to use BranchScopedInterface with a
 * real branch_id, so this is the critical pattern's first true test.
 */
it('scopes stock movements to the users branch', function () {
    actingAsOwner();
    [$mine, $product, $productUnit] = inventoryFixture();
    $theirs = Branch::factory()->create();

    $stock = app(StockService::class);
    $stock->openingBalance($mine, $product, $productUnit, 10, unitCost: 28);
    $stock->openingBalance($theirs, $product, $productUnit, 7, unitCost: 28);

    // Owner holds branches.view-all and sees both.
    expect($this->getJson('/api/v1/inventory/movements')->json('data'))->toHaveCount(2);

    $tindera = actingAsUserWith(['inventory.view']);
    $tindera->branches()->attach($mine->id);

    $scoped = $this->getJson('/api/v1/inventory/movements')->assertOk()->json('data');

    expect($scoped)->toHaveCount(1)
        ->and($scoped[0]['branch_id'])->toBe($mine->id);
});

it('scopes stock levels and batches to the users branch', function () {
    actingAsOwner();
    [$mine, $product, $productUnit] = inventoryFixture();
    $theirs = Branch::factory()->create();

    $stock = app(StockService::class);
    $stock->openingBalance($mine, $product, $productUnit, 10, unitCost: 28);
    $stock->openingBalance($theirs, $product, $productUnit, 7, unitCost: 28);

    $tindera = actingAsUserWith(['inventory.view']);
    $tindera->branches()->attach($mine->id);

    expect($this->getJson('/api/v1/inventory/stocks')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/inventory/batches')->json('data'))->toHaveCount(1);
});

it('requires the inventory permission', function () {
    actingAsUserWith(['sales.create']);

    $this->getJson('/api/v1/inventory/stocks')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('requires inventory.adjust to receive or adjust stock', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    actingAsUserWith(['inventory.view']);

    $this->postJson('/api/v1/inventory/receive', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'product_unit_id' => $productUnit->id,
        'quantity' => 1,
    ])->assertForbidden()->assertJsonPath('success', false);
});

// --- reconcile command -----------------------------------------------------

it('reports drift from the reconcile command', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 100, unitCost: 28);
    BranchProductStock::first()->update(['quantity_on_hand' => 80]);

    $this->artisan('inventory:reconcile')
        ->expectsOutputToContain('drifted')
        ->assertFailed();

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('100.000');
});

it('passes cleanly when the cache matches the ledger', function () {
    actingAsOwner();
    [$branch, $product, $productUnit] = inventoryFixture();

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 100, unitCost: 28);

    $this->artisan('inventory:reconcile')
        ->expectsOutputToContain('No drift')
        ->assertSuccessful();
});
