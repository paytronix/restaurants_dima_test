<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $menuItem = MenuItem::factory()->create();

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => $menuItem->id,
            'quantity' => fake()->numberBetween(1, 5),
            'price_snapshot' => $menuItem->price,
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }
}
