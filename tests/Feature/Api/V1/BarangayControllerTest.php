<?php

use App\Models\Barangay;

it('lists barangays when authenticated', function () {
    actingAsOwner();
    Barangay::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/barangays');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('shows a single barangay when authenticated', function () {
    actingAsOwner();
    $barangay = Barangay::factory()->create();

    $response = $this->getJson("/api/v1/barangays/{$barangay->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $barangay->id)
        ->assertJsonPath('data.name', $barangay->name)
        ->assertJsonPath('data.city_id', $barangay->city_id);
});
