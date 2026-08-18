<?php

use App\Models\Region;

it('lists regions when authenticated', function () {
    actingAsOwner();
    Region::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/regions');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('shows a single region when authenticated', function () {
    actingAsOwner();
    $region = Region::factory()->create();

    $response = $this->getJson("/api/v1/regions/{$region->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $region->id)
        ->assertJsonPath('data.name', $region->name);
});
