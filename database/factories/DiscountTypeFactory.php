<?php

namespace Database\Factories;

use App\Enums\StatusEnum;
use App\Models\DiscountType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DiscountType>
 */
class DiscountTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'percentage' => null,
            'requires_identification' => false,
            'is_system' => false,
            'status' => StatusEnum::ACTIVE,
        ];
    }

    /** A statutory row — protected from deletion and re-rating. */
    public function system(): static
    {
        return $this->state(fn () => [
            'percentage' => '20.00',
            'requires_identification' => true,
            'is_system' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => StatusEnum::INACTIVE]);
    }
}
