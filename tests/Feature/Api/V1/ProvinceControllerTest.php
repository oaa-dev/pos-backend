<?php

use App\Models\Province;

it('lists provinces when authenticated', function () {
    actingAsOwner();
    Province::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/provinces');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('shows a single province when authenticated', function () {
    actingAsOwner();
    $province = Province::factory()->create();

    $response = $this->getJson("/api/v1/provinces/{$province->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $province->id)
        ->assertJsonPath('data.name', $province->name)
        ->assertJsonPath('data.region_id', $province->region_id);
});
