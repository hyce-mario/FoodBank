<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventInventoryAllocation;
use App\Models\EventPreRegistration;
use App\Models\EventReview;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Household;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Volunteer;
use App\Models\VolunteerGroup;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * KioskTestDataSeeder — one-shot seeder for live-testing the Phase 5.11
 * volunteer check-in kiosk redesign.
 *
 * Creates:
 *   • One CURRENT event today + one UPCOMING event tomorrow
 *   • 2 volunteer groups (Packing, Intake)
 *   • 8 volunteers with simple sequential test phones (5550001-5550008)
 *     — 4 pre-assigned to today's event, 4 unassigned (walk-in test)
 *   • 5 households + 3 pre-registrations on today's event
 *   • 2 visible reviews on a past event
 *   • 1 inventory category, 3 items, 1 event allocation
 *   • 2 finance categories, 2 transactions
 *
 * Idempotent — uses firstOrCreate keyed on natural identifiers so re-running
 * is safe. Wraps everything in a transaction so a partial failure rolls back.
 *
 * Run with:
 *   php artisan db:seed --class=KioskTestDataSeeder
 */
class KioskTestDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $today    = $this->seedTodayEvent();
            $tomorrow = $this->seedTomorrowEvent();
            [$packing, $intake] = $this->seedVolunteerGroups();

            $volunteers = $this->seedVolunteers($packing, $intake);
            $this->assignVolunteersToToday($today, $volunteers);

            $households = $this->seedHouseholds();
            $this->seedPreRegistrations($today, $households);

            $this->seedReviews();

            [$category, $items] = $this->seedInventory();
            $this->seedAllocation($today, $items);

            $this->seedFinance($today);

            $this->printSummary($today, $tomorrow, $volunteers);
        });
    }

    // ─────────────────────────────────────────────────────────────────────

    private function seedTodayEvent(): Event
    {
        return Event::firstOrCreate(
            ['name' => 'Spring Distribution Day — TEST', 'date' => Carbon::today()],
            [
                'status'   => 'current',
                'location' => 'Main Hall, 123 Community Way',
                'lanes'    => 2,
                'notes'    => 'Seeded by KioskTestDataSeeder for kiosk check-in testing.',
            ],
        );
    }

    private function seedTomorrowEvent(): Event
    {
        return Event::firstOrCreate(
            ['name' => 'Spring Distribution Day — TEST (Vol. 2)', 'date' => Carbon::tomorrow()],
            [
                'status'   => 'upcoming',
                'location' => 'Main Hall, 123 Community Way',
                'lanes'    => 2,
                'notes'    => 'Seeded by KioskTestDataSeeder.',
            ],
        );
    }

    private function seedVolunteerGroups(): array
    {
        $packing = VolunteerGroup::firstOrCreate(['name' => 'Packing Team']);
        $intake  = VolunteerGroup::firstOrCreate(['name' => 'Intake Crew']);
        return [$packing, $intake];
    }

    /**
     * Eight volunteers with sequential phones (5550001 … 5550008) so user
     * can quickly type them on the kiosk during testing. First four are
     * marked for assignment to today's event (returned as 'assigned'),
     * last four are left unassigned (walk-in path).
     */
    private function seedVolunteers(VolunteerGroup $packing, VolunteerGroup $intake): array
    {
        $rows = [
            ['phone' => '5550001', 'first' => 'Mary',    'last' => 'Johnson',   'role' => 'Coordinator', 'group' => $packing, 'assigned' => true],
            ['phone' => '5550002', 'first' => 'David',   'last' => 'Chen',      'role' => 'Intake',      'group' => $intake,  'assigned' => true],
            ['phone' => '5550003', 'first' => 'Sofia',   'last' => 'Rodriguez', 'role' => 'Loader',      'group' => $packing, 'assigned' => true],
            ['phone' => '5550004', 'first' => 'James',   'last' => 'Williams',  'role' => 'Driver',      'group' => null,     'assigned' => true],
            ['phone' => '5550005', 'first' => 'Aisha',   'last' => 'Patel',     'role' => 'Other',       'group' => null,     'assigned' => false],
            ['phone' => '5550006', 'first' => 'Robert',  'last' => 'Kim',       'role' => 'Loader',      'group' => $packing, 'assigned' => false],
            ['phone' => '5550007', 'first' => 'Emma',    'last' => 'Thompson',  'role' => 'Scanner',     'group' => $intake,  'assigned' => false],
            ['phone' => '5550008', 'first' => 'Carlos',  'last' => 'Mendez',    'role' => 'Other',       'group' => null,     'assigned' => false],
        ];

        $vols = [];
        foreach ($rows as $r) {
            $v = Volunteer::firstOrCreate(
                ['phone' => $r['phone']],
                [
                    'first_name' => $r['first'],
                    'last_name'  => $r['last'],
                    'email'      => strtolower($r['first'].'.'.$r['last']).'@test.local',
                    'role'       => $r['role'],
                ],
            );
            if ($r['group']) {
                $v->groups()->syncWithoutDetaching([$r['group']->id => ['joined_at' => now()]]);
            }
            $vols[] = ['volunteer' => $v, 'assigned' => $r['assigned']];
        }
        return $vols;
    }

    private function assignVolunteersToToday(Event $event, array $volunteers): void
    {
        $assignedIds = collect($volunteers)
            ->filter(fn ($r) => $r['assigned'])
            ->pluck('volunteer.id')
            ->all();
        $event->assignedVolunteers()->syncWithoutDetaching($assignedIds);
    }

    /**
     * Five households spanning small (2) → large (6), various demographics.
     * Numbers are real-feeling test addresses so the visit-log doesn't look
     * obviously fake when the kiosk admin walks the user through.
     */
    private function seedHouseholds(): array
    {
        $rows = [
            ['#TEST-1001', 'Linda',    'Anderson', 'linda.anderson@test.local',   '5557001', 2, 0, 2, 0],
            ['#TEST-1002', 'Marcus',   'Hayes',    'marcus.hayes@test.local',     '5557002', 4, 2, 2, 0],
            ['#TEST-1003', 'Priya',    'Sharma',   'priya.sharma@test.local',     '5557003', 5, 2, 2, 1],
            ['#TEST-1004', 'Thomas',   'Wright',   'thomas.wright@test.local',    '5557004', 3, 1, 2, 0],
            ['#TEST-1005', 'Yuki',     'Tanaka',   'yuki.tanaka@test.local',      '5557005', 6, 3, 2, 1],
        ];
        $households = [];
        foreach ($rows as [$num, $first, $last, $email, $phone, $size, $kids, $adults, $seniors]) {
            $households[] = Household::firstOrCreate(
                ['household_number' => $num],
                [
                    'first_name'      => $first,
                    'last_name'       => $last,
                    'email'           => $email,
                    'phone'           => $phone,
                    'city'            => 'Springfield',
                    'state'           => 'IL',
                    'zip'             => '62701',
                    'household_size'  => $size,
                    'children_count'  => $kids,
                    'adults_count'    => $adults,
                    'seniors_count'   => $seniors,
                ],
            );
        }
        return $households;
    }

    /**
     * Three pre-registrations on today's event:
     *   • Marcus Hayes (matched to existing household)
     *   • Priya Sharma (matched to existing household)
     *   • Robert Brown (new — no household yet, simulates the
     *     reconciliation flow on the attendees tab)
     */
    private function seedPreRegistrations(Event $event, array $households): void
    {
        $matched = [
            ['Marcus', 'Hayes',  $households[1]],
            ['Priya',  'Sharma', $households[2]],
        ];
        foreach ($matched as [$first, $last, $hh]) {
            EventPreRegistration::firstOrCreate(
                ['event_id' => $event->id, 'first_name' => $first, 'last_name' => $last],
                [
                    'email'           => $hh->email,
                    'city'            => $hh->city,
                    'state'           => $hh->state,
                    'zipcode'         => $hh->zip,
                    'household_size'  => $hh->household_size,
                    'children_count'  => $hh->children_count,
                    'adults_count'    => $hh->adults_count,
                    'seniors_count'   => $hh->seniors_count,
                    'household_id'    => $hh->id,
                    'match_status'    => 'matched',
                ],
            );
        }

        EventPreRegistration::firstOrCreate(
            ['event_id' => $event->id, 'first_name' => 'Robert', 'last_name' => 'Brown'],
            [
                'email'           => 'robert.brown@test.local',
                'city'            => 'Springfield',
                'state'           => 'IL',
                'zipcode'         => '62702',
                'household_size'  => 3,
                'children_count'  => 1,
                'adults_count'    => 2,
                'seniors_count'   => 0,
                'match_status'    => 'new',
            ],
        );
    }

    /**
     * Two visible reviews on the most recent past event so the public
     * reviews widget has content. Skips silently if there's no past
     * event yet (fresh-install state).
     */
    private function seedReviews(): void
    {
        $pastEvent = Event::where('status', 'past')->orderByDesc('date')->first();
        if (! $pastEvent) return;

        EventReview::firstOrCreate(
            ['event_id' => $pastEvent->id, 'reviewer_name' => 'Helen K.'],
            [
                'rating'      => 5,
                'review_text' => 'The volunteers were so warm and welcoming. The whole process was smooth and the food was wonderful. Thank you!',
                'email'       => 'helen.k@test.local',
                'is_visible'  => true,
            ],
        );

        EventReview::firstOrCreate(
            ['event_id' => $pastEvent->id, 'reviewer_name' => 'Anonymous'],
            [
                'rating'      => 4,
                'review_text' => 'Well organized. Wait time was a little long but staff did a great job keeping things moving.',
                'is_visible'  => true,
            ],
        );
    }

    private function seedInventory(): array
    {
        $cat = InventoryCategory::firstOrCreate(['name' => 'Pantry Staples']);
        $items = [];
        $rows = [
            ['name' => 'Rice (5lb bag)',             'sku' => 'TEST-RICE-5LB', 'unit_type' => 'bag',   'quantity_on_hand' => 200, 'reorder_level' => 50],
            ['name' => 'Pasta (16oz)',               'sku' => 'TEST-PASTA-16', 'unit_type' => 'box',   'quantity_on_hand' => 350, 'reorder_level' => 75],
            ['name' => 'Canned Vegetables (15oz)',   'sku' => 'TEST-CANVEG-15','unit_type' => 'can',   'quantity_on_hand' => 480, 'reorder_level' => 100],
        ];
        foreach ($rows as $r) {
            $items[] = InventoryItem::firstOrCreate(
                ['sku' => $r['sku']],
                $r + ['category_id' => $cat->id, 'is_active' => true],
            );
        }
        return [$cat, $items];
    }

    private function seedAllocation(Event $event, array $items): void
    {
        // Allocate 50 of each item to today's event so the event-day
        // pages have visible inventory totals.
        foreach ($items as $item) {
            EventInventoryAllocation::firstOrCreate(
                ['event_id' => $event->id, 'inventory_item_id' => $item->id],
                ['allocated_quantity' => 50, 'distributed_quantity' => 0, 'returned_quantity' => 0],
            );
        }
    }

    private function seedFinance(Event $event): void
    {
        $donations = FinanceCategory::firstOrCreate(
            ['name' => 'Donations'],
            ['type' => 'income', 'is_active' => true],
        );
        $supplies = FinanceCategory::firstOrCreate(
            ['name' => 'Food Supplies'],
            ['type' => 'expense', 'is_active' => true],
        );

        FinanceTransaction::firstOrCreate(
            ['title' => 'Community Foundation Q2 Grant', 'transaction_date' => Carbon::today()->subDays(7)],
            [
                'transaction_type' => 'income',
                'category_id'      => $donations->id,
                'amount'           => 2500.00,
                'source_or_payee'  => 'Springfield Community Foundation',
                'payment_method'   => 'check',
                'reference_number' => 'CHK-7831',
                'event_id'         => $event->id,
                'status'           => 'completed',
                'notes'            => 'Seeded by KioskTestDataSeeder.',
            ],
        );

        FinanceTransaction::firstOrCreate(
            ['title' => 'Costco — Pantry Restock', 'transaction_date' => Carbon::today()->subDays(3)],
            [
                'transaction_type' => 'expense',
                'category_id'      => $supplies->id,
                'amount'           => 847.32,
                'source_or_payee'  => 'Costco Wholesale',
                'payment_method'   => 'card',
                'reference_number' => 'INV-44521',
                'event_id'         => $event->id,
                'status'           => 'completed',
                'notes'            => 'Seeded by KioskTestDataSeeder.',
            ],
        );
    }

    private function printSummary(Event $today, Event $tomorrow, array $volunteers): void
    {
        $line = str_repeat('─', 70);
        $this->command->info($line);
        $this->command->info('  KIOSK TEST DATA — READY');
        $this->command->info($line);

        $this->command->info('');
        $this->command->info('  Events:');
        $this->command->line(sprintf('    Today    │ #%d │ %s │ %s lanes', $today->id, $today->name, $today->lanes));
        $this->command->line(sprintf('             │ Auth codes: intake=%s scanner=%s loader=%s exit=%s',
            $today->intake_auth_code, $today->scanner_auth_code,
            $today->loader_auth_code, $today->exit_auth_code,
        ));
        $this->command->line(sprintf('    Tomorrow │ #%d │ %s │ %s lanes', $tomorrow->id, $tomorrow->name, $tomorrow->lanes));
        $this->command->line(sprintf('             │ Auth codes: intake=%s scanner=%s loader=%s exit=%s',
            $tomorrow->intake_auth_code, $tomorrow->scanner_auth_code,
            $tomorrow->loader_auth_code, $tomorrow->exit_auth_code,
        ));

        $this->command->info('');
        $this->command->info('  Volunteers (use these phones on the kiosk):');
        foreach ($volunteers as $row) {
            $v = $row['volunteer'];
            $tag = $row['assigned'] ? '[ASSIGNED]' : '[walk-in ]';
            $this->command->line(sprintf('    %s %s │ %s %s', $tag, $v->phone, str_pad($v->first_name, 8), $v->last_name));
        }
        $this->command->info('');
        $this->command->info('  Open the kiosk:  /Foodbank/public/volunteer-checkin');
        $this->command->info($line);
    }
}
