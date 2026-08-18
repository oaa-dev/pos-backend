<?php

namespace Database\Factories;

use App\Enums\StatusEnum;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('09#########'),
            'status' => StatusEnum::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => StatusEnum::INACTIVE]);
    }
}
