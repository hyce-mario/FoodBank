<?php

namespace Database\Seeders;

use App\Models\Volunteer;
use App\Models\VolunteerGroup;
use Illuminate\Database\Seeder;

class VolunteerSeeder extends Seeder
{
    public function run(): void
    {
        // ── Groups ────────────────────────────────────────────────────────────
        $groups = [
            ['name' => 'Morning Crew',     'description' => 'Early shift volunteers handling intake and sorting from 6am–11am.'],
            ['name' => 'Afternoon Team',   'description' => 'Covers distribution and loading from 11am–4pm.'],
            ['name' => 'Driver Squad',     'description' => 'Certified drivers for food pickup and delivery routes.'],
            ['name' => 'Youth Group',      'description' => 'Young adult volunteers (18–25) assisting with scanning and coordination.'],
        ];

        foreach ($groups as $g) {
            VolunteerGroup::firstOrCreate(['name' => $g['name']], ['description' => $g['description']]);
        }

        $morning   = VolunteerGroup::where('name', 'Morning Crew')->first();
        $afternoon = VolunteerGroup::where('name', 'Afternoon Team')->first();
        $drivers   = VolunteerGroup::where('name', 'Driver Squad')->first();
        $youth     = VolunteerGroup::where('name', 'Youth Group')->first();

        // ── Volunteers ────────────────────────────────────────────────────────
        $volunteers = [
            // Morning Crew members
            ['first_name' => 'Patricia', 'last_name' => 'Williams',  'email' => 'p.williams@example.com',  'phone' => '717-555-0101', 'role' => 'Coordinator', 'groups' => [$morning]],
            ['first_name' => 'James',    'last_name' => 'Henderson',  'email' => 'j.henderson@example.com', 'phone' => '717-555-0102', 'role' => 'Intake',      'groups' => [$morning]],
            ['first_name' => 'Sandra',   'last_name' => 'Mitchell',   'email' => 's.mitchell@example.com',  'phone' => '717-555-0103', 'role' => 'Intake',      'groups' => [$morning]],
            ['first_name' => 'Robert',   'last_name' => 'Carter',     'email' => 'r.carter@example.com',    'phone' => '717-555-0104', 'role' => 'Loader',      'groups' => [$morning]],
            ['first_name' => 'Linda',    'last_name' => 'Brooks',     'email' => 'l.brooks@example.com',    'phone' => '717-555-0105', 'role' => 'Scanner',     'groups' => [$morning, $youth]],

            // Afternoon Team members
            ['first_name' => 'Michael',  'last_name' => 'Thompson',   'email' => 'm.thompson@example.com',  'phone' => '717-555-0106', 'role' => 'Coordinator', 'groups' => [$afternoon]],
            ['first_name' => 'Barbara',  'last_name' => 'Johnson',    'email' => 'b.johnson@example.com',   'phone' => '717-555-0107', 'role' => 'Loader',      'groups' => [$afternoon]],
            ['first_name' => 'David',    'last_name' => 'Martinez',   'email' => 'd.martinez@example.com',  'phone' => '717-555-0108', 'role' => 'Loader',      'groups' => [$afternoon]],
            ['first_name' => 'Susan',    'last_name' => 'Anderson',   'email' => 's.anderson@example.com',  'phone' => '717-555-0109', 'role' => 'Scanner',     'groups' => [$afternoon]],
            ['first_name' => 'Charles',  'last_name' => 'Taylor',     'email' => 'c.taylor@example.com',    'phone' => '717-555-0110', 'role' => 'Intake',      'groups' => [$afternoon, $morning]],

            // Driver Squad members
            ['first_name' => 'Thomas',   'last_name' => 'Jackson',    'email' => 't.jackson@example.com',   'phone' => '717-555-0111', 'role' => 'Driver',      'groups' => [$drivers]],
            ['first_name' => 'Margaret', 'last_name' => 'White',      'email' => 'm.white@example.com',     'phone' => '717-555-0112', 'role' => 'Driver',      'groups' => [$drivers]],
            ['first_name' => 'Kevin',    'last_name' => 'Harris',     'email' => 'k.harris@example.com',    'phone' => '717-555-0113', 'role' => 'Driver',      'groups' => [$drivers]],
            ['first_name' => 'Dorothy',  'last_name' => 'Lewis',      'email' => 'd.lewis@example.com',     'phone' => '717-555-0114', 'role' => 'Driver',      'groups' => [$drivers, $afternoon]],
            ['first_name' => 'Steven',   'last_name' => 'Robinson',   'email' => 's.robinson@example.com',  'phone' => '717-555-0115', 'role' => 'Driver',      'groups' => [$drivers]],

            // Youth Group members
            ['first_name' => 'Ashley',   'last_name' => 'Walker',     'email' => 'a.walker@example.com',    'phone' => '717-555-0116', 'role' => 'Scanner',     'groups' => [$youth]],
            ['first_name' => 'Brandon',  'last_name' => 'Hall',       'email' => 'b.hall@example.com',      'phone' => '717-555-0117', 'role' => 'Scanner',     'groups' => [$youth]],
            ['first_name' => 'Megan',    'last_name' => 'Allen',      'email' => 'm.allen@example.com',     'phone' => '717-555-0118', 'role' => 'Intake',      'groups' => [$youth]],
            ['first_name' => 'Tyler',    'last_name' => 'Young',      'email' => 't.young@example.com',     'phone' => '717-555-0119', 'role' => 'Other',       'groups' => [$youth]],
            ['first_name' => 'Emily',    'last_name' => 'King',       'email' => 'e.king@example.com',      'phone' => '717-555-0120', 'role' => 'Coordinator', 'groups' => [$youth, $morning]],

            // Unaffiliated volunteers (no group)
            ['first_name' => 'George',   'last_name' => 'Scott',      'email' => 'g.scott@example.com',     'phone' => '717-555-0121', 'role' => 'Loader',      'groups' => []],
            ['first_name' => 'Helen',    'last_name' => 'Green',      'email' => 'h.green@example.com',     'phone' => '717-555-0122', 'role' => 'Other',       'groups' => []],
            ['first_name' => 'Raymond',  'last_name' => 'Adams',      'email' => 'r.adams@example.com',     'phone' => '717-555-0123', 'role' => 'Intake',      'groups' => []],
            ['first_name' => 'Deborah',  'last_name' => 'Nelson',     'email' => 'd.nelson@example.com',    'phone' => '717-555-0124', 'role' => 'Scanner',     'groups' => []],
            ['first_name' => 'Gary',     'last_name' => 'Baker',      'email' => 'g.baker@example.com',     'phone' => '717-555-0125', 'role' => 'Driver',      'groups' => []],
        ];

        foreach ($volunteers as $data) {
            $groupModels = $data['groups'];
            unset($data['groups']);

            $volunteer = Volunteer::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            foreach ($groupModels as $group) {
                $volunteer->groups()->syncWithoutDetaching([
                    $group->id => ['joined_at' => now()],
                ]);
            }
        }
    }
}
