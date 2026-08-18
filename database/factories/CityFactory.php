<?php

namespace Database\Factories;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class CityFactory extends Factory
{
    public function definition(): array
    {
        $province = Province::factory();

        return [
            'province_id' => $province,

            'region_id' => fn (array $attributes) => Province::find($attributes['province_id'])?->region_id
                ?? Region::factory(),
            'psgc_code' => fake()->unique()->numerify('#########'),
            'name' => fake()->unique()->city(),
            'is_city' => true,
        ];
    }

    public function municipality(): static
    {
        return $this->state(fn (array $attributes) => ['is_city' => false]);
    }

    public function withoutProvince(): static
    {
        return $this->state(fn (array $attributes) => [
            'province_id' => null,
            'region_id' => Region::factory(),
        ]);
    }
}
