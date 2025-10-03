<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'idempotency_key' => fake()->uuid(),
            'amount' => fake()->randomFloat(2, 10, 100),
            'status' => fake()->randomElement(['pending', 'success', 'failed']),
            'provider_reference' => 'stub_' . uniqid(),
            'error_message' => fake()->optional()->sentence(),
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Payment processing failed',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'error_message' => null,
        ]);
    }
}
