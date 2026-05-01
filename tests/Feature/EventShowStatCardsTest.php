<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — Live stat cards on the Event details page.
 *
 * Pins the four stat-card numbers so a future refactor can't silently break
 * them back to placeholder zeros. Each test seeds the minimum data shape
 * required and asserts the rendered HTML carries the right total. The
 * "Food Pack Served" rename is also pinned here so a copy edit elsewhere
 * doesn't drift it back to "Food Bundle".
 */
class EventShowStatCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeHousehold(string $name, int $size = 3): Household
    {
        static $counter = 0;
        $counter++;
        [$first, $last] = explode(' ', $name) + ['', ''];
        return Household::create([
            'household_number' => str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'first_name'       => $first ?: 'Test',
            'last_name'        => $last ?: 'Household',
            'household_size'   => $size,
            'children_count'   => 1,
            'adults_count'     => max(1, $size - 1),
            'seniors_count'    => 0,
            'qr_token'         => str_repeat('a', 32 + $counter),
        ]);
    }

    private function makeEvent(): Event
    {
        return Event::create([
            'name'   => 'Stat Card Event',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);
    }

    public function test_food_pack_label_replaces_food_bundle(): void
    {
        $event = $this->makeEvent();
        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSee('Food Pack Served')
             ->assertDontSee('Food Bundle Served');
    }

    public function test_packs_served_sums_served_bags_across_exited_visits_only(): void
    {
        $event = $this->makeEvent();
        $hh1   = $this->makeHousehold('Alice One');
        $hh2   = $this->makeHousehold('Bob Two');
        $hh3   = $this->makeHousehold('Carol Three');

        // Two exited visits — bags should be summed.
        $v1 = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'exited',
            'start_time'   => now()->subHour(),
            'end_time'     => now(),
            'served_bags'  => 4,
        ]);
        $v1->households()->attach($hh1->id, $hh1->toVisitPivotSnapshot());

        $v2 = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'exited',
            'start_time'   => now()->subHour(),
            'end_time'     => now(),
            'served_bags'  => 7,
        ]);
        $v2->households()->attach($hh2->id, $hh2->toVisitPivotSnapshot());

        // One in-progress visit — must NOT be counted (still queued).
        $v3 = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'queued',
            'start_time'   => now(),
            'served_bags'  => 99, // would distort the sum if counted
        ]);
        $v3->households()->attach($hh3->id, $hh3->toVisitPivotSnapshot());

        // 4 + 7 = 11
        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSeeInOrder(['11', 'Food Pack Served']);
    }

    public function test_households_served_counts_distinct_households_at_exited_visits(): void
    {
        $event = $this->makeEvent();
        $hh1 = $this->makeHousehold('A One');
        $hh2 = $this->makeHousehold('B Two');
        $hh3 = $this->makeHousehold('C Three'); // represented family

        // Visit 1: hh1 alone.
        $v1 = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'exited',
            'start_time'   => now()->subHour(),
            'end_time'     => now(),
            'served_bags'  => 2,
        ]);
        $v1->households()->attach($hh1->id, $hh1->toVisitPivotSnapshot());

        // Visit 2: hh2 with a represented hh3 (rep pickup) — counts as 2 households.
        $v2 = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'exited',
            'start_time'   => now()->subHour(),
            'end_time'     => now(),
            'served_bags'  => 3,
        ]);
        $v2->households()->attach($hh2->id, $hh2->toVisitPivotSnapshot());
        $v2->households()->attach($hh3->id, $hh3->toVisitPivotSnapshot());

        // 3 distinct households at exited visits.
        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSeeInOrder(['3', 'Households']);
    }

    public function test_volunteers_served_counts_check_ins(): void
    {
        $event = $this->makeEvent();
        $vol1  = Volunteer::create(['first_name' => 'V1', 'last_name' => 'A', 'role' => 'Loader']);
        $vol2  = Volunteer::create(['first_name' => 'V2', 'last_name' => 'B', 'role' => 'Driver']);

        VolunteerCheckIn::create([
            'event_id'      => $event->id,
            'volunteer_id'  => $vol1->id,
            'role'          => 'Loader',
            'source'        => 'pre_assigned',
            'checked_in_at' => now(),
        ]);
        VolunteerCheckIn::create([
            'event_id'      => $event->id,
            'volunteer_id'  => $vol2->id,
            'role'          => 'Driver',
            'source'        => 'walk_in',
            'checked_in_at' => now(),
        ]);

        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSeeInOrder(['2', 'Volunteer Served']);
    }

    public function test_attendees_count_renders_pre_registrations(): void
    {
        $event = $this->makeEvent();
        for ($i = 1; $i <= 5; $i++) {
            EventPreRegistration::create([
                'event_id'         => $event->id,
                'attendee_number'  => str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'first_name'       => "Pre{$i}",
                'last_name'        => 'Reg',
                'email'            => "pre{$i}@test.local",
                'household_size'   => 2,
                'children_count'   => 0,
                'adults_count'     => 2,
                'seniors_count'    => 0,
                'match_status'     => 'unmatched',
            ]);
        }

        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSeeInOrder(['5', 'Attendees']);
    }

    public function test_zero_event_renders_zeros_in_every_card(): void
    {
        // Brand-new event with no visits, no volunteers, no pre-regs — every
        // stat must render as 0, not as the literal "—" or a blank cell.
        $event = $this->makeEvent();

        $response = $this->actingAs($this->admin)
                         ->get(route('events.show', $event))
                         ->assertOk();

        // Each label must be visible alongside its zero, in the same order
        // they appear in the grid.
        $response->assertSeeInOrder([
            '0', 'Food Pack Served',
            '0', 'Households',
            '0', 'Volunteer Served',
            '0', 'Attendees',
        ]);
    }
}
