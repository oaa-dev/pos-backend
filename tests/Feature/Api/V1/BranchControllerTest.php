<?php

use App\Models\Address;
use App\Models\Barangay;
use App\Models\Branch;
use App\Models\City;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    actingAsOwner();
});

it('lists branches', function () {
    Branch::factory()->count(3)->create();

    $this->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

/**
 * The address key must be present even when the branch has no address, or a
 * typed client sees a field that sometimes exists and sometimes does not.
 * `assertJsonPath(..., null)` cannot tell absent from null — array_key_exists
 * can.
 */
it('keeps the address key present on a branch that has none', function () {
    Branch::factory()->create();

    $row = $this->getJson('/api/v1/branches')->assertOk()->json('data.0');

    expect(array_key_exists('address', $row))->toBeTrue()
        ->and($row['address'])->toBeNull();
});

it('loads the address on the index without an N+1', function () {
    $region = Region::factory()->create();
    $city = City::factory()->create(['region_id' => $region->id]);
    $barangay = Barangay::factory()->create(['city_id' => $city->id]);

    Branch::factory()->count(4)->create()->each(function (Branch $branch) use ($region, $city, $barangay) {
        Address::factory()->create([
            'addressable_type' => Branch::class,
            'addressable_id' => $branch->id,
            'region_id' => $region->id,
            'city_id' => $city->id,
            'barangay_id' => $barangay->id,
        ]);
    });

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/branches');
    $queryCount = count(DB::getQueryLog());

    $response->assertOk()->assertJsonCount(4, 'data');

    // The row query plus its eager loads — not one query per branch.
    expect($queryCount)->toBeLessThan(10);
    expect($response->json('data.0.address'))->not->toBeNull();
});

it('creates a branch and derives the code from the name', function () {
    $response = $this->postJson('/api/v1/branch', ['store_id' => testStore()->id, 'name' => 'Aling Nena Store']);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Aling Nena Store')
        // The branch form reads this back; BranchResponse types it as required.
        ->assertJsonPath('data.store_id', testStore()->id)
        ->assertJsonPath('data.status', 'active');

    expect($response->json('data.code'))->toBe('ALINGN');
});

it('does not collide when two branches derive the same code', function () {
    $this->postJson('/api/v1/branch', ['store_id' => testStore()->id, 'name' => 'Aling Nena Store'])->assertCreated();
    $second = $this->postJson('/api/v1/branch', ['store_id' => testStore()->id, 'name' => 'Aling Nena Store'])->assertCreated();

    expect($second->json('data.code'))->not->toBe('ALINGN');
    expect(Branch::count())->toBe(2);
});

it('creates a branch with a nested address', function () {
    $region = Region::factory()->create();
    $city = City::factory()->create(['region_id' => $region->id]);
    $barangay = Barangay::factory()->create(['city_id' => $city->id]);

    $response = $this->postJson('/api/v1/branch', [
        'store_id' => testStore()->id,
        'name' => 'Barangay Uno Branch',
        'address' => [
            'region_id' => $region->id,
            'city_id' => $city->id,
            'barangay_id' => $barangay->id,
            'address_line' => '12 Rizal St',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.address.address_line', '12 Rizal St');

    $this->assertDatabaseHas('addresses', [
        'addressable_type' => Branch::class,
        'address_line' => '12 Rizal St',
    ]);
});

it('rejects a duplicate branch code', function () {
    Branch::factory()->create(['store_id' => testStore()->id, 'code' => 'MAIN01']);

    $this->postJson('/api/v1/branch', ['store_id' => testStore()->id, 'name' => 'Another', 'code' => 'MAIN01'])
        ->assertStatus(422);
});

it('updates a branch and leaves omitted fields unchanged', function () {
    $branch = Branch::factory()->create(['name' => 'Old Name', 'phone' => '09171234567']);

    $this->patchJson("/api/v1/branch/{$branch->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    expect($branch->fresh()->phone)->toBe('09171234567');
});

it('lets a branch keep its own code on update', function () {
    $branch = Branch::factory()->create(['code' => 'KEEP01']);

    $this->patchJson("/api/v1/branch/{$branch->id}", ['code' => 'KEEP01'])->assertOk();
});

it('returns a 404 envelope for a branch that does not exist', function () {
    $this->getJson('/api/v1/branch/999999')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('assigns a tindera to a branch', function () {
    $branch = Branch::factory()->create(['store_id' => testStore()->id]);
    // A branch may only be staffed from its own store's accounts.
    $tindera = User::factory()->create();
    $tindera->stores()->syncWithoutDetaching([testStore()->id => ['is_owner' => false]]);

    $this->postJson("/api/v1/branch/{$branch->id}/users", [
        'user_id' => $tindera->id,
        'is_primary' => true,
    ])->assertOk();

    $this->assertDatabaseHas('branch_user', [
        'branch_id' => $branch->id,
        'user_id' => $tindera->id,
        'is_primary' => true,
    ]);
});

it('does not duplicate an assignment that already exists', function () {
    $branch = Branch::factory()->create(['store_id' => testStore()->id]);
    // A branch may only be staffed from its own store's accounts.
    $tindera = User::factory()->create();
    $tindera->stores()->syncWithoutDetaching([testStore()->id => ['is_owner' => false]]);

    $this->postJson("/api/v1/branch/{$branch->id}/users", ['user_id' => $tindera->id])->assertOk();
    $this->postJson("/api/v1/branch/{$branch->id}/users", ['user_id' => $tindera->id])->assertOk();

    expect(DB::table('branch_user')->where('branch_id', $branch->id)->count())->toBe(1);
});

it('removes a tindera from a branch', function () {
    $branch = Branch::factory()->create(['store_id' => testStore()->id]);
    // A branch may only be staffed from its own store's accounts.
    $tindera = User::factory()->create();
    $tindera->stores()->syncWithoutDetaching([testStore()->id => ['is_owner' => false]]);
    $branch->users()->attach($tindera->id);

    $this->deleteJson("/api/v1/branch/{$branch->id}/users/{$tindera->id}")->assertOk();

    $this->assertDatabaseMissing('branch_user', [
        'branch_id' => $branch->id,
        'user_id' => $tindera->id,
    ]);
});

it('rejects assigning a user that does not exist', function () {
    $branch = Branch::factory()->create();

    $this->postJson("/api/v1/branch/{$branch->id}/users", ['user_id' => 999999])
        ->assertStatus(422);
});

it('searches branches by name, code, and address line', function () {
    $region = Region::factory()->create();
    $city = City::factory()->create(['region_id' => $region->id]);
    $barangay = Barangay::factory()->create(['city_id' => $city->id]);

    $target = Branch::factory()->create(['name' => 'Poblacion Store', 'code' => 'POB001']);
    Address::factory()->create([
        'addressable_type' => Branch::class,
        'addressable_id' => $target->id,
        'region_id' => $region->id,
        'city_id' => $city->id,
        'barangay_id' => $barangay->id,
        'address_line' => 'Mabini Extension',
    ]);
    Branch::factory()->create(['name' => 'Riverside', 'code' => 'RIV001']);

    expect($this->getJson('/api/v1/branches?filter[q]=Poblacion')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/branches?filter[q]=POB001')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/branches?filter[q]=Mabini')->json('data'))->toHaveCount(1);
});

it('requires the branches permission', function () {
    actingAsUserWith(['sales.create']);

    $this->getJson('/api/v1/branches')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

/**
 * The dropdown carries no `can:`, so its whole safety story is the narrowing.
 * These four tests are that story; if any of them is deleted the endpoint
 * becomes an unguarded read of every customer's branches.
 */
it('pins the branch dropdown to the actor own store', function () {
    $mine = Branch::factory()->create(['store_id' => testStore()->id, 'name' => 'Mine']);
    Branch::factory()->create(['store_id' => anotherStore()->id, 'name' => 'Theirs']);

    actingAsOwner();

    $response = $this->getJson('/api/v1/branches/dropdown')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and(collect($response->json('data'))->pluck('name'))->not->toContain('Theirs');
});

it('refuses to let a requested store_id override the actor own store', function () {
    Branch::factory()->create(['store_id' => testStore()->id, 'name' => 'Mine']);
    Branch::factory()->create(['store_id' => anotherStore()->id, 'name' => 'Theirs']);

    actingAsOwner();

    $names = collect(
        $this->getJson('/api/v1/branches/dropdown?store_id='.anotherStore()->id)
            ->assertOk()
            ->json('data')
    )->pluck('name');

    expect($names)->toContain('Mine')->and($names)->not->toContain('Theirs');
});

it('narrows the branch dropdown to the branches a tindera is assigned to', function () {
    $assigned = Branch::factory()->create(['store_id' => testStore()->id, 'name' => 'Assigned']);
    Branch::factory()->create(['store_id' => testStore()->id, 'name' => 'Unassigned']);

    $user = actingAsUserWith(['sales.create']);
    $assigned->users()->syncWithoutDetaching([$user->id => ['is_primary' => true]]);

    $names = collect($this->getJson('/api/v1/branches/dropdown')->assertOk()->json('data'))
        ->pluck('name');

    expect($names)->toContain('Assigned')->and($names)->not->toContain('Unassigned');
});

it('serves the branch dropdown without any branches permission', function () {
    $branch = Branch::factory()->create(['store_id' => testStore()->id]);

    $user = actingAsUserWith(['sales.create']);
    $branch->users()->syncWithoutDetaching([$user->id => ['is_primary' => true]]);

    $this->getJson('/api/v1/branches/dropdown')->assertOk();
    $this->getJson('/api/v1/branches')->assertForbidden();
});
