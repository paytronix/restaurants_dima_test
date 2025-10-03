<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 50),
            'category' => fake()->randomElement(['Burgers', 'Salads', 'Pizza', 'Sides', 'Desserts', 'Beverages']),
            'available' => fake()->boolean(90),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => true,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => false,
        ]);
    }
}
