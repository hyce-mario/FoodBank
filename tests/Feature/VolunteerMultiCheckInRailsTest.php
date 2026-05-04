<?php

namespace Tests\Feature;

use App\Exceptions\VolunteerCheckedInRecentlyException;
use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Services\SettingService;
use App\Services\VolunteerCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5.6.j — multi-check-in safety rails on
 * VolunteerCheckInService::checkIn().
 *
 * Two failure modes the rails address:
 *   Mode A — stale-open auto-close: a row left open for > stale_cap
 *            hours (default 12) is closed at checked_in_at + cap and a
 *            fresh row is started for the current session. Prevents a
 *            "forgot to check out yesterday" row from accumulating
 *            unbounded hours_served.
 *   Mode B — min session gap: a volunteer whose most-recent CLOSED
 *            row's checked_out_at is within min_gap minutes (default 5)
 *            cannot start a new session. Throws
 *            VolunteerCheckedInRecentlyException so the public
 *            controller returns a 422.
 *
 * Both rails apply to the public path (service->checkIn()). Admin
 * manual check-ins via EventVolunteerCheckInController go through their
 * own DB writes and bypass the rails — pinned by a test that
 * exercises that path.
 */
class VolunteerMultiCheckInRailsTest extends TestCase
{
    use RefreshDatabase;

    private VolunteerCheckInService $service;
    private Event $event;
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

        $this->event = Event::create([
            'name'   => 'Multi Check-In Rails',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);

        $this->service = app(VolunteerCheckInService::class);
    }

    private function makeVolunteer(string $phone = '5550001'): Volunteer
    {
        return Volunteer::create([
            'first_name' => 'A',
            'last_name'  => 'B',
            'phone'      => $phone,
            'role'       => 'Loader',
        ]);
    }

    // ─── Mode A — stale-open auto-close ─────────────────────────────────────

    public function test_fresh_open_row_is_returned_idempotently(): void
    {
        $vol = $this->makeVolunteer();
        $first = $this->service->checkIn($this->event, $vol);

        // Re-call immediately — fresh row, returned as-is.
        $second = $this->service->checkIn($this->event, $vol);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, VolunteerCheckIn::where('event_id', $this->event->id)
            ->where('volunteer_id', $vol->id)->count());
    }

    public function test_stale_open_row_is_auto_closed_at_cap_and_new_row_started(): void
    {
        $vol = $this->makeVolunteer();

        // Manually create a row that's older than the default 12h cap.
        $stale = VolunteerCheckIn::create([
            'event_id'       => $this->event->id,
            'volunteer_id'   => $vol->id,
            'role'           => 'Other',
            'source'         => 'walk_in',
            'is_first_timer' => false,
            'checked_in_at'  => now()->subHours(20),
        ]);

        $fresh = $this->service->checkIn($this->event, $vol);

        $stale->refresh();
        $this->assertNotSame($stale->id, $fresh->id, 'A new row must be inserted for the current session');
        $this->assertNotNull($stale->checked_out_at, 'Stale row must be auto-closed');
        $this->assertEquals(12.0, (float) $stale->hours_served,
            'hours_served must be capped to the configured stale_cap, NOT the full 20h drift');

        // checked_out_at should equal checked_in_at + 12h
        $expectedClose = $stale->checked_in_at->copy()->addHours(12);
        $this->assertSame($expectedClose->toDateTimeString(), $stale->checked_out_at->toDateTimeString());
    }

    public function test_stale_cap_is_configurable(): void
    {
        // Tighten the cap to 2h via the setting.
        SettingService::set('event_queue.volunteer_stale_open_hours_cap', 2);
        SettingService::flush();

        $vol = $this->makeVolunteer();
        $stale = VolunteerCheckIn::create([
            'event_id'       => $this->event->id,
            'volunteer_id'   => $vol->id,
            'role'           => 'Other',
            'source'         => 'walk_in',
            'is_first_timer' => false,
            'checked_in_at'  => now()->subHours(5),
        ]);

        $this->service->checkIn($this->event, $vol);

        $stale->refresh();
        $this->assertEquals(2.0, (float) $stale->hours_served,
            'hours_served must reflect the new lower cap');
    }

    // ─── Mode B — min session gap ───────────────────────────────────────────

    public function test_min_gap_refuses_immediate_recheck_in(): void
    {
        $vol = $this->makeVolunteer();
        $first = $this->service->checkIn($this->event, $vol);
        $first->update([
            'checked_out_at' => now()->subSeconds(30),
            'hours_served'   => 1.0,
        ]);

        // Default min_gap is 5 minutes; 30s < 5min so this must throw.
        $this->expectException(VolunteerCheckedInRecentlyException::class);
        $this->service->checkIn($this->event, $vol);
    }

    public function test_exception_carries_seconds_remaining_and_volunteer_id(): void
    {
        $vol = $this->makeVolunteer();
        $first = $this->service->checkIn($this->event, $vol);
        $first->update([
            'checked_out_at' => now()->subSeconds(60),  // 1 min ago, 4 min remaining
            'hours_served'   => 1.0,
        ]);

        try {
            $this->service->checkIn($this->event, $vol);
            $this->fail('Expected exception was not thrown');
        } catch (VolunteerCheckedInRecentlyException $e) {
            $this->assertSame($vol->id, $e->volunteerId);
            $this->assertSame($this->event->id, $e->eventId);
            // Should be ~240 seconds (4 min) remaining; tolerate a couple seconds drift.
            $this->assertGreaterThanOrEqual(235, $e->secondsRemaining);
            $this->assertLessThanOrEqual(245, $e->secondsRemaining);
        }
    }

    public function test_min_gap_allows_recheck_in_after_window_passes(): void
    {
        $vol = $this->makeVolunteer();
        $first = $this->service->checkIn($this->event, $vol);
        $first->update([
            'checked_out_at' => now()->subMinutes(10),  // > 5min default gap
            'hours_served'   => 1.0,
        ]);

        $second = $this->service->checkIn($this->event, $vol);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_min_gap_zero_disables_the_rail(): void
    {
        SettingService::set('event_queue.volunteer_min_session_gap_minutes', 0);
        SettingService::flush();

        $vol = $this->makeVolunteer();
        $first = $this->service->checkIn($this->event, $vol);
        $first->update([
            'checked_out_at' => now()->subSeconds(1),
            'hours_served'   => 1.0,
        ]);

        // Zero gap setting → no refusal even after 1s.
        $second = $this->service->checkIn($this->event, $vol);
        $this->assertNotSame($first->id, $second->id);
    }

    // ─── Public controller surfaces friendly 422 ───────────────────────────

    public function test_public_checkin_returns_422_with_friendly_message_when_min_gap_hit(): void
    {
        $vol = $this->makeVolunteer('5559876');
        $first = $this->service->checkIn($this->event, $vol);
        $first->update([
            'checked_out_at' => now()->subSeconds(30),
            'hours_served'   => 1.0,
        ]);

        $this->postJson(route('volunteer-checkin.checkin'), [
                 'volunteer_id' => $vol->id,
             ])
             ->assertStatus(422)
             ->assertJson(['ok' => false])
             ->assertJsonFragment(['message' => 'You just checked out — please wait about 5 minutes before checking back in.']);
    }

    // ─── Admin bypass — Mode B does NOT apply via the admin path ───────────

    public function test_admin_check_in_path_bypasses_min_gap_rail(): void
    {
        $vol = $this->makeVolunteer();
        $first = $this->service->checkIn($this->event, $vol);
        $first->update([
            'checked_out_at' => now()->subSeconds(30),
            'hours_served'   => 1.0,
        ]);

        // Admin path uses VolunteerCheckIn::create directly via
        // EventVolunteerCheckInController::store — bypasses the service.
        // No 422; the manual check-in proceeds.
        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.store', $this->event), [
                 'volunteer_id' => $vol->id,
             ])
             ->assertRedirect();

        $this->assertSame(2, VolunteerCheckIn::where('event_id', $this->event->id)
            ->where('volunteer_id', $vol->id)->count());
    }
}
