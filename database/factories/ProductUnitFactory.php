<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit_id' => Unit::factory(),
            'conversion_factor' => 1,
            'selling_price' => fake()->randomFloat(2, 1, 500),
            'is_base' => false,
            'is_default_sale_unit' => false,
            'sort_order' => 0,
        ];
    }

    public function base(): static
    {
        return $this->state(fn () => [
            'conversion_factor' => 1,
            'is_base' => true,
            'is_default_sale_unit' => true,
        ]);
    }
}
