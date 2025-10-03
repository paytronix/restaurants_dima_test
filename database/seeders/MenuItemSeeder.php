<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $menuItems = [
            [
                'name' => 'Classic Burger',
                'description' => 'Beef patty with lettuce, tomato, and special sauce',
                'price' => 12.99,
                'category' => 'Burgers',
                'available' => true,
            ],
            [
                'name' => 'Chicken Caesar Salad',
                'description' => 'Grilled chicken breast with romaine, parmesan, and caesar dressing',
                'price' => 10.99,
                'category' => 'Salads',
                'available' => true,
            ],
            [
                'name' => 'Margherita Pizza',
                'description' => 'Fresh mozzarella, basil, and tomato sauce',
                'price' => 14.99,
                'category' => 'Pizza',
                'available' => true,
            ],
            [
                'name' => 'French Fries',
                'description' => 'Crispy golden fries with sea salt',
                'price' => 4.99,
                'category' => 'Sides',
                'available' => true,
            ],
            [
                'name' => 'Chocolate Lava Cake',
                'description' => 'Warm chocolate cake with molten center',
                'price' => 7.99,
                'category' => 'Desserts',
                'available' => true,
            ],
            [
                'name' => 'Iced Coffee',
                'description' => 'Cold brew coffee over ice',
                'price' => 3.99,
                'category' => 'Beverages',
                'available' => true,
            ],
            [
                'name' => 'Grilled Salmon',
                'description' => 'Atlantic salmon with lemon butter sauce',
                'price' => 18.99,
                'category' => 'Seafood',
                'available' => true,
            ],
            [
                'name' => 'Vegetarian Wrap',
                'description' => 'Hummus, vegetables, and feta cheese in a whole wheat wrap',
                'price' => 9.99,
                'category' => 'Wraps',
                'available' => true,
            ],
            [
                'name' => 'Seasonal Special',
                'description' => 'Chef\'s special dish of the season',
                'price' => 16.99,
                'category' => 'Specials',
                'available' => false,
            ],
        ];

        foreach ($menuItems as $item) {
            MenuItem::create($item);
        }
    }
}
