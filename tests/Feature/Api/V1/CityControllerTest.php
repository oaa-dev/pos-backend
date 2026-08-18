<?php

use App\Models\City;

it('lists cities when authenticated', function () {
    actingAsOwner();
    City::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/cities');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('shows a single city when authenticated', function () {
    actingAsOwner();
    $city = City::factory()->create();

    $response = $this->getJson("/api/v1/cities/{$city->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $city->id)
        ->assertJsonPath('data.name', $city->name)
        ->assertJsonPath('data.province_id', $city->province_id);
});

it('shows a city without a province', function () {
    actingAsOwner();
    $city = City::factory()->withoutProvince()->create();

    $response = $this->getJson("/api/v1/cities/{$city->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $city->id)
        ->assertJsonPath('data.province_id', null);
});
