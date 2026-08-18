<?php

use App\Data\UnitData;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\CategoryService;
use App\Services\UnitService;
use Database\Seeders\UnitSeeder;

beforeEach(function () {
    actingAsOwner();
});

it('lists the seeded units', function () {
    $this->seed(UnitSeeder::class);

    $response = $this->getJson('/api/v1/units')->assertOk();

    expect($response->json('data'))->toHaveCount(10)
        ->and(collect($response->json('data'))->pluck('name'))->toContain('piraso', 'kilo', 'sako');
});

it('seeds units idempotently', function () {
    $this->seed(UnitSeeder::class);
    $this->seed(UnitSeeder::class);

    expect(Unit::count())->toBe(10);
});

/**
 * Units and categories used to be readable by anyone with a token — the four
 * catalogue reads carried no `can:` at all. Permission checking lives only on
 * the route now, so an unguarded route is an open one.
 */
it('requires the catalogue-support permissions to read them', function () {
    actingAsUserWith(['sales.create']);

    foreach (['/api/v1/units', '/api/v1/categories', '/api/v1/discount-types'] as $route) {
        $this->getJson($route)
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
});

/**
 * The assertion that proves the guard **moved** rather than merely widening.
 *
 * These three screens used to ride on `products.view`, which a tindera must
 * hold to work the till — so there was no permission an owner could untick to
 * keep her out of them. Holding the new permission and *not* `products.view`
 * has to be enough, or the move was cosmetic.
 */
it('reads each catalogue-support screen on its own permission, without products.view', function () {
    foreach ([
        'units.view' => '/api/v1/units',
        'categories.view' => '/api/v1/categories',
        'discount-types.view' => '/api/v1/discount-types',
    ] as $permission => $route) {
        actingAsUserWith([$permission]);

        $this->getJson($route)->assertOk();
    }
});

/**
 * The reported bug, from the other end: `products.view` alone no longer opens
 * these screens. If this passes with `assertOk()` the tindera is still seeing
 * Units and Discounts.
 */
it('no longer lets products.view reach the catalogue-support screens', function () {
    actingAsUserWith(['products.view']);

    foreach (['/api/v1/units', '/api/v1/categories', '/api/v1/discount-types'] as $route) {
        $this->getJson($route)->assertForbidden();
    }
});

/**
 * The dropdown is the one unit read that carries no permission, and it exists
 * because `owner` no longer holds `units.view`: the product form fills "Base
 * unit" from it and the selling-units dialog fills every row from it, so
 * gating it would mean a role that may price a product cannot name a unit.
 *
 * Both halves are asserted. If only the first were, moving the guard back onto
 * the dropdown would still pass for a holder of `units.view`.
 */
it('serves the unit dropdown without any units permission', function () {
    $this->seed(UnitSeeder::class);

    actingAsUserWith(['products.view']);

    $response = $this->getJson('/api/v1/units/dropdown')->assertOk();

    expect($response->json('data'))->toHaveCount(10)
        ->and($response->json('data.0'))->toHaveKeys(['id', 'name', 'abbreviation', 'allows_fraction']);
});

it('keeps every other unit route gated for a holder of the dropdown', function () {
    $unit = Unit::factory()->create();

    actingAsUserWith(['products.view']);

    $this->getJson('/api/v1/units')->assertForbidden();
    $this->getJson("/api/v1/unit/{$unit->id}")->assertForbidden();
    $this->postJson('/api/v1/unit', ['name' => 'kaha'])->assertForbidden();
    $this->patchJson("/api/v1/unit/{$unit->id}", ['name' => 'kaha'])->assertForbidden();
    $this->deleteJson("/api/v1/unit/{$unit->id}")->assertForbidden();
});

/**
 * A retired unit is gone from the picker but not from the product that was
 * sold in it — the dropdown is where "gone" has to be true.
 */
it('leaves a retired unit out of the dropdown', function () {
    $unit = Unit::factory()->create(['name' => 'sako-retired']);
    $unit->delete();

    actingAsUserWith(['products.view']);

    $names = collect($this->getJson('/api/v1/units/dropdown')->assertOk()->json('data'))
        ->pluck('name');

    expect($names)->not->toContain('sako-retired');
});

/**
 * The till reads no discount types — `pos/discount-dialog.tsx` carries its own
 * fixed senior/PWD list — so moving that read off `sales.view` costs a cashier
 * nothing she uses.
 */
it('refuses the catalogue-support screens to a sales-only actor', function () {
    actingAsUserWith(['sales.create', 'sales.view', 'sales.discount']);

    $this->getJson('/api/v1/discount-types')->assertForbidden();
});

it('separates creating a category from updating one', function () {
    actingAsUserWith(['categories.update']);

    // Was `products.update`, which let update-but-not-create create.
    $this->postJson('/api/v1/category', ['store_id' => testStore()->id, 'name' => 'Yelo'])
        ->assertForbidden();

    actingAsUserWith(['categories.create']);

    $this->postJson('/api/v1/category', ['store_id' => testStore()->id, 'name' => 'Yelo'])
        ->assertCreated();
});

it('creates a category and derives the slug', function () {
    $this->postJson('/api/v1/category', ['store_id' => testStore()->id, 'name' => 'Inuming Malamig'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Inuming Malamig')
        ->assertJsonPath('data.slug', 'inuming-malamig');
});

it('nests a category under a parent', function () {
    $parent = Category::factory()->create();

    $response = $this->postJson('/api/v1/category', [
        'store_id' => testStore()->id,
        'name' => 'Softdrinks',
        'parent_id' => $parent->id,
    ])->assertCreated();

    expect($response->json('data.parent.id'))->toBe($parent->id);
});

/**
 * A top-level category is the loaded-but-null case — the key must still be
 * present, or clients see a field that comes and goes per row.
 */
it('keeps the parent key present on a top-level category', function () {
    Category::factory()->create();

    $row = $this->getJson('/api/v1/categories')->assertOk()->json('data.0');

    expect(array_key_exists('parent', $row))->toBeTrue()
        ->and($row['parent'])->toBeNull();
});

it('promotes a child to top level by nulling the parent', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $this->patchJson("/api/v1/category/{$child->id}", ['parent_id' => null])->assertOk();

    expect($child->fresh()->parent_id)->toBeNull();
});

it('refuses to make a category its own parent', function () {
    $category = Category::factory()->create();

    $this->patchJson("/api/v1/category/{$category->id}", ['parent_id' => $category->id])
        ->assertStatus(422);
});

it('requires categories.create to write a category', function () {
    actingAsUserWith(['sales.create']);

    $this->postJson('/api/v1/category', ['store_id' => testStore()->id, 'name' => 'Sneaky'])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

/**
 * Retiring a unit must not rewrite what a product is sold in.
 *
 * The defect this prevents is silent: units are soft-deleted, so without
 * withTrashed() on ProductUnit::unit() the relation resolves to null. The
 * resource emits it through the callback form of whenLoaded, which turns null
 * into an explicit null rather than an error — so the POS would render a
 * sellable line with no unit name and nothing anywhere would complain.
 *
 * Assert the unit NAME, not that the request returned 200. A test that only
 * checks the status passes with the bug present.
 */
it('still renders the unit name of a product whose unit was retired', function () {
    $sachet = Unit::create(['name' => 'sachet-retired', 'abbreviation' => 'sct']);

    $product = Product::factory()->create(['base_unit_id' => $sachet->id]);
    ProductUnit::factory()->base()->create([
        'product_id' => $product->id,
        'unit_id' => $sachet->id,
    ]);

    $sachet->delete();

    $response = $this->getJson("/api/v1/product/{$product->id}")->assertOk();

    expect($response->json('data.base_unit.name'))->toBe('sachet-retired')
        ->and($response->json('data.units.0.unit.name'))->toBe('sachet-retired');
});

it('hides a retired unit from the picker', function () {
    $unit = Unit::create(['name' => 'obsolete', 'abbreviation' => 'obs']);
    $unit->delete();

    $names = collect($this->getJson('/api/v1/units')->assertOk()->json('data'))->pluck('name');

    expect($names)->not->toContain('obsolete');
});

it('keeps a products category when the category is retired', function () {
    $category = Category::create(['store_id' => testStore()->id, 'name' => 'Retired Aisle', 'slug' => 'retired-aisle']);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $category->delete();

    // nullOnDelete would have blanked this on a hard delete.
    expect($product->fresh()->category_id)->toBe($category->id)
        ->and($this->getJson("/api/v1/product/{$product->id}")->json('data.category.name'))
        ->toBe('Retired Aisle');
});

/*
|--------------------------------------------------------------------------
| Unit & Category write path
|--------------------------------------------------------------------------
| Exercised at the service layer: the HTTP routes are registered in a later
| commit, because routes/api.php is being edited concurrently elsewhere.
*/

it('creates a unit and derives a unique abbreviation', function () {
    $service = app(UnitService::class);

    $first = $service->store(UnitData::from(['name' => 'sakong bigas', 'abbreviation' => null]));
    $second = $service->store(UnitData::from(['name' => 'sakong bigas maliit', 'abbreviation' => null]));

    expect($first->abbreviation)->toBe('sako')
        // Derived, then checked — two names can shorten to the same stem.
        ->and($second->abbreviation)->not->toBe($first->abbreviation);
});

it('keeps an explicit abbreviation when one is given', function () {
    $unit = app(UnitService::class)->store(UnitData::from(['name' => 'garapon', 'abbreviation' => 'jar']));

    expect($unit->abbreviation)->toBe('jar');
});

it('renames a unit without touching its abbreviation', function () {
    $unit = Unit::create(['name' => 'lata', 'abbreviation' => 'lta']);

    $updated = app(UnitService::class)->updateUnit($unit, UnitData::from(['name' => 'de lata']));

    expect($updated->name)->toBe('de lata')
        ->and($updated->abbreviation)->toBe('lta');
});

it('retires a unit without erasing it', function () {
    $unit = Unit::create(['name' => 'temporary', 'abbreviation' => 'tmp']);

    app(UnitService::class)->retire($unit);

    expect(Unit::find($unit->id))->toBeNull()
        ->and(Unit::withTrashed()->find($unit->id))->not->toBeNull();
});

it('refuses to retire a category that still has children', function () {
    $parent = Category::create(['store_id' => testStore()->id, 'name' => 'Beverages', 'slug' => 'beverages']);
    Category::create(['store_id' => testStore()->id, 'name' => 'Softdrinks', 'slug' => 'softdrinks', 'parent_id' => $parent->id]);

    app(CategoryService::class)->retire($parent);
})->throws(InvalidArgumentException::class, 'Move or retire the 1 sub-categories first.');

it('retires a childless category', function () {
    $category = Category::create(['store_id' => testStore()->id, 'name' => 'Seasonal', 'slug' => 'seasonal']);

    app(CategoryService::class)->retire($category);

    expect(Category::find($category->id))->toBeNull()
        ->and(Category::withTrashed()->find($category->id))->not->toBeNull();
});

it('creates and retires a unit over HTTP', function () {
    $id = $this->postJson('/api/v1/unit', ['name' => 'balde', 'abbreviation' => 'bld'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'balde')
        ->json('data.id');

    $this->deleteJson("/api/v1/unit/{$id}")->assertOk();

    expect(Unit::find($id))->toBeNull()
        ->and(Unit::withTrashed()->find($id))->not->toBeNull();
});

it('refuses over HTTP to retire a category that still has children', function () {
    $parent = Category::create(['store_id' => testStore()->id, 'name' => 'Snacks', 'slug' => 'snacks']);
    Category::create(['store_id' => testStore()->id, 'name' => 'Chips', 'slug' => 'chips', 'parent_id' => $parent->id]);

    $this->deleteJson("/api/v1/category/{$parent->id}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Category::find($parent->id))->not->toBeNull();
});

it('requires units.delete to retire a unit', function () {
    $unit = Unit::create(['name' => 'kahon', 'abbreviation' => 'khn']);

    // Holds every other unit permission, so the refusal can only be the
    // missing delete — not an actor who was never allowed in at all.
    actingAsUserWith(['units.view', 'units.update']);

    $this->deleteJson("/api/v1/unit/{$unit->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
