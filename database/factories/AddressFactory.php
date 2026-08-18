<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [

            'city_id' => City::factory()->withoutProvince(),
            'region_id' => fn (array $attributes) => City::find($attributes['city_id'])->region_id,
            'province_id' => null,
            'barangay_id' => fn (array $attributes) => Barangay::factory()
                ->create(['city_id' => $attributes['city_id']])
                ->id,
            'address_line' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'type' => 'primary',
            'is_default' => true,
        ];
    }

    public function withProvince(): static
    {
        return $this->state(fn (array $attributes) => [
            'city_id' => City::factory(),
            'region_id' => fn (array $attrs) => City::find($attrs['city_id'])->region_id,
            'province_id' => fn (array $attrs) => City::find($attrs['city_id'])->province_id,
            'barangay_id' => fn (array $attrs) => Barangay::factory()
                ->create(['city_id' => $attrs['city_id']])
                ->id,
        ]);
    }
}
