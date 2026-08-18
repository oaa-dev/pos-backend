<?php

namespace Database\Factories;

use App\Enums\CreditTransactionTypeEnum;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => CreditTransactionTypeEnum::CHARGE->value,
            'amount' => 100,
            'balance_after' => 100,
            'outstanding' => 100,
            'occurred_at' => now(),
        ];
    }

    public function payment(): static
    {
        return $this->state(fn () => [
            'type' => CreditTransactionTypeEnum::PAYMENT->value,
            'outstanding' => 0,
        ]);
    }
}
