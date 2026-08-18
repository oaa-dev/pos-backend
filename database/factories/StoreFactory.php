<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->lastName().' Sari-Sari Store';

        return [
            'name' => $name,
            // Random suffix, not just the slugged name: tests create several
            // stores per run and `stores.slug` is globally unique.
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'owner_name' => fake()->name(),
            'phone' => fake()->numerify('09#########'),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inactive']);
    }
}
