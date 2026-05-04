<?php

namespace Database\Seeders;

use App\Models\AllocationRuleset;
use Illuminate\Database\Seeder;

class AllocationRulesetSeeder extends Seeder
{
    public function run(): void
    {
        $rulesets = [
            [
                'name'               => 'Standard Distribution',
                'allocation_type'    => 'household_size',
                'description'        => 'Default allocation rules for regular food bank distribution events.',
                'is_active'          => true,
                'max_household_size' => 20,
                'rules'              => [
                    ['min' => 1, 'max' => 1, 'bags' => 1],
                    ['min' => 2, 'max' => 3, 'bags' => 2],
                    ['min' => 4, 'max' => 6, 'bags' => 3],
                    ['min' => 7, 'max' => null, 'bags' => 4],
                ],
            ],
            [
                'name'               => 'Holiday Distribution',
                'allocation_type'    => 'household_size',
                'description'        => 'Enhanced allocation for holiday events with extra supplies.',
                'is_active'          => true,
                'max_household_size' => 20,
                'rules'              => [
                    ['min' => 1, 'max' => 1, 'bags' => 2],
                    ['min' => 2, 'max' => 3, 'bags' => 3],
                    ['min' => 4, 'max' => 6, 'bags' => 5],
                    ['min' => 7, 'max' => null, 'bags' => 6],
                ],
            ],
            [
                'name'               => 'Mobile Distribution',
                'allocation_type'    => 'household_size',
                'description'        => 'Reduced allocation for mobile pop-up events with limited supplies.',
                'is_active'          => false,
                'max_household_size' => 10,
                'rules'              => [
                    ['min' => 1, 'max' => 2, 'bags' => 1],
                    ['min' => 3, 'max' => 5, 'bags' => 2],
                    ['min' => 6, 'max' => null, 'bags' => 3],
                ],
            ],
            [
                'name'               => 'Family-Based Distribution',
                'allocation_type'    => 'family_count',
                'description'        => 'Allocates bags based on the number of families rather than individual household size.',
                'is_active'          => true,
                'max_household_size' => 10,
                'rules'              => [
                    ['min' => 1, 'max' => 1, 'bags' => 2],
                    ['min' => 2, 'max' => 3, 'bags' => 4],
                    ['min' => 4, 'max' => null, 'bags' => 6],
                ],
            ],
        ];

        foreach ($rulesets as $data) {
            AllocationRuleset::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
