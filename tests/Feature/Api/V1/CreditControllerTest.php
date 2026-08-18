<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\ApprovalService;
use App\Services\CashDrawerService;
use App\Services\CreditService;
use App\Services\StockService;
use Illuminate\Support\Str;

function creditSuki(array $attributes = []): Customer
{
    return Customer::factory()->create($attributes);
}

it('returns a statement with the ledger, aging, and overdue rows', function () {
    actingAsOwner();
    $customer = creditSuki();

    posCreditCharge($customer, 350, dueDate: now()->subDay()->toDateString());
    app(CreditService::class)->collect($customer, 100);

    $response = $this->getJson("/api/v1/credit/customer/{$customer->id}/statement")->assertOk();

    expect($response->json('data.transactions'))->toHaveCount(2)
        ->and($response->json('data.aging.total'))->toEqual('250.00')
        ->and($response->json('data.overdue'))->toHaveCount(1)
        ->and($response->json('data.customer.current_balance'))->toEqual('250.00');
});

it('does not expose a standalone cash lending endpoint', function () {
    actingAsOwner();
    $customer = creditSuki();

    $this->postJson("/api/v1/credit/customer/{$customer->id}/charge", [
        'amount' => 250,
        'note' => 'Cash advance',
    ])->assertNotFound();
});
it('collects a payment through the API', function () {
    actingAsOwner();
    $customer = creditSuki();
    posCreditCharge($customer, 500);

    $this->postJson("/api/v1/credit/customer/{$customer->id}/collect", [
        'amount' => 200,
        'note' => 'Bayad ni Aling Nena',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'payment')
        ->assertJsonPath('data.balance_after', '300.00');

    expect($customer->fresh()->current_balance)->toEqual('300.00');
});

/**
 * An utang payment lands in the drawer, so it has to count toward the expected
 * cash at close — otherwise every collecting shift reads as an overage.
 */
it('counts a collection toward the shift expected cash', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $session = app(CashDrawerService::class)->open($branch, 500);

    $customer = creditSuki();
    posCreditCharge($customer, 300);

    $this->postJson("/api/v1/credit/customer/{$customer->id}/collect", [
        'amount' => 200,
        'branch_id' => $branch->id,
    ])->assertCreated();

    // 500 opening + 200 collected.
    $this->postJson("/api/v1/shift/{$session->id}/close", ['closing_counted' => 700])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', '700.00')
        ->assertJsonPath('data.variance', '0.00');
});

it('keeps a writeoff out of the expected cash', function () {
    $owner = actingAsOwner();
    app(ApprovalService::class)->setPin($owner, '1234');

    $branch = Branch::factory()->create();
    $session = app(CashDrawerService::class)->open($branch, 500);

    $customer = creditSuki();
    posCreditCharge($customer, 300);

    $this->postJson("/api/v1/credit/customer/{$customer->id}/writeoff", [
        'amount' => 300,
        'note' => 'Hindi na mababayaran',
        'approval_pin' => '1234',
    ])->assertCreated();

    // Forgiving debt moves no money.
    $this->postJson("/api/v1/shift/{$session->id}/close", ['closing_counted' => 500])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', '500.00');

    expect($customer->fresh()->current_balance)->toEqual('0.00');
});

it('refuses a writeoff with the wrong PIN', function () {
    $owner = actingAsOwner();
    app(ApprovalService::class)->setPin($owner, '1234');

    $customer = creditSuki();
    posCreditCharge($customer, 300);

    $this->postJson("/api/v1/credit/customer/{$customer->id}/writeoff", [
        'amount' => 300,
        'note' => 'Test',
        'approval_pin' => '9999',
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect($customer->fresh()->current_balance)->toEqual('300.00');
});

it('requires credit.writeoff to forgive a balance', function () {
    actingAsOwner();
    $customer = creditSuki();

    actingAsUserWith(['credit.view', 'credit.collect']);

    $this->postJson("/api/v1/credit/customer/{$customer->id}/writeoff", [
        'amount' => 10,
        'note' => 'Sneaky',
        'approval_pin' => '1234',
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('returns an envelope when a blocked suki is charged', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create(['base_unit_id' => $unit->id]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 100,
    ]);

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 50, unitCost: 60);
    app(CashDrawerService::class)->open($branch, 0);

    $customer = Customer::factory()->blocked()->create();

    $this->postJson('/api/v1/sale', [
        'uuid' => (string) Str::uuid(),
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => 2],
        ],
        'payments' => [['method' => 'credit', 'amount' => 200]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonMissingPath('exception');
});

/**
 * A credit sale must land in the ledger, not just move the cached balance —
 * otherwise the statement would show a balance with nothing explaining it.
 */
it('writes a ledger charge for a credit sale', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create(['base_unit_id' => $unit->id]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 50,
    ]);

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 50, unitCost: 30);
    app(CashDrawerService::class)->open($branch, 0);

    $customer = creditSuki();

    $sale = $this->postJson('/api/v1/sale', [
        'uuid' => (string) Str::uuid(),
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => 2],
        ],
        'payments' => [['method' => 'credit', 'amount' => 100]],
    ])->assertCreated();

    $statement = $this->getJson("/api/v1/credit/customer/{$customer->id}/statement")->assertOk();

    expect($statement->json('data.transactions'))->toHaveCount(1)
        ->and($statement->json('data.transactions.0.type'))->toBe('charge')
        ->and($statement->json('data.transactions.0.sale_id'))->toBe($sale->json('data.id'))
        ->and($statement->json('data.aging.total'))->toEqual('100.00');
});

it('reverses the ledger charge when a credit sale is voided', function () {
    $owner = actingAsOwner();
    app(ApprovalService::class)->setPin($owner, '1234');

    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create(['base_unit_id' => $unit->id]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 50,
    ]);

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 50, unitCost: 30);
    app(CashDrawerService::class)->open($branch, 0);

    $customer = creditSuki();

    $saleId = $this->postJson('/api/v1/sale', [
        'uuid' => (string) Str::uuid(),
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => 2],
        ],
        'payments' => [['method' => 'credit', 'amount' => 100]],
    ])->json('data.id');

    $this->postJson("/api/v1/sale/{$saleId}/void", [
        'reason' => 'Nagkamali',
        'approval_pin' => '1234',
    ])->assertOk();

    $statement = $this->getJson("/api/v1/credit/customer/{$customer->id}/statement")->assertOk();

    // Charge plus its reversal — the original is never deleted.
    expect($statement->json('data.transactions'))->toHaveCount(2)
        ->and($statement->json('data.aging.total'))->toEqual('0.00');

    expect($customer->fresh()->current_balance)->toEqual('0.00');
});

it('scopes the credit ledger to the users branch', function () {
    actingAsOwner();
    $mine = Branch::factory()->create();
    $theirs = Branch::factory()->create();

    posCreditCharge(creditSuki(['branch_id' => $mine->id]), 100);
    posCreditCharge(creditSuki(['branch_id' => $theirs->id]), 200);

    expect($this->getJson('/api/v1/credit/transactions')->json('data'))->toHaveCount(2);

    $tindera = actingAsUserWith(['credit.view']);
    $tindera->branches()->attach($mine->id);

    expect($this->getJson('/api/v1/credit/transactions')->json('data'))->toHaveCount(1);
});

it('requires the credit permission', function () {
    actingAsUserWith(['sales.create']);

    $this->getJson('/api/v1/credit/transactions')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
