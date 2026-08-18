<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'store_id' => Store::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'parent_id' => null,
        ];
    }
}
