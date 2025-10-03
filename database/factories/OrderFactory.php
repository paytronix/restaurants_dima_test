<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 100);
        $tax = $subtotal * 0.08;
        $total = $subtotal + $tax;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'status' => OrderStatus::PENDING,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'pickup_time' => fake()->optional()->dateTimeBetween('now', '+2 hours'),
            'delivery_address' => fake()->optional()->address(),
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PENDING,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PAID,
        ]);
    }

    public function inPrep(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::IN_PREP,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::READY,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::CANCELLED,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::FAILED,
        ]);
    }
}
