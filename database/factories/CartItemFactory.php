<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    public function definition(): array
    {
        $menuItem = MenuItem::factory()->create();

        return [
            'cart_id' => Cart::factory(),
            'menu_item_id' => $menuItem->id,
            'quantity' => fake()->numberBetween(1, 5),
            'price_snapshot' => $menuItem->price,
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }
}
