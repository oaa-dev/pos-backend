<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'psgc_code' => fake()->unique()->numerify('#########'),
            'name' => 'Region '.fake()->unique()->randomNumber(3),
        ];
    }
}
