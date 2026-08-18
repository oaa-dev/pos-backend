<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsOwner();
});

/**
 * The worked example from the brainstorm: Coffee based in sachet, sold as
 * sachet / strip (10) / box (100).
 */
function coffeePayload(array $overrides = []): array
{
    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);
    $strip = Unit::firstOrCreate(['name' => 'strip'], ['abbreviation' => 'stp']);
    $box = Unit::firstOrCreate(['name' => 'box'], ['abbreviation' => 'box']);

    return array_merge([
        'store_id' => testStore()->id,
        'name' => 'Kopiko Blanca 3-in-1',
        'base_unit_id' => $sachet->id,
        'units' => [
            ['unit_id' => $sachet->id, 'conversion_factor' => 1, 'selling_price' => 8.00, 'is_base' => true, 'is_default_sale_unit' => true, 'sort_order' => 0],
            ['unit_id' => $strip->id, 'conversion_factor' => 10, 'selling_price' => 75.00, 'is_base' => false, 'is_default_sale_unit' => false, 'sort_order' => 1],
            ['unit_id' => $box->id, 'conversion_factor' => 100, 'selling_price' => 700.00, 'is_base' => false, 'is_default_sale_unit' => false, 'sort_order' => 2],
        ],
    ], $overrides);
}

it('creates a product with three selling units', function () {
    $response = $this->postJson('/api/v1/product', coffeePayload());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Kopiko Blanca 3-in-1')
        // The product form reads this back; ProductResponse types it as required.
        ->assertJsonPath('data.store_id', testStore()->id)
        ->assertJsonCount(3, 'data.units');

    $product = Product::with('units')->first();

    expect($product->units)->toHaveCount(3)
        ->and($product->baseProductUnit->conversion_factor)->toEqual('1.0000')
        ->and($product->defaultSaleUnit->selling_price)->toEqual('8.00');
});

/**
 * The arithmetic the whole tingi model rests on: selling 3 strips must remove
 * 30 sachets from stock, not 3.
 */
it('keeps every product stock-tracked and hides legacy service switches', function () {
    $response = $this->postJson('/api/v1/product', coffeePayload([
        'is_service' => true,
        'track_stock' => false,
    ]))->assertCreated()
        ->assertJsonMissingPath('data.is_service')
        ->assertJsonMissingPath('data.track_stock');

    $product = Product::findOrFail($response->json('data.id'));

    expect($product->is_service)->toBeFalse()
        ->and($product->track_stock)->toBeTrue();
});

it('uploads, replaces, and removes a product image', function () {
    Storage::fake('public');

    $id = $this->postJson('/api/v1/product', coffeePayload())
        ->assertCreated()
        ->json('data.id');

    $this->post("/api/v1/product/{$id}/image", [
        'image' => UploadedFile::fake()->image('coffee.png'),
    ], ['Accept' => 'application/json'])
        ->assertOk();

    $firstPath = Product::findOrFail($id)->image_path;
    Storage::disk('public')->assertExists($firstPath);

    $this->post("/api/v1/product/{$id}/image", [
        'image' => UploadedFile::fake()->image('coffee-new.webp'),
    ], ['Accept' => 'application/json'])
        ->assertOk();

    $secondPath = Product::findOrFail($id)->image_path;
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    $this->deleteJson("/api/v1/product/{$id}/image")
        ->assertOk()
        ->assertJsonPath('data.image_url', null);

    expect(Product::findOrFail($id)->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

it('protects product image changes with products.update', function () {
    Storage::fake('public');
    $product = Product::factory()->create();

    actingAsUserWith(['products.view']);

    $this->post("/api/v1/product/{$product->id}/image", [
        'image' => UploadedFile::fake()->image('coffee.png'),
    ], ['Accept' => 'application/json'])->assertForbidden();

    $this->deleteJson("/api/v1/product/{$product->id}/image")->assertForbidden();
});

it('converts a quantity in a selling unit to base units', function () {
    $this->postJson('/api/v1/product', coffeePayload())->assertCreated();

    $strip = ProductUnit::where('conversion_factor', 10)->first();
    $box = ProductUnit::where('conversion_factor', 100)->first();
    $sachet = ProductUnit::where('is_base', true)->first();

    expect($strip->toBaseQuantity(3))->toEqual('30.000')
        ->and($box->toBaseQuantity(1))->toEqual('100.000')
        ->and($sachet->toBaseQuantity(7))->toEqual('7.000')
        ->and($strip->fromBaseQuantity(30))->toEqual('3.000');
});

/**
 * Fractional factors are the sako→kilo case. Float arithmetic drifts here;
 * the ledger stores this number, so it has to be exact.
 */
it('converts fractional factors without float drift', function () {
    $kilo = Unit::firstOrCreate(['name' => 'kilo'], ['abbreviation' => 'kg']);
    $sako = Unit::firstOrCreate(['name' => 'sako'], ['abbreviation' => 'sk']);

    $this->postJson('/api/v1/product', [
        'store_id' => testStore()->id,
        'name' => 'Bigas Sinandomeng',
        'base_unit_id' => $kilo->id,
        'units' => [
            ['unit_id' => $kilo->id, 'conversion_factor' => 1, 'selling_price' => 55, 'is_base' => true, 'is_default_sale_unit' => true],
            ['unit_id' => $sako->id, 'conversion_factor' => 25.5, 'selling_price' => 1350, 'is_base' => false, 'is_default_sale_unit' => false],
        ],
    ])->assertCreated();

    $sakoUnit = ProductUnit::where('conversion_factor', 25.5)->first();

    expect($sakoUnit->toBaseQuantity(3))->toEqual('76.500')
        ->and($sakoUnit->toBaseQuantity('0.1'))->toEqual('2.550');
});

it('rejects a product with no base unit', function () {
    $payload = coffeePayload();
    $payload['units'][0]['is_base'] = false;

    $this->postJson('/api/v1/product', $payload)->assertStatus(422);
});

it('rejects a product with two base units', function () {
    $payload = coffeePayload();
    $payload['units'][1]['is_base'] = true;

    $this->postJson('/api/v1/product', $payload)->assertStatus(422);
});

it('rejects a base unit whose conversion factor is not 1', function () {
    $payload = coffeePayload();
    $payload['units'][0]['conversion_factor'] = 5;

    $this->postJson('/api/v1/product', $payload)->assertStatus(422);
});

it('rejects a product with no default sale unit', function () {
    $payload = coffeePayload();
    $payload['units'][0]['is_default_sale_unit'] = false;

    $this->postJson('/api/v1/product', $payload)->assertStatus(422);
});

it('rejects a zero or negative conversion factor', function () {
    $payload = coffeePayload();
    $payload['units'][1]['conversion_factor'] = 0;

    $this->postJson('/api/v1/product', $payload)->assertStatus(422);
});

it('records price history when a selling price changes', function () {
    $this->postJson('/api/v1/product', coffeePayload())->assertCreated();

    $product = Product::first();
    $payload = coffeePayload();
    $payload['units'][0]['selling_price'] = 9.00;

    $this->patchJson("/api/v1/product/{$product->id}", ['units' => $payload['units']])->assertOk();

    $base = ProductUnit::where('is_base', true)->first();
    $history = ProductPriceHistory::where('product_unit_id', $base->id)->orderBy('id')->get();

    // One row for the initial price, one for the change.
    expect($history)->toHaveCount(2)
        ->and($history->last()->old_price)->toEqual('8.00')
        ->and($history->last()->new_price)->toEqual('9.00');
});

it('does not record history when the price is unchanged', function () {
    $this->postJson('/api/v1/product', coffeePayload())->assertCreated();

    $product = Product::first();
    $this->patchJson("/api/v1/product/{$product->id}", coffeePayload())->assertOk();

    expect(ProductPriceHistory::count())->toBe(3);
});

/**
 * The defect this prevents: a box barcode resolving to the default sale unit
 * sells one sachet at the sachet price, deducts one sachet, and errors
 * nowhere. Assert on the resolved unit id, not merely that a product came
 * back — that assertion passes with the bug present.
 */
it('resolves a barcode to the selling unit it is printed on', function () {
    $payload = coffeePayload();
    $payload['units'][0]['barcode'] = 'SACHET-0001';
    $payload['units'][2]['barcode'] = 'BOX-0001';

    $this->postJson('/api/v1/product', $payload)->assertCreated();

    $product = Product::with('units')->first();
    $box = $product->units->firstWhere('conversion_factor', '100.0000');
    $sachet = $product->units->firstWhere('is_base', true);

    $this->getJson('/api/v1/products/barcode/BOX-0001')
        ->assertOk()
        ->assertJsonPath('data.product.name', 'Kopiko Blanca 3-in-1')
        ->assertJsonPath('data.product_unit_id', $box->id);

    $this->getJson('/api/v1/products/barcode/SACHET-0001')
        ->assertOk()
        ->assertJsonPath('data.product_unit_id', $sachet->id);
});

/**
 * Replaces the old "falls back to the default sale unit" case.
 *
 * There is no fallback any more: a barcode lives on a unit, so a unit without
 * one is simply not findable by scan. Silently resolving to *some* unit is
 * exactly the defect the previous model allowed.
 */
it('does not find a selling unit that has no barcode', function () {
    $payload = coffeePayload();
    $payload['units'][2]['barcode'] = 'BOX-0002';

    $this->postJson('/api/v1/product', $payload)->assertCreated();

    $this->getJson('/api/v1/products/barcode/SACHET-NOPE')->assertNotFound();
});

it('carries the barcode back on the selling unit', function () {
    $payload = coffeePayload();
    $payload['units'][2]['barcode'] = 'BOX-0003';

    $id = $this->postJson('/api/v1/product', $payload)->assertCreated()->json('data.id');

    $units = collect($this->getJson("/api/v1/product/{$id}")->assertOk()->json('data.units'));

    expect($units->firstWhere('barcode', 'BOX-0003'))->not->toBeNull()
        ->and($units->where('barcode', null))->toHaveCount(2);
});

it('edits a units barcode without tripping its own uniqueness', function () {
    $payload = coffeePayload();
    $payload['units'][2]['barcode'] = 'BOX-0004';

    $id = $this->postJson('/api/v1/product', $payload)->assertCreated()->json('data.id');

    // Resubmitting the same code for the same unit is what every save does.
    $this->patchJson("/api/v1/product/{$id}", $payload)->assertOk();

    $payload['units'][2]['barcode'] = 'BOX-0004-NEW';
    $this->patchJson("/api/v1/product/{$id}", $payload)->assertOk();

    expect(ProductUnit::where('barcode', 'BOX-0004-NEW')->exists())->toBeTrue()
        ->and(ProductUnit::where('barcode', 'BOX-0004')->exists())->toBeFalse();
});

it('refuses a barcode already used by another product', function () {
    $first = coffeePayload();
    $first['units'][2]['barcode'] = 'SHARED-0001';
    $this->postJson('/api/v1/product', $first)->assertCreated();

    $second = coffeePayload(['name' => 'Great Taste White']);
    $second['units'][2]['barcode'] = 'SHARED-0001';

    $this->postJson('/api/v1/product', $second)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('finds a product by part of a barcode', function () {
    $payload = coffeePayload();
    $payload['units'][2]['barcode'] = '4800016641107';

    $this->postJson('/api/v1/product', $payload)->assertCreated();

    $rows = $this->getJson('/api/v1/products?filter[q]=48000166')->assertOk()->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Kopiko Blanca 3-in-1');
});

/*
|--------------------------------------------------------------------------
| Fields that no longer exist
|--------------------------------------------------------------------------
*/

it('generates a sku on create and leaves it alone on update', function () {
    $created = $this->postJson('/api/v1/product', coffeePayload())->assertCreated()->json('data');

    expect($created['sku'])->toStartWith('SKU-');

    $this->patchJson("/api/v1/product/{$created['id']}", ['name' => 'Renamed'])->assertOk();

    // A regenerated SKU would break every label and report already quoting it.
    expect(Product::find($created['id'])->sku)->toBe($created['sku']);
});

it('no longer exposes brand, plu code, keywords, or product-level barcodes', function () {
    $id = $this->postJson('/api/v1/product', coffeePayload())->assertCreated()->json('data.id');

    // Absent, not merely empty — a key that lingers is a key something still
    // reads.
    $this->getJson("/api/v1/product/{$id}")
        ->assertOk()
        ->assertJsonMissingPath('data.brand')
        ->assertJsonMissingPath('data.plu_code')
        ->assertJsonMissingPath('data.keywords')
        ->assertJsonMissingPath('data.barcodes');
});

it('returns a 404 envelope for an unknown barcode', function () {
    $this->getJson('/api/v1/products/barcode/0000000000000')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('searches by name and barcode', function () {
    $payload = coffeePayload();
    $payload['units'][2]['barcode'] = '4800016641107';
    $this->postJson('/api/v1/product', $payload)->assertCreated();

    $other = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    $this->postJson('/api/v1/product', [
        'store_id' => testStore()->id,
        'name' => 'Itlog',
        'base_unit_id' => $other->id,
        'units' => [['unit_id' => $other->id, 'conversion_factor' => 1, 'selling_price' => 9, 'is_base' => true, 'is_default_sale_unit' => true]],
    ])->assertCreated();

    expect($this->getJson('/api/v1/products?filter[q]=Kopiko')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/products?filter[q]=4800016641107')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/products?filter[q]=Itlog')->json('data'))->toHaveCount(1);

    // brand, keywords, and plu_code are gone; a term that only ever matched
    // them must now find nothing rather than quietly still working.
    expect($this->getJson('/api/v1/products?filter[q]=kape')->json('data'))->toHaveCount(0);
});

it('returns the favorites grid in sort order', function () {
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);

    Product::factory()->favorite(2)->create(['name' => 'Yelo', 'base_unit_id' => $unit->id]);
    Product::factory()->favorite(1)->create(['name' => 'Itlog', 'base_unit_id' => $unit->id]);
    Product::factory()->create(['name' => 'Not Favorite', 'base_unit_id' => $unit->id]);

    $names = collect($this->getJson('/api/v1/products/favorites')->assertOk()->json('data'))->pluck('name');

    expect($names->all())->toBe(['Itlog', 'Yelo']);
});

it('loads units on the index without an N+1', function () {
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);

    Product::factory()->count(5)->create(['base_unit_id' => $unit->id])->each(function (Product $product) use ($unit) {
        ProductUnit::factory()->base()->create(['product_id' => $product->id, 'unit_id' => $unit->id]);
    });

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/products');
    $queryCount = count(DB::getQueryLog());

    $response->assertOk()->assertJsonCount(5, 'data');

    expect($queryCount)->toBeLessThan(12);
    expect($response->json('data.0.units'))->not->toBeEmpty();
});

it('keeps the category key present on a product with no category', function () {
    $unit = Unit::firstOrCreate(['name' => 'piraso'], ['abbreviation' => 'pc']);
    Product::factory()->create(['base_unit_id' => $unit->id, 'category_id' => null]);

    $row = $this->getJson('/api/v1/products')->assertOk()->json('data.0');

    expect(array_key_exists('category', $row))->toBeTrue()
        ->and($row['category'])->toBeNull();
});

it('assigns a category', function () {
    $category = Category::factory()->create();
    $payload = coffeePayload(['category_id' => $category->id]);

    $this->postJson('/api/v1/product', $payload)
        ->assertCreated()
        ->assertJsonPath('data.category.id', $category->id);
});

/**
 * Per-product discount eligibility was dropped on 2026-08-06 — a statutory
 * discount now covers the whole cart. The field must be gone from the payload
 * shape, not merely ignored, or a client would keep sending a flag that
 * silently does nothing.
 */
it('no longer exposes discount eligibility on a product', function () {
    $this->postJson('/api/v1/product', coffeePayload())
        ->assertCreated()
        ->assertJsonMissingPath('data.discount_eligibility');
});

it('requires the products permission', function () {
    actingAsUserWith(['sales.create']);

    $this->getJson('/api/v1/products')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('lets a tindera read products but not write them', function () {
    actingAsUserWith(['products.view']);

    $this->getJson('/api/v1/products')->assertOk();
    $this->postJson('/api/v1/product', coffeePayload())
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| Units are set up separately from the product
|--------------------------------------------------------------------------
*/

/**
 * Creating a product no longer asks for selling units — they are set up in
 * their own screen afterwards. A product with none is inert rather than
 * broken: the POS refuses it, which is the correct half-configured state.
 */
it('creates a product with no selling units', function () {
    $payload = coffeePayload();
    unset($payload['units']);

    $id = $this->postJson('/api/v1/product', $payload)
        ->assertCreated()
        ->assertJsonPath('data.units', [])
        ->json('data.id');

    expect(ProductUnit::where('product_id', $id)->count())->toBe(0);
});

it('adds selling units to an existing product', function () {
    $payload = coffeePayload();
    unset($payload['units']);

    $id = $this->postJson('/api/v1/product', $payload)->assertCreated()->json('data.id');

    $sachet = Unit::firstOrCreate(['name' => 'sachet'], ['abbreviation' => 'sct']);
    $box = Unit::firstOrCreate(['name' => 'box'], ['abbreviation' => 'box']);

    $this->patchJson("/api/v1/product/{$id}", [
        'units' => [
            ['unit_id' => $sachet->id, 'conversion_factor' => 1, 'selling_price' => 8, 'is_base' => true, 'is_default_sale_unit' => true, 'sort_order' => 0, 'barcode' => 'SCT-1'],
            ['unit_id' => $box->id, 'conversion_factor' => 100, 'selling_price' => 700, 'is_base' => false, 'is_default_sale_unit' => false, 'sort_order' => 1, 'barcode' => 'BOX-1'],
        ],
    ])->assertOk();

    expect(ProductUnit::where('product_id', $id)->count())->toBe(2);

    // And the box barcode now sells a box.
    $this->getJson('/api/v1/products/barcode/BOX-1')
        ->assertOk()
        ->assertJsonPath('data.product_unit_id', ProductUnit::where('barcode', 'BOX-1')->first()->id);
});

/**
 * The invariants still bite once units exist — an empty list is the only
 * thing the guard now lets through.
 */
it('still refuses a unit set with two base units', function () {
    $payload = coffeePayload();
    $payload['units'][1]['is_base'] = true;

    $this->postJson('/api/v1/product', $payload)->assertStatus(422);
});
