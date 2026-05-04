<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Grains',      'description' => 'Rice, pasta, bread, cereals, and flour products.'],
            ['name' => 'Canned Goods','description' => 'Canned vegetables, fruits, beans, soups, and meats.'],
            ['name' => 'Beverages',   'description' => 'Water, juice, powdered drinks, and other beverages.'],
            ['name' => 'Produce',     'description' => 'Fresh fruits and vegetables.'],
            ['name' => 'Hygiene',     'description' => 'Soap, shampoo, toothpaste, diapers, and personal care items.'],
        ];

        foreach ($categories as $category) {
            InventoryCategory::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
