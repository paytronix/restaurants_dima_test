<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ItemAvailability;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $pizzaCategory = Category::where('name', 'Pizza')->first();
        $pastaCategory = Category::where('name', 'Pasta')->first();
        $beveragesCategory = Category::where('name', 'Beverages')->first();

        $items = [
            [
                'category_id' => $pizzaCategory->id,
                'name' => 'Margherita',
                'description' => 'Classic tomato sauce, mozzarella, basil',
                'price' => '24.90',
                'currency' => 'PLN',
                'is_active' => true,
            ],
            [
                'category_id' => $pizzaCategory->id,
                'name' => 'Pepperoni',
                'description' => 'Tomato sauce, mozzarella, pepperoni',
                'price' => '29.90',
                'currency' => 'PLN',
                'is_active' => true,
            ],
            [
                'category_id' => $pizzaCategory->id,
                'name' => 'Vegetariana',
                'description' => 'Tomato sauce, mozzarella, vegetables',
                'price' => '27.90',
                'currency' => 'PLN',
                'is_active' => true,
            ],
            [
                'category_id' => $pastaCategory->id,
                'name' => 'Spaghetti Carbonara',
                'description' => 'Eggs, bacon, parmesan, black pepper',
                'price' => '32.90',
                'currency' => 'PLN',
                'is_active' => true,
            ],
            [
                'category_id' => $pastaCategory->id,
                'name' => 'Penne Arrabiata',
                'description' => 'Tomato sauce, garlic, chili',
                'price' => '28.90',
                'currency' => 'PLN',
                'is_active' => true,
            ],
            [
                'category_id' => $beveragesCategory->id,
                'name' => 'Coca Cola',
                'description' => '330ml',
                'price' => '8.90',
                'currency' => 'PLN',
                'is_active' => true,
            ],
            [
                'category_id' => $beveragesCategory->id,
                'name' => 'Mineral Water',
                'description' => '500ml',
                'price' => '6.90',
                'currency' => 'PLN',
                'is_active' => true,
            ],
        ];

        foreach ($items as $itemData) {
            $item = MenuItem::create($itemData);

            for ($day = 0; $day <= 6; $day++) {
                ItemAvailability::create([
                    'menu_item_id' => $item->id,
                    'day_of_week' => $day,
                    'time_from' => '10:00',
                    'time_to' => '22:00',
                ]);
            }
        }
    }
}
