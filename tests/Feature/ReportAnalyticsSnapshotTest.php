<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use App\Services\EventCheckInService;
use App\Services\ReportAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 1.2.c — pins the temporal stability of analytics reports.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.2.
 *
 * The headline contract: editing a household's demographics or vehicle
 * info AFTER a visit must NOT change historical report totals. Pre-1.2
 * the reports SUM-joined live `households.*`, so a single admin edit
 * silently rewrote every prior month's "people served" number. After
 * 1.2.b's snapshot-on-attach + 1.2.c's switch-to-pivot-reads, those
 * totals are fixed at attach-time and stable forever.
 */
class ReportAnalyticsSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private EventCheckInService $checkInService;
    private ReportAnalyticsService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkInService = app(EventCheckInService::class);
        $this->reports        = app(ReportAnalyticsService::class);
    }

    private function makeHousehold(string $first, string $last, array $overrides = []): Household
    {
        static $counter = 0;
        $counter++;

        return Household::create(array_merge([
            'household_number' => 'RPT' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'first_name'       => $first,
            'last_name'        => $last,
            'household_size'   => 1,
            'adults_count'     => 1,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ], $overrides));
    }

    /**
     * Check a household into an event and exit it on the same day so it
     * appears in `exited` reports.
     */
    private function checkInAndExit(Event $event, Household $h, ?array $representedIds = null): Visit
    {
        $visit = $this->checkInService->checkIn($event, $h, 1, $representedIds);
        $visit->update([
            'visit_status' => 'exited',
            'exited_at'    => now(),
            'end_time'     => now(),
            'served_bags'  => 1,
        ]);
        return $visit->fresh();
    }

    private function makePastEvent(string $name, string $date = '2026-04-15'): Event
    {
        return Event::create([
            'name'   => $name,
            'date'   => $date,
            'lanes'  => 1,
            'status' => 'past',
        ]);
    }

    /**
     * THE headline regression: edit a household's size after a visit;
     * report totals must not move. Verified via `eventPerformance()`,
     * which is sqlite-portable. The other report methods (`overview()`,
     * `overviewTrend()`, `trends()`) use the same `vh.household_size`
     * SUM pattern but contain MySQL-specific SQL (`TIMESTAMPDIFF`,
     * `DATE_FORMAT`, `YEARWEEK`) that doesn't run on the in-memory
     * sqlite test DB. Their changes are visually identical to the
     * demographics tests below.
     */
    public function test_event_performance_people_does_not_change_when_household_size_is_edited_post_visit(): void
    {
        $event = $this->makePastEvent('Event-perf test');
        $h     = $this->makeHousehold('Perf', 'Hh', ['household_size' => 7]);

        $visit = $this->checkInAndExit($event, $h);
        $visit->update(['start_time' => Carbon::parse('2026-04-15 10:00:00'), 'exited_at' => Carbon::parse('2026-04-15 10:30:00')]);

        $from = Carbon::parse('2026-04-01');
        $to   = Carbon::parse('2026-04-30')->endOfDay();

        $before = $this->reports->eventPerformance($from, $to);
        $this->assertSame(7, $before[0]['people_served']);

        $h->update(['household_size' => 100]);

        $after = $this->reports->eventPerformance($from, $to);
        $this->assertSame(7, $after[0]['people_served']);
    }

    public function test_demographics_size_distribution_groups_by_snapshot_not_live(): void
    {
        $event = $this->makePastEvent('Demographics test');
        $h     = $this->makeHousehold('Demo', 'Hh', ['household_size' => 3]);

        $visit = $this->checkInAndExit($event, $h);
        $visit->update(['start_time' => Carbon::parse('2026-04-15 10:00:00'), 'exited_at' => Carbon::parse('2026-04-15 10:30:00')]);

        $from = Carbon::parse('2026-04-01');
        $to   = Carbon::parse('2026-04-30')->endOfDay();

        // Edit the household's CURRENT size — historical breakdown must
        // still bucket the visit at the snapshotted size 3, not 8.
        $h->update(['household_size' => 8]);

        $demo = $this->reports->demographics($from, $to);

        $this->assertCount(1, $demo['sizeDist']);
        $this->assertSame(3, (int) $demo['sizeDist']->first()->size);
        $this->assertSame(1, (int) $demo['sizeDist']->first()->count);
    }

    public function test_demographics_vehicle_distribution_uses_snapshot(): void
    {
        $event = $this->makePastEvent('Vehicle test');
        $h     = $this->makeHousehold('Veh', 'Hh', ['household_size' => 2, 'vehicle_make' => 'Toyota']);

        $visit = $this->checkInAndExit($event, $h);
        $visit->update(['start_time' => Carbon::parse('2026-04-15 10:00:00'), 'exited_at' => Carbon::parse('2026-04-15 10:30:00')]);

        $from = Carbon::parse('2026-04-01');
        $to   = Carbon::parse('2026-04-30')->endOfDay();

        // Change the vehicle on the household. The historical breakdown
        // must still credit Toyota.
        $h->update(['vehicle_make' => 'Lamborghini']);

        $demo = $this->reports->demographics($from, $to);

        $this->assertCount(1, $demo['vehicleDist']);
        $this->assertSame('Toyota', $demo['vehicleDist']->first()->vehicle_make);
    }

    /**
     * Representative pickup: rep + 2 represented = 3 households, sum of
     * their household_size at attach-time. Editing any of them later
     * must not move the total. Verified via `eventPerformance()` (the
     * sqlite-portable path).
     */
    public function test_representative_pickup_people_served_uses_each_households_snapshot(): void
    {
        $event = $this->makePastEvent('Rep-pickup test');

        $rep   = $this->makeHousehold('Rep', 'Self',  ['household_size' => 2]);
        $r1    = $this->makeHousehold('Rep1', 'Mem1', ['household_size' => 4]);
        $r2    = $this->makeHousehold('Rep2', 'Mem2', ['household_size' => 5]);

        $visit = $this->checkInAndExit($event, $rep, [$r1->id, $r2->id]);
        $visit->update(['start_time' => Carbon::parse('2026-04-15 10:00:00'), 'exited_at' => Carbon::parse('2026-04-15 10:30:00')]);

        $from = Carbon::parse('2026-04-01');
        $to   = Carbon::parse('2026-04-30')->endOfDay();

        $before = $this->reports->eventPerformance($from, $to);
        $this->assertSame(2 + 4 + 5, $before[0]['people_served']);

        // Bulk-edit all three households after the fact.
        $rep->update(['household_size' => 99]);
        $r1->update(['household_size' => 99]);
        $r2->update(['household_size' => 99]);

        $after = $this->reports->eventPerformance($from, $to);
        $this->assertSame(11, $after[0]['people_served'], 'rep pickup snapshot must capture each represented household separately');
    }
}
