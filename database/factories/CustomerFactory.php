<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->name(),
            'nickname' => fake()->firstName(),
            'phone' => fake()->numerify('09#########'),
            'branch_id' => null,
            'current_balance' => 0,
            'is_blocked' => false,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['is_blocked' => true]);
    }
}
