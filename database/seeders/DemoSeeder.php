<?php

namespace Database\Seeders;

use App\Models\AllocationRuleset;
use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\EventReview;
use App\Models\Household;
use App\Models\Visit;
use App\Models\Volunteer;
use App\Models\VolunteerGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    // ─── Entry point ──────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->command->info('Seeding allocation rulesets…');
        $this->call(AllocationRulesetSeeder::class);

        $this->command->info('Seeding households…');
        $households = $this->seedHouseholds();

        $this->command->info('Seeding events…');
        $this->seedEvents($households);

        $this->command->info('Demo data seeded successfully.');
    }

    // ─── Households ───────────────────────────────────────────────────────────

    private function seedHouseholds(): array
    {
        // Each entry: primary household fields + optional 'represents' array
        // of sub-households that will be linked to this one as representative.
        $data = [
            [
                'first_name' => 'Maria',    'last_name' => 'Gonzalez',
                'email' => 'mgonzalez@demo.com',  'phone' => '717-555-0201',
                'city' => 'Lancaster', 'state' => 'PA', 'zip' => '17601',
                'vehicle_color' => 'Silver', 'vehicle_make' => 'Toyota',
                'children_count' => 1, 'adults_count' => 2, 'seniors_count' => 1,
            ],
            [
                'first_name' => 'James',    'last_name' => 'Wilson',
                'email' => 'jwilson@demo.com',    'phone' => '717-555-0202',
                'city' => 'York', 'state' => 'PA', 'zip' => '17401',
                'vehicle_color' => 'Blue', 'vehicle_make' => 'Ford',
                'children_count' => 0, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Linda',    'last_name' => 'Okafor',
                'email' => 'lokafor@demo.com',    'phone' => '717-555-0203',
                'city' => 'Harrisburg', 'state' => 'PA', 'zip' => '17101',
                'vehicle_color' => 'White', 'vehicle_make' => 'Honda',
                'children_count' => 1, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Derek',    'last_name' => 'Thompson',
                'email' => 'dthompson@demo.com',  'phone' => '717-555-0204',
                'city' => 'Reading', 'state' => 'PA', 'zip' => '19601',
                'vehicle_color' => 'Black', 'vehicle_make' => 'Chevrolet',
                'children_count' => 0, 'adults_count' => 1, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Aisha',    'last_name' => 'Patel',
                'email' => 'apatel@demo.com',     'phone' => '717-555-0205',
                'city' => 'Lebanon', 'state' => 'PA', 'zip' => '17042',
                'vehicle_color' => 'Red', 'vehicle_make' => 'Nissan',
                'children_count' => 2, 'adults_count' => 2, 'seniors_count' => 1,
            ],
            [
                'first_name' => 'Calvin',   'last_name' => 'Morris',
                'email' => 'cmorris@demo.com',    'phone' => '717-555-0206',
                'city' => 'Allentown', 'state' => 'PA', 'zip' => '18101',
                'vehicle_color' => 'Gray', 'vehicle_make' => 'Dodge',
                'children_count' => 0, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Priya',    'last_name' => 'Sharma',
                'email' => 'psharma@demo.com',    'phone' => '717-555-0207',
                'city' => 'Lancaster', 'state' => 'PA', 'zip' => '17602',
                'vehicle_color' => 'White', 'vehicle_make' => 'Kia',
                'children_count' => 3, 'adults_count' => 2, 'seniors_count' => 1,
            ],
            [
                'first_name' => 'Robert',   'last_name' => 'Jefferson',
                'email' => 'rjefferson@demo.com', 'phone' => '717-555-0208',
                'city' => 'York', 'state' => 'PA', 'zip' => '17402',
                'vehicle_color' => 'Green', 'vehicle_make' => 'Hyundai',
                'children_count' => 1, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            // ── Representative households (picking up for another family) ────────
            [
                'first_name' => 'Rosa',     'last_name' => 'Martinez',
                'email' => 'rmartinez@demo.com',  'phone' => '717-555-0209',
                'city' => 'Lancaster', 'state' => 'PA', 'zip' => '17603',
                'vehicle_color' => 'Brown', 'vehicle_make' => 'Jeep',
                'children_count' => 2, 'adults_count' => 1, 'seniors_count' => 0,
                'represents' => [
                    [
                        'first_name' => 'Elena',  'last_name' => 'Martinez',
                        'email' => 'elena.m@demo.com', 'phone' => null,
                        'children_count' => 2, 'adults_count' => 2, 'seniors_count' => 0,
                        'notes' => 'Rosa\'s sister — picked up together.',
                    ],
                ],
            ],
            [
                'first_name' => 'Thomas',   'last_name' => 'Nguyen',
                'email' => 'tnguyen@demo.com',    'phone' => '717-555-0210',
                'city' => 'Harrisburg', 'state' => 'PA', 'zip' => '17102',
                'vehicle_color' => 'Silver', 'vehicle_make' => 'Subaru',
                'children_count' => 0, 'adults_count' => 2, 'seniors_count' => 0,
                'represents' => [
                    [
                        'first_name' => 'Linh', 'last_name' => 'Nguyen',
                        'email' => null, 'phone' => '717-555-0220',
                        'children_count' => 0, 'adults_count' => 1, 'seniors_count' => 1,
                        'notes' => 'Thomas\'s mother.',
                    ],
                ],
            ],
            [
                'first_name' => 'Fatima',   'last_name' => 'Hassan',
                'email' => 'fhassan@demo.com',    'phone' => '717-555-0211',
                'city' => 'York', 'state' => 'PA', 'zip' => '17403',
                'vehicle_color' => 'Black', 'vehicle_make' => 'Toyota',
                'children_count' => 2, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Marcus',   'last_name' => 'Reed',
                'email' => 'mreed@demo.com',      'phone' => '717-555-0212',
                'city' => 'Reading', 'state' => 'PA', 'zip' => '19602',
                'vehicle_color' => 'Blue', 'vehicle_make' => 'GMC',
                'children_count' => 1, 'adults_count' => 2, 'seniors_count' => 1,
            ],
            [
                'first_name' => 'Nadia',    'last_name' => 'Kowalski',
                'email' => 'nkowalski@demo.com',  'phone' => '717-555-0213',
                'city' => 'Allentown', 'state' => 'PA', 'zip' => '18102',
                'vehicle_color' => 'White', 'vehicle_make' => 'Ford',
                'children_count' => 2, 'adults_count' => 2, 'seniors_count' => 2,
            ],
            [
                'first_name' => 'Jerome',   'last_name' => 'Butler',
                'email' => 'jbutler@demo.com',    'phone' => '717-555-0214',
                'city' => 'Lancaster', 'state' => 'PA', 'zip' => '17604',
                'vehicle_color' => 'Red', 'vehicle_make' => 'Chevrolet',
                'children_count' => 3, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Grace',    'last_name' => 'Owens',
                'email' => 'gowens@demo.com',     'phone' => '717-555-0215',
                'city' => 'Harrisburg', 'state' => 'PA', 'zip' => '17103',
                'vehicle_color' => 'Gray', 'vehicle_make' => 'Honda',
                'children_count' => 3, 'adults_count' => 3, 'seniors_count' => 1,
                'notes' => 'Large household — may need extra bags.',
            ],
            [
                'first_name' => 'Elijah',   'last_name' => 'Barnes',
                'email' => 'ebarnes@demo.com',    'phone' => '717-555-0216',
                'city' => 'York', 'state' => 'PA', 'zip' => '17404',
                'vehicle_color' => 'Silver', 'vehicle_make' => 'Ram',
                'children_count' => 2, 'adults_count' => 2, 'seniors_count' => 2,
            ],
            [
                'first_name' => 'Isabelle', 'last_name' => 'Fontaine',
                'email' => 'ifontaine@demo.com',  'phone' => '717-555-0217',
                'city' => 'Lancaster', 'state' => 'PA', 'zip' => '17601',
                'vehicle_color' => 'White', 'vehicle_make' => 'Mazda',
                'children_count' => 0, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Carlos',   'last_name' => 'Rivera',
                'email' => 'crivera@demo.com',    'phone' => '717-555-0218',
                'city' => 'Reading', 'state' => 'PA', 'zip' => '19603',
                'vehicle_color' => 'Black', 'vehicle_make' => 'Honda',
                'children_count' => 2, 'adults_count' => 2, 'seniors_count' => 0,
            ],
            [
                'first_name' => 'Wendy',    'last_name' => 'Chambers',
                'email' => 'wchambers@demo.com',  'phone' => '717-555-0219',
                'city' => 'Lebanon', 'state' => 'PA', 'zip' => '17042',
                'vehicle_color' => 'Blue', 'vehicle_make' => 'Volkswagen',
                'children_count' => 1, 'adults_count' => 2, 'seniors_count' => 1,
            ],
        ];

        $created = [];
        foreach ($data as $row) {
            $existing = Household::where('email', $row['email'])->first();
            if ($existing) {
                $created[] = $existing;
                continue;
            }

            $children = (int) $row['children_count'];
            $adults   = (int) $row['adults_count'];
            $seniors  = (int) $row['seniors_count'];

            $household = Household::create([
                'household_number' => $this->uniqueHouseholdNumber(),
                'first_name'       => $row['first_name'],
                'last_name'        => $row['last_name'],
                'email'            => $row['email'],
                'phone'            => $row['phone'],
                'city'             => $row['city'],
                'state'            => $row['state'],
                'zip'              => $row['zip'],
                'vehicle_make'     => $row['vehicle_make'],
                'vehicle_color'    => $row['vehicle_color'],
                'children_count'   => $children,
                'adults_count'     => $adults,
                'seniors_count'    => $seniors,
                'household_size'   => max(1, $children + $adults + $seniors),
                'notes'            => $row['notes'] ?? null,
                'qr_token'         => Str::uuid()->toString(),
            ]);

            // Create any represented households and link them
            foreach ($row['represents'] ?? [] as $repRow) {
                $existingRep = $repRow['email']
                    ? Household::where('email', $repRow['email'])->first()
                    : null;

                if (! $existingRep) {
                    $rc = (int) ($repRow['children_count'] ?? 0);
                    $ra = (int) ($repRow['adults_count']   ?? 0);
                    $rs = (int) ($repRow['seniors_count']  ?? 0);

                    Household::create([
                        'household_number'            => $this->uniqueHouseholdNumber(),
                        'first_name'                  => $repRow['first_name'],
                        'last_name'                   => $repRow['last_name'],
                        'email'                       => $repRow['email'] ?? null,
                        'phone'                       => $repRow['phone'] ?? null,
                        'children_count'              => $rc,
                        'adults_count'                => $ra,
                        'seniors_count'               => $rs,
                        'household_size'              => max(1, $rc + $ra + $rs),
                        'notes'                       => $repRow['notes'] ?? null,
                        'representative_household_id' => $household->id,
                        'qr_token'                    => Str::uuid()->toString(),
                    ]);
                }
            }

            $created[] = $household;
        }

        return $created;
    }

    // ─── Events ───────────────────────────────────────────────────────────────

    private function seedEvents(array $households): void
    {
        $standard  = AllocationRuleset::where('name', 'Standard Distribution')->first();
        $holiday   = AllocationRuleset::where('name', 'Holiday Distribution')->first();
        $family    = AllocationRuleset::where('name', 'Family-Based Distribution')->first();

        $morning   = VolunteerGroup::where('name', 'Morning Crew')->first();
        $afternoon = VolunteerGroup::where('name', 'Afternoon Team')->first();
        $youth     = VolunteerGroup::where('name', 'Youth Group')->first();

        $vols = Volunteer::all()->keyBy(fn($v) => "{$v->first_name} {$v->last_name}");

        $events = [
            [
                'name'            => 'Saturday Community Pantry',
                'date'            => now()->subDays(9)->toDateString(),
                'location'        => '234 Community Ave, Lancaster, PA',
                'lanes'           => 2,
                'ruleset'         => $standard,
                'volunteer_group' => $morning,
                'volunteers'      => ['Patricia Williams', 'James Henderson', 'Sandra Mitchell', 'Robert Carter', 'Linda Brooks'],
                'notes'           => 'Standard Saturday distribution. Focused on families with children.',
                'attendees'       => array_slice($households, 0, 14),
                'past_visits'     => true,
                'reviews'         => [
                    ['rating' => 5, 'review_text' => 'Very organized and kind staff. We were in and out quickly. Thank you!', 'reviewer_name' => 'Maria G.', 'email' => null],
                    ['rating' => 4, 'review_text' => 'Smooth process. Parking was a little tight but everyone was helpful.', 'reviewer_name' => null, 'email' => 'jwilson@demo.com'],
                    ['rating' => 5, 'review_text' => 'Great event. The volunteers were very respectful and efficient.', 'reviewer_name' => 'L. Okafor', 'email' => null],
                    ['rating' => 3, 'review_text' => 'Wait time was longer than expected but the food quality was excellent.', 'reviewer_name' => null, 'email' => null],
                    ['rating' => 5, 'review_text' => 'This event makes such a difference for our family. So grateful.', 'reviewer_name' => 'Rosa M.', 'email' => 'rmartinez@demo.com'],
                ],
            ],
            [
                'name'            => 'Eastside Mobile Pop-Up',
                'date'            => now()->subDays(6)->toDateString(),
                'location'        => '88 Eastside Blvd, York, PA',
                'lanes'           => 1,
                'ruleset'         => $family,
                'volunteer_group' => $youth,
                'volunteers'      => ['Ashley Walker', 'Brandon Hall', 'Megan Allen', 'Tyler Young', 'Emily King'],
                'notes'           => 'Mobile distribution targeting underserved east side residents.',
                'attendees'       => array_slice($households, 4, 10),
                'past_visits'     => true,
                'reviews'         => [
                    ['rating' => 4, 'review_text' => 'The young volunteers were so energetic and positive. Loved it.', 'reviewer_name' => 'Aisha P.', 'email' => null],
                    ['rating' => 5, 'review_text' => 'Convenient location. Easy drive-through setup. Will come back!', 'reviewer_name' => null, 'email' => 'cmorris@demo.com'],
                    ['rating' => 4, 'review_text' => 'Great selection of food. Could use more lanes for faster service.', 'reviewer_name' => 'Priya S.', 'email' => null],
                ],
            ],
            [
                'name'            => 'Spring Community Food Drive',
                'date'            => now()->subDays(2)->toDateString(),
                'location'        => '550 Spring Gardens, Harrisburg, PA',
                'lanes'           => 3,
                'ruleset'         => $holiday,
                'volunteer_group' => $afternoon,
                'volunteers'      => ['Michael Thompson', 'Barbara Johnson', 'David Martinez', 'Susan Anderson', 'Charles Taylor', 'Dorothy Lewis'],
                'notes'           => 'Seasonal drive with enhanced food packages. Includes fresh produce.',
                'attendees'       => array_slice($households, 2, 16),
                'past_visits'     => true,
                'reviews'         => [
                    ['rating' => 5, 'review_text' => 'Best event yet! Fresh produce and great variety. The staff went above and beyond.', 'reviewer_name' => 'Fatima H.', 'email' => null],
                    ['rating' => 5, 'review_text' => 'Very well organized. Three lanes kept everything moving fast. Impressed!', 'reviewer_name' => 'Marcus R.', 'email' => 'mreed@demo.com'],
                    ['rating' => 4, 'review_text' => 'Excellent food distribution. Parking could be better but overall very good.', 'reviewer_name' => null, 'email' => 'nkowalski@demo.com'],
                    ['rating' => 5, 'review_text' => 'Such a blessing for our community. Thank you to all the volunteers!', 'reviewer_name' => 'Grace O.', 'email' => null],
                    ['rating' => 3, 'review_text' => 'Line moved slowly at first but got better. Food was great though.', 'reviewer_name' => null, 'email' => null],
                    ['rating' => 4, 'review_text' => 'Really appreciated the spring event. Hope to see this become a regular!', 'reviewer_name' => 'Jerome B.', 'email' => null],
                ],
            ],
            [
                'name'            => 'Tuesday Regular Distribution',
                'date'            => now()->toDateString(),
                'location'        => '410 Main Street, Lancaster, PA',
                'lanes'           => 2,
                'ruleset'         => $standard,
                'volunteer_group' => $morning,
                'volunteers'      => ['Patricia Williams', 'James Henderson', 'Robert Carter', 'Emily King', 'George Scott'],
                'notes'           => 'Weekly Tuesday distribution. All household types welcome.',
                'attendees'       => array_slice($households, 0, 12),
                'past_visits'     => false,
                'reviews'         => [],
            ],
            [
                'name'            => 'Saturday Community Pantry',
                'date'            => now()->addDays(5)->toDateString(),
                'location'        => '234 Community Ave, Lancaster, PA',
                'lanes'           => 2,
                'ruleset'         => $standard,
                'volunteer_group' => $morning,
                'volunteers'      => ['Patricia Williams', 'Sandra Mitchell', 'Linda Brooks', 'Charles Taylor'],
                'notes'           => 'Regular bi-weekly Saturday distribution.',
                'attendees'       => array_slice($households, 6, 8),
                'past_visits'     => false,
                'reviews'         => [],
            ],
            [
                'name'            => 'Month-End Mega Distribution',
                'date'            => now()->addDays(12)->toDateString(),
                'location'        => '1200 Expo Center Dr, Reading, PA',
                'lanes'           => 3,
                'ruleset'         => $holiday,
                'volunteer_group' => $afternoon,
                'volunteers'      => ['Michael Thompson', 'Barbara Johnson', 'David Martinez', 'Thomas Jackson', 'Margaret White', 'Kevin Harris'],
                'notes'           => 'Large-scale month-end distribution with expanded inventory and 3 service lanes.',
                'attendees'       => array_slice($households, 0, 10),
                'past_visits'     => false,
                'reviews'         => [],
            ],
        ];

        foreach ($events as $cfg) {
            $this->seedEvent($cfg, $vols, $households);
        }
    }

    private function seedEvent(array $cfg, $allVols, array $allHouseholds): void
    {
        $event = Event::firstOrCreate(
            ['name' => $cfg['name'], 'date' => $cfg['date']],
            [
                'location'           => $cfg['location'],
                'lanes'              => $cfg['lanes'],
                'ruleset_id'         => $cfg['ruleset']?->id,
                'volunteer_group_id' => $cfg['volunteer_group']?->id,
                'notes'              => $cfg['notes'],
            ]
        );

        $volIds = [];
        foreach ($cfg['volunteers'] as $name) {
            if (isset($allVols[$name])) {
                $volIds[] = $allVols[$name]->id;
            }
        }
        if ($volIds) {
            $event->assignedVolunteers()->syncWithoutDetaching($volIds);
        }

        foreach ($cfg['attendees'] as $household) {
            /** @var Household $household */
            $alreadyRegistered = EventPreRegistration::where('event_id', $event->id)
                ->where(function ($q) use ($household) {
                    $q->where('email', $household->email)
                      ->orWhere('household_id', $household->id);
                })->exists();

            if ($alreadyRegistered) continue;

            EventPreRegistration::create([
                'event_id'       => $event->id,
                'attendee_number'=> EventPreRegistration::generateAttendeeNumber(),
                'first_name'     => $household->first_name,
                'last_name'      => $household->last_name,
                'email'          => $household->email,
                'city'           => $household->city,
                'state'          => $household->state,
                'zipcode'        => $household->zip,
                'household_size' => $household->household_size,
                'children_count' => $household->children_count,
                'adults_count'   => $household->adults_count,
                'seniors_count'  => $household->seniors_count,
                'household_id'   => $household->id,
                'match_status'   => 'matched',
            ]);
        }

        if ($cfg['past_visits']) {
            $this->seedPastVisits($event, $cfg['attendees']);
        } elseif ($event->date->isToday()) {
            $this->seedTodayVisits($event, $cfg['attendees'], $cfg['lanes']);
        }

        foreach ($cfg['reviews'] as $review) {
            $alreadyExists = EventReview::where('event_id', $event->id)
                ->where('review_text', $review['review_text'])
                ->exists();

            if ($alreadyExists) continue;

            EventReview::create([
                'event_id'      => $event->id,
                'rating'        => $review['rating'],
                'review_text'   => $review['review_text'],
                'reviewer_name' => $review['reviewer_name'],
                'email'         => $review['email'],
                'is_visible'    => true,
                'created_at'    => \Carbon\Carbon::parse($event->date)->addHours(rand(2, 8)),
            ]);
        }
    }

    private function seedPastVisits(Event $event, array $households): void
    {
        $lanes     = $event->lanes;
        $startHour = \Carbon\Carbon::parse($event->date)->setTime(9, 0);

        foreach ($households as $idx => $household) {
            /** @var Household $household */
            $alreadyVisited = Visit::where('event_id', $event->id)
                ->whereHas('households', fn($q) => $q->where('households.id', $household->id))
                ->exists();

            if ($alreadyVisited) continue;

            $laneNum   = ($idx % $lanes) + 1;
            $startTime = $startHour->copy()->addMinutes($idx * 8);
            $endTime   = $startTime->copy()->addMinutes(rand(4, 12));

            $visit = Visit::create([
                'event_id'       => $event->id,
                'lane'           => $laneNum,
                'queue_position' => (int) floor($idx / $lanes) + 1,
                'start_time'     => $startTime,
                'end_time'       => $endTime,
                'served_bags'    => $this->bagsFor($event, $household->household_size),
            ]);

            // Phase 1.2.b: snapshot demographics + vehicle on the pivot at
            // attach time. Required by the NOT NULL constraint on the
            // demographic columns of `visit_households`. The shared helper
            // on Household keeps the seeder + service in lockstep so a
            // future snapshot column needs only one update.
            $visit->households()->attach($household->id, $household->toVisitPivotSnapshot());
        }
    }

    private function seedTodayVisits(Event $event, array $households, int $lanes): void
    {
        $now       = now();
        $startHour = $now->copy()->startOfDay()->setTime(8, 30);

        $completedCount = (int) ceil(count($households) * 0.4);

        foreach ($households as $idx => $household) {
            /** @var Household $household */
            $alreadyVisited = Visit::where('event_id', $event->id)
                ->whereHas('households', fn($q) => $q->where('households.id', $household->id))
                ->exists();

            if ($alreadyVisited) continue;

            $laneNum    = ($idx % $lanes) + 1;
            $startTime  = $startHour->copy()->addMinutes($idx * 10);
            $isComplete = $idx < $completedCount;
            $exitTime   = $isComplete ? $startTime->copy()->addMinutes(rand(5, 15)) : null;

            $visit = Visit::create([
                'event_id'             => $event->id,
                'lane'                 => $laneNum,
                'queue_position'       => (int) floor($idx / $lanes) + 1,
                'visit_status'         => $isComplete ? 'exited' : 'checked_in',
                'start_time'           => $startTime,
                'queued_at'            => $isComplete ? $startTime->copy()->addMinutes(1) : null,
                'loading_completed_at' => $isComplete ? $startTime->copy()->addMinutes(rand(2, 4)) : null,
                'exited_at'            => $exitTime,
                'end_time'             => $exitTime,
                'served_bags'          => $isComplete ? $this->bagsFor($event, $household->household_size) : 0,
            ]);

            // Phase 1.2.b: snapshot demographics + vehicle on the pivot at
            // attach time. Required by the NOT NULL constraint on the
            // demographic columns of `visit_households`. The shared helper
            // on Household keeps the seeder + service in lockstep so a
            // future snapshot column needs only one update.
            $visit->households()->attach($household->id, $household->toVisitPivotSnapshot());
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function bagsFor(Event $event, int $size): int
    {
        $ruleset = $event->ruleset;
        return $ruleset ? $ruleset->getBagsFor($size) : 0;
    }

    private function uniqueHouseholdNumber(): string
    {
        do {
            $n = (string) random_int(100000, 999999);
        } while (Household::where('household_number', $n)->exists());

        return $n;
    }
}
