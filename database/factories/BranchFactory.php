<?php

namespace Database\Factories;

use App\Enums\StatusEnum;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Explicit, because nothing stamps it anymore. A caller that wants
            // this branch in an existing store passes `['store_id' => $store->id]`;
            // otherwise it gets its own, which is what keeps unrelated tests
            // from colliding on `(store_id, code)`.
            'store_id' => Store::factory(),
            'name' => fake()->company().' Sari-Sari',
            'code' => Str::upper(Str::random(6)),
            'phone' => fake()->numerify('09#########'),
            'status' => StatusEnum::ACTIVE,
            'opened_at' => fake()->dateTimeBetween('-3 years')->format('Y-m-d'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusEnum::INACTIVE,
        ]);
    }
}
