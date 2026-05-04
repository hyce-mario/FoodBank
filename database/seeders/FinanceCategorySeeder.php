<?php

namespace Database\Seeders;

use App\Models\FinanceCategory;
use Illuminate\Database\Seeder;

class FinanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // ── Income ───────────────────────────────────────────────────────────
            ['name' => 'Government Grant',       'type' => 'income',  'description' => 'Federal, state, or local government funding'],
            ['name' => 'Corporate Donation',     'type' => 'income',  'description' => 'Donations from businesses and corporations'],
            ['name' => 'Individual Donation',    'type' => 'income',  'description' => 'Donations from individual supporters'],
            ['name' => 'Fundraising Event',      'type' => 'income',  'description' => 'Revenue raised through fundraising activities'],
            ['name' => 'Foundation Grant',       'type' => 'income',  'description' => 'Grants from private or community foundations'],
            ['name' => 'In-Kind Donation',       'type' => 'income',  'description' => 'Non-cash contributions (food, goods, services)'],
            ['name' => 'Membership Fee',         'type' => 'income',  'description' => 'Annual or periodic membership dues'],
            ['name' => 'Other Income',           'type' => 'income',  'description' => 'Miscellaneous income not in other categories'],

            // ── Expense ──────────────────────────────────────────────────────────
            ['name' => 'Food & Supplies',        'type' => 'expense', 'description' => 'Food items, bags, and packing materials'],
            ['name' => 'Venue & Facilities',     'type' => 'expense', 'description' => 'Rental, utilities, and facility costs'],
            ['name' => 'Transportation',         'type' => 'expense', 'description' => 'Fuel, vehicle hire, and delivery costs'],
            ['name' => 'Staff & Volunteer',      'type' => 'expense', 'description' => 'Stipends, training, and staff-related costs'],
            ['name' => 'Marketing & Outreach',   'type' => 'expense', 'description' => 'Printing, social media, and outreach materials'],
            ['name' => 'Equipment & Technology', 'type' => 'expense', 'description' => 'Hardware, software, and equipment purchases'],
            ['name' => 'Administrative',         'type' => 'expense', 'description' => 'Office supplies, postage, banking fees'],
            ['name' => 'Insurance',              'type' => 'expense', 'description' => 'Liability and property insurance'],
            ['name' => 'Other Expense',          'type' => 'expense', 'description' => 'Miscellaneous expenses not in other categories'],
        ];

        foreach ($categories as $cat) {
            FinanceCategory::firstOrCreate(
                ['name' => $cat['name'], 'type' => $cat['type']],
                ['description' => $cat['description'], 'is_active' => true]
            );
        }
    }
}
