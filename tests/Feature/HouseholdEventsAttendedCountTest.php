<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use App\Services\EventCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6.7 — pin the cached events_attended_count contract.
 * Service increments on attach (atomic with the visit transaction),
 * VisitObserver decrements on Visit deletion.
 */
class HouseholdEventsAttendedCountTest extends TestCase
{
    use RefreshDatabase;

    private function makeHousehold(string $first = 'Test'): Household
    {
        return Household::create([
            'household_number' => substr(md5($first . microtime(true)), 0, 6),
            'first_name'       => $first,
            'last_name'        => 'Family',
            'household_size'   => 2,
            'children_count'   => 0,
            'adults_count'     => 2,
            'seniors_count'    => 0,
            'qr_token'         => substr(md5($first . random_int(0, 99999)), 0, 32),
        ]);
    }

    private function makeEvent(): Event
    {
        return Event::create([
            'name'   => 'Phase 6.7 Test Event',
            'date'   => now()->toDateString(),
            'lanes'  => 1,
            'status' => 'current',
        ]);
    }

    public function test_count_starts_at_zero(): void
    {
        $h = $this->makeHousehold();
        $this->assertSame(0, $h->fresh()->events_attended_count);
    }

    public function test_check_in_increments_count(): void
    {
        $h     = $this->makeHousehold();
        $event = $this->makeEvent();

        app(EventCheckInService::class)->checkIn($event, $h, 1);

        $this->assertSame(1, $h->fresh()->events_attended_count);
    }

    public function test_check_in_increments_count_for_represented_households(): void
    {
        $primary = $this->makeHousehold('Primary');
        $rep1    = $this->makeHousehold('Rep1');
        $rep2    = $this->makeHousehold('Rep2');
        $event   = $this->makeEvent();

        app(EventCheckInService::class)->checkIn($event, $primary, 1, [$rep1->id, $rep2->id]);

        $this->assertSame(1, $primary->fresh()->events_attended_count);
        $this->assertSame(1, $rep1->fresh()->events_attended_count);
        $this->assertSame(1, $rep2->fresh()->events_attended_count);
    }

    public function test_visit_delete_decrements_count(): void
    {
        $h     = $this->makeHousehold();
        $event = $this->makeEvent();

        $visit = app(EventCheckInService::class)->checkIn($event, $h, 1);
        $this->assertSame(1, $h->fresh()->events_attended_count);

        Visit::find($visit->id)->delete();

        $this->assertSame(0, $h->fresh()->events_attended_count);
    }

    public function test_decrement_clamps_at_zero(): void
    {
        $h     = $this->makeHousehold();
        $event = $this->makeEvent();

        // No prior check-in — count is 0. Create a visit manually then delete it.
        $visit = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'queue_position' => 1,
            'visit_status' => 'checked_in',
            'start_time'   => now(),
        ]);
        $visit->households()->attach($h->id, $h->toVisitPivotSnapshot());

        // Force count to 0 directly to simulate stale data
        $h->update(['events_attended_count' => 0]);

        $visit->delete();

        // Should still be 0, not -1
        $this->assertSame(0, $h->fresh()->events_attended_count);
    }
}
