<?php

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\StockService;

function parkedCart(Branch $branch, ProductUnit $unit): array
{
    return [
        'branch_id' => $branch->id,
        'label' => 'Aling Nena',
        'payload' => [
            'items' => [
                [
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'quantity' => 3,
                    'unit_price' => '10.00',
                    'product_name' => $unit->product->name,
                    'unit_name' => $unit->unit->name,
                    'line_discount' => '0.00',
                    'discount_eligible' => true,
                    'conversion_factor' => (string) $unit->conversion_factor,
                ],
            ],
        ],
    ];
}

/** @return array{Branch, ProductUnit} */
function suspendable(): array
{
    $branch = Branch::factory()->create();
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $product = Product::factory()->create(['base_unit_id' => $unit->id]);
    $productUnit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'selling_price' => 10,
    ]);

    app(StockService::class)->openingBalance($branch, $product, $productUnit, 20, unitCost: 6);

    return [$branch, $productUnit];
}

it('suspends a cart', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $response = $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))
        ->assertCreated()
        ->assertJsonPath('data.label', 'Aling Nena')
        ->assertJsonPath('data.item_count', 1)
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonPath('data.is_expired', false);

    expect($response->json('data.expires_at'))->not->toBeNull()
        ->and($response->json('data.seconds_remaining'))->toBeBetween(899, 900);
});

/**
 * The whole point of parking rather than half-selling: nothing moves until the
 * sale completes, so a parked cart cannot oversell and a forgotten one costs
 * nothing.
 */
it('holds no stock while parked', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->assertCreated();

    expect(BranchProductStock::first()->quantity_on_hand)->toEqual('20.000')
        ->and(StockMovement::where('type', 'sale')->count())->toBe(0);
});

it('lists suspended carts for a branch', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->assertCreated();
    $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->assertCreated();

    expect($this->getJson("/api/v1/suspensions/branch/{$branch->id}")->json('data'))
        ->toHaveCount(2);
});

it('returns the parked payload on resume', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $id = $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->json('data.id');

    $response = $this->postJson("/api/v1/suspension/{$id}/resume")->assertOk();

    expect($response->json('data.status'))->toBe('resumed')
        ->and($response->json('data.payload.items.0.quantity'))->toBe(3)
        ->and($response->json('data.payload.items.0.product_name'))->toBe($unit->product->name);
});

it('resumes a parked cart within fifteen minutes', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $id = $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->json('data.id');

    $this->travel(14)->minutes();

    $this->postJson("/api/v1/suspension/{$id}/resume")
        ->assertOk()
        ->assertJsonPath('data.status', 'resumed');
});

it('refuses to resume and stops listing a cart after fifteen minutes', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $id = $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->json('data.id');

    $this->travel(16)->minutes();

    expect($this->getJson("/api/v1/suspensions/branch/{$branch->id}")->json('data'))
        ->toHaveCount(0);

    $this->postJson("/api/v1/suspension/{$id}/resume")
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'This parked cart expired after 15 minutes. Park the items again to use current prices.',
        );
});

it('drops a resumed cart out of the list', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $id = $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->json('data.id');
    $this->postJson("/api/v1/suspension/{$id}/resume")->assertOk();

    expect($this->getJson("/api/v1/suspensions/branch/{$branch->id}")->json('data'))
        ->toHaveCount(0);
});

it('refuses to resume the same cart twice', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $id = $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->json('data.id');
    $this->postJson("/api/v1/suspension/{$id}/resume")->assertOk();

    $this->postJson("/api/v1/suspension/{$id}/resume")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('discards a cart', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    $id = $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))->json('data.id');

    $this->deleteJson("/api/v1/suspension/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'discarded');

    expect($this->getJson("/api/v1/suspensions/branch/{$branch->id}")->json('data'))
        ->toHaveCount(0);
});

it('scopes suspended carts to the users branch', function () {
    actingAsOwner();
    [$mine, $mineUnit] = suspendable();
    [$theirs, $theirsUnit] = suspendable();

    $this->postJson('/api/v1/suspension', parkedCart($mine, $mineUnit))->assertCreated();
    $this->postJson('/api/v1/suspension', parkedCart($theirs, $theirsUnit))->assertCreated();

    $tindera = actingAsUserWith(['sales.suspend', 'branches.view']);
    $tindera->branches()->attach($mine->id);

    expect($this->getJson("/api/v1/suspensions/branch/{$mine->id}")->json('data'))->toHaveCount(1);

    $this->getJson("/api/v1/suspensions/branch/{$theirs->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('requires the suspend permission', function () {
    actingAsOwner();
    [$branch, $unit] = suspendable();

    actingAsUserWith(['sales.create']);

    $this->postJson('/api/v1/suspension', parkedCart($branch, $unit))
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
