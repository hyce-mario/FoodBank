<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            VolunteerSeeder::class,
            DemoSeeder::class,
            InventoryCategorySeeder::class,
            InventoryItemSeeder::class,
            FinanceCategorySeeder::class,
            FinanceTransactionSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
