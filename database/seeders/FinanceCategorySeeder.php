<?php

namespace Database\Seeders;

use App\Models\FinanceCategory;
use Illuminate\Database\Seeder;

class FinanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Phase 7.4.a — function_classification per row. Income rows technically
        // don't need it (Statement of Functional Expenses is expense-only), but
        // the column is NOT NULL with default 'program' so they get the default.
        $categories = [
            // ── Income (function defaults to 'program' but unused for income) ──
            ['name' => 'Government Grant',       'type' => 'income',  'description' => 'Federal, state, or local government funding'],
            ['name' => 'Corporate Donation',     'type' => 'income',  'description' => 'Donations from businesses and corporations'],
            ['name' => 'Individual Donation',    'type' => 'income',  'description' => 'Donations from individual supporters'],
            ['name' => 'Fundraising Event',      'type' => 'income',  'description' => 'Revenue raised through fundraising activities'],
            ['name' => 'Foundation Grant',       'type' => 'income',  'description' => 'Grants from private or community foundations'],
            ['name' => 'In-Kind Donation',       'type' => 'income',  'description' => 'Non-cash contributions (food, goods, services)'],
            ['name' => 'Membership Fee',         'type' => 'income',  'description' => 'Annual or periodic membership dues'],
            ['name' => 'Other Income',           'type' => 'income',  'description' => 'Miscellaneous income not in other categories'],

            // ── Expense — explicit functional classification per IRS-990 norms ──
            ['name' => 'Food & Supplies',        'type' => 'expense', 'function_classification' => 'program',            'description' => 'Food items, bags, and packing materials'],
            ['name' => 'Venue & Facilities',     'type' => 'expense', 'function_classification' => 'program',            'description' => 'Rental, utilities, and facility costs'],
            ['name' => 'Transportation',         'type' => 'expense', 'function_classification' => 'program',            'description' => 'Fuel, vehicle hire, and delivery costs'],
            ['name' => 'Staff & Volunteer',      'type' => 'expense', 'function_classification' => 'program',            'description' => 'Stipends, training, and staff-related costs'],
            ['name' => 'Marketing & Outreach',   'type' => 'expense', 'function_classification' => 'fundraising',        'description' => 'Printing, social media, and outreach materials'],
            ['name' => 'Equipment & Technology', 'type' => 'expense', 'function_classification' => 'management_general', 'description' => 'Hardware, software, and equipment purchases'],
            ['name' => 'Administrative',         'type' => 'expense', 'function_classification' => 'management_general', 'description' => 'Office supplies, postage, banking fees'],
            ['name' => 'Insurance',              'type' => 'expense', 'function_classification' => 'management_general', 'description' => 'Liability and property insurance'],
            ['name' => 'Other Expense',          'type' => 'expense', 'function_classification' => 'program',            'description' => 'Miscellaneous expenses not in other categories'],
        ];

        foreach ($categories as $cat) {
            $attrs = ['description' => $cat['description'], 'is_active' => true];
            if (isset($cat['function_classification'])) {
                $attrs['function_classification'] = $cat['function_classification'];
            }
            FinanceCategory::firstOrCreate(
                ['name' => $cat['name'], 'type' => $cat['type']],
                $attrs
            );
        }
    }
}
