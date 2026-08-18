<?php

namespace Database\Factories;

use App\Enums\StatusEnum;
use App\Models\Store;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->unique()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-????####'),
            'category_id' => null,
            'base_unit_id' => Unit::factory(),
            'is_perishable' => false,
            'track_stock' => true,
            'is_favorite' => false,
            'favorite_sort' => 0,
            'status' => StatusEnum::ACTIVE,
        ];
    }

    public function perishable(): static
    {
        return $this->state(fn () => ['is_perishable' => true]);
    }

    public function favorite(int $sort = 0): static
    {
        return $this->state(fn () => ['is_favorite' => true, 'favorite_sort' => $sort]);
    }
}
