<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierOption;
use Illuminate\Database\Seeder;

class ModifierSeeder extends Seeder
{
    public function run(): void
    {
        $sizeModifier = Modifier::create([
            'name' => 'Size',
            'type' => 'single',
            'min_select' => 1,
            'max_select' => 1,
            'is_required' => true,
        ]);

        ModifierOption::create([
            'modifier_id' => $sizeModifier->id,
            'name' => 'Small',
            'price_delta' => '-5.00',
            'is_active' => true,
            'position' => 1,
        ]);

        ModifierOption::create([
            'modifier_id' => $sizeModifier->id,
            'name' => 'Medium',
            'price_delta' => '0.00',
            'is_active' => true,
            'position' => 2,
        ]);

        ModifierOption::create([
            'modifier_id' => $sizeModifier->id,
            'name' => 'Large',
            'price_delta' => '5.00',
            'is_active' => true,
            'position' => 3,
        ]);

        $toppingsModifier = Modifier::create([
            'name' => 'Extra Toppings',
            'type' => 'multiple',
            'min_select' => 0,
            'max_select' => 5,
            'is_required' => false,
        ]);

        $toppings = [
            'Olives' => '2.50',
            'Mushrooms' => '3.00',
            'Extra Cheese' => '4.00',
            'Bacon' => '5.00',
            'Ham' => '5.00',
        ];

        $position = 1;
        foreach ($toppings as $name => $price) {
            ModifierOption::create([
                'modifier_id' => $toppingsModifier->id,
                'name' => $name,
                'price_delta' => $price,
                'is_active' => true,
                'position' => $position++,
            ]);
        }

        $pizzaItems = MenuItem::whereHas('category', function ($query) {
            $query->where('name', 'Pizza');
        })->get();

        foreach ($pizzaItems as $item) {
            $item->modifiers()->attach([$sizeModifier->id, $toppingsModifier->id]);
        }
    }
}
