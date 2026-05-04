<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Services\VolunteerCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the post-fix re-check-in semantics for VolunteerCheckInService.
 *
 * Before the fix, the unique constraint on (event_id, volunteer_id) plus
 * VolunteerCheckInService::checkIn()'s updateOrCreate(...) caused a
 * volunteer who checked out and later checked back in for the same event
 * to silently overwrite the first session — wiping checked_out_at and
 * the first session's hours_served.
 *
 * After the fix:
 *   - check-in while already open → idempotent (returns existing row)
 *   - check-in after checkout → NEW row, prior session preserved
 *   - both sessions' hours_served sum correctly
 *   - "events served" counts distinct events, not rows
 */
class VolunteerReCheckInTest extends TestCase
{
    use RefreshDatabase;

    private VolunteerCheckInService $service;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);

        $this->event = Event::create([
            'name'   => 'Re-Check-In Test',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);

        $this->service = app(VolunteerCheckInService::class);
    }

    private function makeVolunteer(string $first = 'Alice', string $last = 'A'): Volunteer
    {
        return Volunteer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'role'       => 'Loader',
        ]);
    }

    public function test_first_check_in_creates_a_row(): void
    {
        $vol = $this->makeVolunteer();

        $ci = $this->service->checkIn($this->event, $vol);

        $this->assertSame(1, VolunteerCheckIn::where('event_id', $this->event->id)
            ->where('volunteer_id', $vol->id)->count());
        $this->assertNull($ci->checked_out_at);
        $this->assertTrue($ci->is_first_timer);
    }

    public function test_check_in_while_already_open_is_idempotent(): void
    {
        $vol   = $this->makeVolunteer();
        $first = $this->service->checkIn($this->event, $vol);

        // Re-call without checking out first → returns the same row.
        $second = $this->service->checkIn($this->event, $vol);

        $this->assertSame($first->id, $second->id, 'Second check-in should return the open row');
        $this->assertSame(1, VolunteerCheckIn::where('event_id', $this->event->id)
            ->where('volunteer_id', $vol->id)->count(), 'No new row should be inserted');
    }

    public function test_recheck_in_after_checkout_creates_new_row_and_preserves_history(): void
    {
        $vol = $this->makeVolunteer();

        // Session 1 — 2 hours
        $first = $this->service->checkIn($this->event, $vol);
        $first->update([
            'checked_in_at'  => now()->subHours(3),
            'checked_out_at' => now()->subHours(1),
            'hours_served'   => 2.0,
        ]);

        // Session 2 — fresh check-in, same event, after a break
        $second = $this->service->checkIn($this->event, $vol);

        $this->assertNotSame(
            $first->id, $second->id,
            'A re-check-in after checkout must produce a NEW row, not overwrite the prior session'
        );

        $first->refresh();
        $this->assertNotNull($first->checked_out_at, 'Prior session must keep its checked_out_at');
        $this->assertEquals(2.0, (float) $first->hours_served, 'Prior session hours_served must be preserved');

        $this->assertSame(2, VolunteerCheckIn::where('event_id', $this->event->id)
            ->where('volunteer_id', $vol->id)->count(), 'Two rows should exist after re-check-in');
    }

    public function test_stats_counts_distinct_events_not_rows(): void
    {
        $vol = $this->makeVolunteer();

        // Two sessions on the SAME event — one closed, one open.
        $closed = $this->service->checkIn($this->event, $vol);
        $closed->update([
            'checked_out_at' => now()->subHour(),
            'hours_served'   => 1.5,
        ]);
        $this->service->checkIn($this->event, $vol);

        $stats = $this->service->stats($vol->fresh());

        $this->assertSame(1, $stats['totalEvents'], 'Two rows on one event = 1 event served');
        $this->assertSame(2, $stats['checkIns']->count(), 'Both rows should still be returned');
        $this->assertEquals(1.5, $stats['totalHours'], 'Total hours sums across rows');
    }

    public function test_isfirsttimer_uses_distinct_events_not_rows(): void
    {
        $vol = $this->makeVolunteer();

        $first = $this->service->checkIn($this->event, $vol);
        $first->update(['checked_out_at' => now()->subHour(), 'hours_served' => 1]);
        $this->service->checkIn($this->event, $vol);

        // Two rows, but one event → still a first-timer.
        $this->assertTrue($vol->fresh()->isFirstTimer());

        // Add a second event with one check-in → no longer a first-timer.
        $event2 = Event::create([
            'name' => 'Second Event', 'date' => now()->subWeek()->toDateString(),
            'status' => 'past', 'lanes' => 1,
        ]);
        $this->service->checkIn($event2, $vol);

        $this->assertFalse($vol->fresh()->isFirstTimer());
    }
}
