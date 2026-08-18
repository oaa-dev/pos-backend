<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashDrawerSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'opened_at' => now(),
            'opening_float' => 1000,
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'closed_at' => now(),
            'closing_counted' => 1000,
            'expected_cash' => 1000,
            'variance' => 0,
        ]);
    }
}
