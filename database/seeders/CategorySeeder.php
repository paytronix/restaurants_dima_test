<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pizza', 'position' => 1, 'is_active' => true],
            ['name' => 'Pasta', 'position' => 2, 'is_active' => true],
            ['name' => 'Beverages', 'position' => 3, 'is_active' => true],
            ['name' => 'Desserts', 'position' => 4, 'is_active' => true],
            ['name' => 'Salads', 'position' => 5, 'is_active' => true],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
