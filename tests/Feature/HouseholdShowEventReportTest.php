<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\Event;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\EventCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B — pin the event-history report on households.show.
 *
 * Stat cards count only exited visits. Bag totals use the event's
 * AllocationRuleset against the visit_households snapshot size (not the
 * live household.household_size), keeping the totals temporally stable.
 * Representative-pickup visits show up on the represented household's
 * report tagged with the driver household's name.
 */
class HouseholdShowEventReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Administrator', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $adminRole->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeHousehold(string $first, int $size = 2): Household
    {
        return Household::create([
            'household_number' => substr(md5($first . microtime(true)), 0, 6),
            'first_name'       => $first,
            'last_name'        => 'Family',
            'household_size'   => $size,
            'children_count'   => 0,
            'adults_count'     => $size,
            'seniors_count'    => 0,
            'qr_token'         => substr(md5($first . random_int(0, 99999)), 0, 32),
        ]);
    }

    private function makeEvent(string $name, ?string $date = null, ?int $rulesetId = null): Event
    {
        return Event::create([
            'name'       => $name,
            'date'       => $date ?? now()->toDateString(),
            'lanes'      => 1,
            'status'     => 'current',
            'ruleset_id' => $rulesetId,
        ]);
    }

    private function makeRuleset(int $bagsFor1to3 = 1, int $bagsFor4plus = 3): AllocationRuleset
    {
        return AllocationRuleset::create([
            'name'               => 'Test Ruleset',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 20,
            'rules'              => [
                ['min' => 1, 'max' => 3,    'bags' => $bagsFor1to3],
                ['min' => 4, 'max' => null, 'bags' => $bagsFor4plus],
            ],
        ]);
    }

    private function exitVisit(Visit $visit): void
    {
        $visit->update([
            'visit_status' => 'exited',
            'exited_at'    => now(),
        ]);
    }

    public function test_stat_cards_count_only_exited_visits(): void
    {
        $ruleset = $this->makeRuleset(bagsFor1to3: 2);
        $hh      = $this->makeHousehold('Alice', size: 2);

        $event1 = $this->makeEvent('Event One', '2026-04-01', $ruleset->id);
        $event2 = $this->makeEvent('Event Two', '2026-04-15', $ruleset->id);
        $event3 = $this->makeEvent('Event Three (in progress)', '2026-05-01', $ruleset->id);

        $service = app(EventCheckInService::class);
        $v1 = $service->checkIn($event1, $hh, 1);
        $v2 = $service->checkIn($event2, $hh, 1);
        $v3 = $service->checkIn($event3, $hh, 1); // stays checked_in

        $this->exitVisit($v1);
        $this->exitVisit($v2);

        $response = $this->actingAs($this->admin)->get(route('households.show', $hh));
        $response->assertOk();

        // 2 exited visits × 2 bags = 4 bags. Mid-flow visit excluded.
        $stats = $response->viewData('historyStats');
        $this->assertSame(2, $stats['total_visits']);
        $this->assertSame(4, $stats['total_bags_received']);
        $this->assertSame('2026-04-15', $stats['last_served_at']->toDateString());
    }

    public function test_bags_use_pivot_snapshot_not_live_size(): void
    {
        // Snapshot was 2 (1 bag). Edit household to size 5 afterwards (would
        // be 3 bags if we read live). Stat must still report 1 bag.
        $ruleset = $this->makeRuleset(bagsFor1to3: 1, bagsFor4plus: 3);
        $hh      = $this->makeHousehold('Bob', size: 2);
        $event   = $this->makeEvent('Snapshot Event', '2026-04-10', $ruleset->id);

        $visit = app(EventCheckInService::class)->checkIn($event, $hh, 1);
        $this->exitVisit($visit);

        // Mutate live household after the visit completed.
        $hh->update(['household_size' => 5, 'adults_count' => 5]);

        $response = $this->actingAs($this->admin)->get(route('households.show', $hh));
        $stats    = $response->viewData('historyStats');

        $this->assertSame(1, $stats['total_visits']);
        $this->assertSame(1, $stats['total_bags_received']); // snapshot=2 → 1 bag
    }

    public function test_representative_pickup_appears_with_driver_name(): void
    {
        $ruleset = $this->makeRuleset(bagsFor1to3: 1);
        $linda   = $this->makeHousehold('Linda', size: 2); // driver
        $bob     = $this->makeHousehold('Bob', size: 1);   // represented
        $event   = $this->makeEvent('Rep Pickup Event', '2026-04-20', $ruleset->id);

        $visit = app(EventCheckInService::class)->checkIn($event, $linda, 1, [$bob->id]);
        $this->exitVisit($visit);

        // Bob's page should show the visit tagged "Picked up by Linda".
        $response = $this->actingAs($this->admin)->get(route('households.show', $bob));
        $response->assertOk();
        $response->assertSee('Picked up by');
        $response->assertSee('Linda Family');
    }

    public function test_self_pickup_does_not_show_picked_up_by_label(): void
    {
        $ruleset = $this->makeRuleset(bagsFor1to3: 1);
        $hh      = $this->makeHousehold('Solo', size: 1);
        $event   = $this->makeEvent('Self Pickup Event', '2026-04-22', $ruleset->id);

        $visit = app(EventCheckInService::class)->checkIn($event, $hh, 1);
        $this->exitVisit($visit);

        $response = $this->actingAs($this->admin)->get(route('households.show', $hh));
        $response->assertOk();
        $response->assertDontSee('Picked up by');
    }
}
