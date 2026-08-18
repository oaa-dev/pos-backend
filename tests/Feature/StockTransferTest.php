<?php

use App\Data\StockTransferData;
use App\Enums\StockMovementTypeEnum;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\StockService;
use App\Services\StockTransferService;

/**
 * @return array{Branch, Branch, Product, ProductUnit}
 */
function transferable(): array
{
    $from = Branch::factory()->create(['store_id' => testStore()->id]);
    $to = Branch::factory()->create(['store_id' => testStore()->id]);

    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);

    $product = Product::factory()->create([
        'store_id' => testStore()->id,
        'name' => 'Kopiko Blanca',
        'base_unit_id' => $sachet->id,
    ]);

    $unit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $sachet->id,
        'selling_price' => 8,
    ]);

    return [$from, $to, $product, $unit];
}

/**
 * The same, in another store. Used for the isolation tests, which need a
 * complete second set — a transfer reaches its store through its branches.
 *
 * @return array{Branch, Branch, Product, ProductUnit}
 */
function transferableElsewhere(): array
{
    $other = anotherStore();
    $from = Branch::factory()->create(['store_id' => $other->id]);
    $to = Branch::factory()->create(['store_id' => $other->id]);

    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);

    $product = Product::factory()->create(['store_id' => $other->id, 'base_unit_id' => $sachet->id]);
    $unit = ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $sachet->id,
    ]);

    return [$from, $to, $product, $unit];
}

function transfers(): StockTransferService
{
    return app(StockTransferService::class);
}

/**
 * Creating a transfer moves the stock, so every one of these needs the origin
 * stocked first — an unstocked origin raises `InsufficientStockException` at
 * create time rather than at some later send.
 */
function stockUp(Branch $branch, Product $product, ProductUnit $unit, $quantity, $unitCost = 50): void
{
    app(StockService::class)->openingBalance($branch, $product, $unit, $quantity, unitCost: $unitCost);
}

function makeTransfer(Branch $from, Branch $to, Product $product, ProductUnit $unit, $quantity): StockTransfer
{
    return transfers()->create(StockTransferData::from([
        'from_branch_id' => $from->id,
        'to_branch_id' => $to->id,
        'items' => [[
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => $quantity,
        ]],
    ]));
}

/**
 * The headline behaviour. There is no `send` and no `receive` — a transfer is
 * requested and the stock has already moved by the time the response is written.
 *
 * Asserting the status alone would not be enough: the row could reach `received`
 * with neither leg written. The two ledger legs and both branches' stock are
 * what prove it.
 */
it('moves the stock on create, with no second call', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 10);

    $transfer = makeTransfer($from, $to, $product, $unit, 4);

    expect($transfer->status)->toBe(StockTransfer::RECEIVED)
        ->and($transfer->sent_at)->not->toBeNull()
        ->and($transfer->received_at)->not->toBeNull();

    expect(BranchProductStock::where('branch_id', $from->id)->first()->quantity_on_hand)
        ->toEqual('6.000')
        ->and(BranchProductStock::where('branch_id', $to->id)->first()->quantity_on_hand)
        ->toEqual('4.000');

    $legs = StockMovement::where('reference_type', $transfer->getMorphClass())
        ->where('reference_id', $transfer->id)
        ->pluck('type');

    // `type` is cast to the enum, so these are cases and not strings.
    expect($legs)->toHaveCount(2)
        ->and($legs)->toContain(StockMovementTypeEnum::TRANSFER_OUT)
        ->and($legs)->toContain(StockMovementTypeEnum::TRANSFER_IN);
});

/**
 * `create()` never set `status`, so the row took the column default while the
 * returned instance carried null. The controller serialises exactly that
 * instance, so the screen read a transfer with no status at all.
 */
it('returns a fully stamped instance, without re-reading it', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 10);

    // Deliberately not `->fresh()`.
    $transfer = makeTransfer($from, $to, $product, $unit, 4);

    expect($transfer->status)->toBe($transfer->fresh()->status);
});

/**
 * The one that matters. The receive leg passed `0` as the sixth positional
 * argument of `StockService::receive()` — which is the cost — so transferred
 * stock arrived free and dragged the destination's average cost to zero.
 */
it('carries the cost across the transfer', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 10);

    makeTransfer($from, $to, $product, $unit, 4);

    $destination = BranchProductStock::where('branch_id', $to->id)->first();

    expect($destination->quantity_on_hand)->toEqual('4.000')
        // ₱0.0000 before the fix.
        ->and($destination->average_cost)->toEqual('50.0000');

    // The origin keeps its own cost basis; only the quantity left.
    $origin = BranchProductStock::where('branch_id', $from->id)->first();

    expect($origin->quantity_on_hand)->toEqual('6.000')
        ->and($origin->average_cost)->toEqual('50.0000');
});

/**
 * FEFO draws from the oldest batch first, so one transferred item can span
 * several batches at different costs. Each is mirrored rather than averaged —
 * flattening two expiry dates into one would quietly extend the shelf life of
 * the older half.
 */
it('mirrors each batch with its own cost and expiry', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    $stock = app(StockService::class);

    // Two batches: the older, cheaper one is drawn first.
    $stock->openingBalance($from, $product, $unit, 6, unitCost: 50, expiryDate: '2026-09-30');
    $stock->openingBalance($from, $product, $unit, 4, unitCost: 55, expiryDate: '2026-12-31');

    makeTransfer($from, $to, $product, $unit, 10);

    $batches = ProductBatch::where('branch_id', $to->id)->orderBy('expiry_date')->get();

    expect($batches)->toHaveCount(2)
        ->and($batches[0]->unit_cost)->toEqual('50.0000')
        ->and($batches[0]->expiry_date->toDateString())->toBe('2026-09-30')
        ->and($batches[1]->unit_cost)->toEqual('55.0000')
        ->and($batches[1]->expiry_date->toDateString())->toBe('2026-12-31');

    // 6 at ₱50 and 4 at ₱55 weights to ₱52.
    expect(BranchProductStock::where('branch_id', $to->id)->first()->average_cost)
        ->toEqual('52.0000');
});

/**
 * The transferred quantity is in base units. Receiving it against the item's
 * own selling unit rather than the base one would multiply it by the
 * conversion factor.
 */
it('does not multiply the quantity by the conversion factor', function () {
    actingAsOwner();
    [$from, $to, $product] = transferable();

    $box = Unit::firstOrCreate(['name' => 'box'], ['abbreviation' => 'box']);
    $boxUnit = ProductUnit::factory()->create([
        'product_id' => $product->id,
        'unit_id' => $box->id,
        'conversion_factor' => 100,
        'selling_price' => 700,
    ]);

    stockUp($from, $product, $boxUnit, 2, unitCost: 700);

    // Two boxes = 200 sachets. Transfer one box.
    makeTransfer($from, $to, $product, $boxUnit, 1);

    expect(BranchProductStock::where('branch_id', $to->id)->first()->quantity_on_hand)
        ->toEqual('100.000');
});

/**
 * The write path, and now the *only* guard.
 *
 * `send` and `receive` were route-model bound and `StockTransferPolicy` checked
 * the store on the way through. Both are gone: creating the transfer is what
 * moves the stock, so `StoreStockTransferRequest` scopes each branch id to the
 * actor's own store. Without it, posting another customer's branch ids empties
 * their shelves.
 */
it('refuses to move stock out of another store’s branch', function () {
    actingAsOwner();
    [, $to, $product, $unit] = transferable();
    [$theirBranch] = transferableElsewhere();

    $this->postJson('/api/v1/stock-transfer', [
        'from_branch_id' => $theirBranch->id,
        'to_branch_id' => $to->id,
        'items' => [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'quantity' => 1]],
    ])->assertJsonValidationErrors('from_branch_id');

    expect(StockTransfer::count())->toBe(0);
});

it('refuses to move stock into another store’s branch', function () {
    actingAsOwner();
    [$from, , $product, $unit] = transferable();
    [$theirBranch] = transferableElsewhere();

    stockUp($from, $product, $unit, 10);

    $this->postJson('/api/v1/stock-transfer', [
        'from_branch_id' => $from->id,
        'to_branch_id' => $theirBranch->id,
        'items' => [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'quantity' => 1]],
    ])->assertJsonValidationErrors('to_branch_id');

    expect(StockTransfer::count())->toBe(0);
});

it('completes a transfer between two of the actor’s own branches', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 10);

    $this->postJson('/api/v1/stock-transfer', [
        'from_branch_id' => $from->id,
        'to_branch_id' => $to->id,
        'items' => [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'quantity' => 4]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', StockTransfer::RECEIVED);

    expect(BranchProductStock::where('branch_id', $to->id)->first()->quantity_on_hand)
        ->toEqual('4.000');
});

/**
 * The listing has no `store_id` of its own — a transfer reaches its store
 * through the origin branch. Unfiltered it returns every customer's transfers.
 */
it('excludes another store’s transfers from a store-filtered listing', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();
    [$theirFrom, $theirTo, $theirProduct, $theirUnit] = transferableElsewhere();

    stockUp($from, $product, $unit, 5);
    stockUp($theirFrom, $theirProduct, $theirUnit, 5);

    makeTransfer($from, $to, $product, $unit, 1);
    makeTransfer($theirFrom, $theirTo, $theirProduct, $theirUnit, 1);

    $numbers = collect(
        $this->getJson('/api/v1/stock-transfers?filter[store_id]='.testStore()->id)->json('data')
    )->pluck('transfer_number');

    expect($numbers)->toHaveCount(1);
});

it('paginates the transfer listing', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 5);
    makeTransfer($from, $to, $product, $unit, 1);

    // The index used to query Eloquent in the controller and return raw models
    // with `->get()`. The list screen needs the paginated envelope.
    $this->getJson('/api/v1/stock-transfers')
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data', 'links', 'meta']);
});

/**
 * The report asks "what moved in or out of this branch", which neither
 * `from_branch_id` nor `to_branch_id` can answer on its own.
 */
it('filters the listing by either end of the transfer', function () {
    actingAsOwner();
    [$a, $b, $product, $unit] = transferable();
    $c = Branch::factory()->create(['store_id' => testStore()->id]);

    stockUp($a, $product, $unit, 10);
    stockUp($c, $product, $unit, 10);

    $out = makeTransfer($a, $b, $product, $unit, 1);   // leaves B's counterpart
    $in = makeTransfer($c, $b, $product, $unit, 1);    // arrives at B
    $unrelated = makeTransfer($a, $c, $product, $unit, 1);

    $numbers = collect(
        $this->getJson('/api/v1/stock-transfers?filter[branch_id]='.$b->id)->json('data')
    )->pluck('transfer_number');

    expect($numbers)->toHaveCount(2)
        ->and($numbers)->toContain($out->transfer_number)
        ->and($numbers)->toContain($in->transfer_number)
        ->and($numbers)->not->toContain($unrelated->transfer_number);
});

/**
 * The `orWhere` pair inside the `branch_id` filter is nested in its own closure.
 * Unnested, `AND` binds tighter than `OR` and the store constraint applies to
 * only one half — so asking for another store's branch id would return their
 * transfers regardless of the store filter.
 */
it('does not let the branch filter escape the store filter', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();
    [$theirFrom, $theirTo, $theirProduct, $theirUnit] = transferableElsewhere();

    stockUp($from, $product, $unit, 5);
    stockUp($theirFrom, $theirProduct, $theirUnit, 5);

    makeTransfer($from, $to, $product, $unit, 1);
    makeTransfer($theirFrom, $theirTo, $theirProduct, $theirUnit, 1);

    $rows = $this->getJson(
        '/api/v1/stock-transfers?filter[store_id]='.testStore()->id.'&filter[branch_id]='.$theirFrom->id
    )->json('data');

    expect($rows)->toBeEmpty();
});

it('bounds the listing by transfer date', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 10);

    $old = makeTransfer($from, $to, $product, $unit, 1);
    $old->forceFill(['created_at' => now()->subDays(10)])->save();

    $recent = makeTransfer($from, $to, $product, $unit, 1);

    $numbers = collect(
        $this->getJson('/api/v1/stock-transfers?filter[transferred_from]='.now()->subDays(2)->toDateString())
            ->json('data')
    )->pluck('transfer_number');

    expect($numbers)->toHaveCount(1)
        ->and($numbers)->toContain($recent->transfer_number)
        ->and($numbers)->not->toContain($old->transfer_number);
});

/**
 * The report's "how many" column. Every path here must be in
 * `allowedIncludes()` — Spatie 400s on one that is not, so a missing entry
 * breaks the report at runtime with nothing failing at build time.
 */
it('includes the line items, their product and their unit', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 10);
    makeTransfer($from, $to, $product, $unit, 4);

    $row = $this->getJson('/api/v1/stock-transfers?include=items.product,items.productUnit.unit')
        ->assertOk()
        ->json('data.0');

    expect($row['items'])->toHaveCount(1)
        ->and($row['items'][0]['quantity'])->toEqual('4.000')
        ->and($row['items'][0]['product']['name'])->toBe('Kopiko Blanca')
        ->and($row['items'][0]['product_unit']['unit']['name'])->toBe('sachet');
});

it('no longer exposes the send and receive routes', function () {
    actingAsOwner();
    [$from, $to, $product, $unit] = transferable();

    stockUp($from, $product, $unit, 5);
    $transfer = makeTransfer($from, $to, $product, $unit, 1);

    $this->postJson("/api/v1/stock-transfer/{$transfer->id}/send")->assertNotFound();
    $this->postJson("/api/v1/stock-transfer/{$transfer->id}/receive")->assertNotFound();
});
