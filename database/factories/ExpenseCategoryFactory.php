<?php

namespace Database\Factories;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'status' => StatusEnum::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => StatusEnum::INACTIVE]);
    }
}
