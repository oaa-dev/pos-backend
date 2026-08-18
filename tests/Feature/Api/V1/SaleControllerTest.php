<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\CashDrawerService;
use App\Services\StockService;
use Illuminate\Support\Str;

/**
 * @return array{Branch, Product, ProductUnit}
 */
function apiSellable(): array
{
    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create(['name' => 'Yelo', 'base_unit_id' => $unit->id]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 10,
    ]);

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 50, unitCost: 6);
    app(CashDrawerService::class)->open($branch, 500);

    return [$branch, $product, $productUnit];
}

function apiCart(Branch $branch, ProductUnit $unit, array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'branch_id' => $branch->id,
        'items' => [
            ['product_id' => $unit->product_id, 'product_unit_id' => $unit->id, 'quantity' => 2],
        ],
        'payments' => [['method' => 'cash', 'amount' => 20]],
        'amount_tendered' => 50,
    ], $overrides);
}

it('records a sale through the API', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();

    $this->postJson('/api/v1/sale', apiCart($branch, $unit))
        ->assertCreated()
        ->assertJsonPath('data.total', '20.00')
        ->assertJsonPath('data.change_due', '30.00')
        ->assertJsonCount(1, 'data.items');
});

/**
 * A retried submit must not charge twice. 201 on the genuine create, 200 on
 * the repeat, so the terminal can tell them apart.
 */
it('returns 200 and the original sale on a repeated uuid', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();

    $payload = apiCart($branch, $unit);

    $first = $this->postJson('/api/v1/sale', $payload)->assertCreated();
    $second = $this->postJson('/api/v1/sale', $payload)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Sale::count())->toBe(1);
});

it('returns an envelope when no shift is open', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create(['base_unit_id' => $unit->id]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 10,
    ]);
    app(StockService::class)->openingBalance($branch, $product, $productUnit, 10, unitCost: 6);

    $this->postJson('/api/v1/sale', apiCart($branch, $productUnit))
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('exception');
});

it('returns an envelope when stock runs out', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();

    $this->postJson('/api/v1/sale', apiCart($branch, $unit, [
        'items' => [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'quantity' => 500]],
        'payments' => [['method' => 'cash', 'amount' => 5000]],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('exception');
});

it('requires the logbook fields on a senior discount', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();

    $this->postJson('/api/v1/sale', apiCart($branch, $unit, [
        // No id_number or customer_name.
        'discounts' => [['type' => 'senior']],
    ]))->assertStatus(422);
});

// --- void ------------------------------------------------------------------

it('voids a sale with a valid approval PIN', function () {
    $owner = actingAsOwner();
    app(ApprovalService::class)->setPin($owner, '1234');
    [$branch, $product, $unit] = apiSellable();

    $sale = $this->postJson('/api/v1/sale', apiCart($branch, $unit))->json('data.id');

    $this->postJson("/api/v1/sale/{$sale}/void", [
        'reason' => 'Nagkamali',
        'approval_pin' => '1234',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'voided');

    expect(Sale::find($sale)->approved_by)->toBe($owner->id);
});

it('refuses a void with the wrong PIN', function () {
    $owner = actingAsOwner();
    app(ApprovalService::class)->setPin($owner, '1234');
    [$branch, $product, $unit] = apiSellable();

    $sale = $this->postJson('/api/v1/sale', apiCart($branch, $unit))->json('data.id');

    $this->postJson("/api/v1/sale/{$sale}/void", [
        'reason' => 'Nagkamali',
        'approval_pin' => '9999',
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect(Sale::find($sale)->status)->toBe('completed');
});

/**
 * A matching PIN alone must never authorise anything — the holder also has to
 * be allowed to perform the action.
 */
it('refuses a PIN belonging to someone without sales.void', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();

    $tindera = User::factory()->create();
    $tindera->forceFill(['role_id' => actingAsUserWith(['sales.create'])->role_id])->save();
    app(ApprovalService::class)->setPin($tindera, '5555');

    $owner = actingAsOwner();
    $sale = $this->postJson('/api/v1/sale', apiCart($branch, $unit))->json('data.id');

    $this->postJson("/api/v1/sale/{$sale}/void", [
        'reason' => 'Test',
        'approval_pin' => '5555',
    ])->assertForbidden();
});

it('rate limits repeated PIN failures', function () {
    $owner = actingAsOwner();
    app(ApprovalService::class)->setPin($owner, '1234');
    [$branch, $product, $unit] = apiSellable();

    $sale = $this->postJson('/api/v1/sale', apiCart($branch, $unit))->json('data.id');

    foreach (range(1, 5) as $ignored) {
        $this->postJson("/api/v1/sale/{$sale}/void", [
            'reason' => 'x',
            'approval_pin' => '0000',
        ])->assertForbidden();
    }

    // The sixth is refused by the limiter rather than by the PIN check.
    $this->postJson("/api/v1/sale/{$sale}/void", [
        'reason' => 'x',
        'approval_pin' => '1234',
    ])->assertStatus(429)->assertJsonPath('success', false);
});

// --- permissions and scoping ----------------------------------------------

it('hides unit cost from a user without products.cost', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();
    $saleId = $this->postJson('/api/v1/sale', apiCart($branch, $unit))->json('data.id');

    $tindera = actingAsUserWith(['sales.view', 'branches.view']);
    $tindera->branches()->attach($branch->id);

    $item = $this->getJson("/api/v1/sale/{$saleId}")->assertOk()->json('data.items.0');

    expect($item)->not->toHaveKey('unit_cost')
        ->and($item)->not->toHaveKey('gross_profit');
});

it('scopes the sales list to the users branch', function () {
    actingAsOwner();
    [$mine, $product, $unit] = apiSellable();
    $this->postJson('/api/v1/sale', apiCart($mine, $unit))->assertCreated();

    [$theirs, $otherProduct, $otherUnit] = apiSellable();
    $this->postJson('/api/v1/sale', apiCart($theirs, $otherUnit))->assertCreated();

    $tindera = actingAsUserWith(['sales.view']);
    $tindera->branches()->attach($mine->id);

    $rows = $this->getJson('/api/v1/sales')->assertOk()->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['branch_id'])->toBe($mine->id);
});

/**
 * Route-model binding never reaches the repository scope — the policy is what
 * stops a tindera reading another branch's sale by id.
 */
it('refuses to show a sale from another branch', function () {
    actingAsOwner();
    [$mine, $p1, $u1] = apiSellable();
    [$theirs, $p2, $u2] = apiSellable();

    $theirSale = $this->postJson('/api/v1/sale', apiCart($theirs, $u2))->json('data.id');

    $tindera = actingAsUserWith(['sales.view']);
    $tindera->branches()->attach($mine->id);

    $this->getJson("/api/v1/sale/{$theirSale}")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('requires sales.create to record a sale', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();

    actingAsUserWith(['sales.view']);

    $this->postJson('/api/v1/sale', apiCart($branch, $unit))
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

// --- shifts ----------------------------------------------------------------

it('opens and closes a shift through the API', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    $session = $this->postJson('/api/v1/shift/open', [
        'branch_id' => $branch->id,
        'opening_float' => 1000,
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/shift/{$session}/close", [
        'closing_counted' => 980,
        'closing_notes' => 'Kulang ng 20',
    ])
        ->assertOk()
        ->assertJsonPath('data.variance', '-20.00')
        ->assertJsonPath('data.status', 'closed');
});

it('reports the current open shift for a branch', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    $this->getJson("/api/v1/shifts/branch/{$branch->id}/current")
        ->assertOk()
        ->assertJsonPath('data', null);

    $this->postJson('/api/v1/shift/open', ['branch_id' => $branch->id, 'opening_float' => 500]);

    $this->getJson("/api/v1/shifts/branch/{$branch->id}/current")
        ->assertOk()
        ->assertJsonPath('data.status', 'open');
});

it('counts cash sales into the expected cash at close', function () {
    actingAsOwner();
    [$branch, $product, $unit] = apiSellable();

    $this->postJson('/api/v1/sale', apiCart($branch, $unit))->assertCreated();

    $session = app(CashDrawerService::class)->findOpenForBranch($branch->id);

    // 500 opening + 20 cash sale.
    $this->postJson("/api/v1/shift/{$session->id}/close", ['closing_counted' => 520])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', '520.00')
        ->assertJsonPath('data.variance', '0.00');
});
