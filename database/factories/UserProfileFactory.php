<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'middlename' => fake()->lastName(),
            'suffix' => null,
            'salutation' => fake()->randomElement(['Mr.', 'Ms.', 'Mrs.', 'Dr.']),
            'gender' => fake()->randomElement(['male', 'female']),
            'birthdate' => fake()->date(),
        ];
    }
}
