<?php

namespace Database\Factories;

use App\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'permission_group_id' => PermissionGroup::factory(),
            'name' => fake()->unique()->slug(3),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
