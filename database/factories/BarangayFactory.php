<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class BarangayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'psgc_code' => fake()->unique()->numerify('#########'),
            'name' => 'Barangay '.fake()->unique()->word(),
        ];
    }
}
