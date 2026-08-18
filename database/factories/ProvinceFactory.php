<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProvinceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'psgc_code' => fake()->unique()->numerify('#########'),
            'name' => fake()->unique()->city().' Province',
        ];
    }
}
