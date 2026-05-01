<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E — admin volunteer check-in / bulk check-in / checkout.
 *
 * Three actions surfaced on the event detail page volunteers tab:
 *   POST   /events/{event}/volunteer-checkins              single
 *   POST   /events/{event}/volunteer-checkins/bulk         all assigned
 *   PATCH  /events/{event}/volunteer-checkins/{ci}/checkout single
 *
 * The contract these tests pin:
 *   - auth required
 *   - volunteer_id must exist
 *   - duplicate check-ins are rejected (same volunteer, same event, open row)
 *   - bulk only inserts for assigned volunteers NOT already checked in
 *   - bulk skips silently if everyone is checked in
 *   - checkout sets checked_out_at + computes hours_served
 *   - checkout refuses to re-close an already-closed row
 *   - checkout enforces event ownership of the check-in id
 */
class EventVolunteerCheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;

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
            'name'   => 'Volunteer Check-In Test',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);
    }

    private function makeVolunteer(string $first, string $last, string $role = 'Loader'): Volunteer
    {
        return Volunteer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'role'       => $role,
        ]);
    }

    // ─── Single check-in ─────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_check_in(): void
    {
        $vol = $this->makeVolunteer('A', 'B');
        $this->post(route('events.volunteer-checkins.store', $this->event), [
            'volunteer_id' => $vol->id,
        ])->assertRedirect('/login');
    }

    public function test_admin_can_check_in_single_volunteer_with_explicit_time(): void
    {
        $vol = $this->makeVolunteer('Alice', 'A');
        $when = now()->subMinutes(15)->format('Y-m-d\TH:i');

        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.store', $this->event), [
                 'volunteer_id'  => $vol->id,
                 'checked_in_at' => $when,
             ])
             ->assertRedirect(route('events.show', $this->event));

        $this->assertDatabaseHas('volunteer_check_ins', [
            'event_id'     => $this->event->id,
            'volunteer_id' => $vol->id,
            'role'         => 'Loader',         // inherited from volunteer's default
            'source'       => 'pre_assigned',
        ]);
    }

    public function test_check_in_defaults_time_to_now_when_omitted(): void
    {
        $vol = $this->makeVolunteer('Bob', 'B');

        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.store', $this->event), [
                 'volunteer_id' => $vol->id,
             ])
             ->assertRedirect();

        $ci = VolunteerCheckIn::where('event_id', $this->event->id)
            ->where('volunteer_id', $vol->id)
            ->firstOrFail();

        $this->assertNotNull($ci->checked_in_at);
        $this->assertTrue(now()->diffInSeconds($ci->checked_in_at) <= 5);
    }

    public function test_duplicate_open_check_in_is_rejected(): void
    {
        $vol = $this->makeVolunteer('Carol', 'C');
        $this->event->volunteerCheckIns()->create([
            'volunteer_id'  => $vol->id,
            'role'          => 'Loader',
            'source'        => 'pre_assigned',
            'checked_in_at' => now(),
        ]);

        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.store', $this->event), [
                 'volunteer_id' => $vol->id,
             ])
             ->assertRedirect()
             ->assertSessionHas('error');

        // Still only one row.
        $this->assertSame(1, VolunteerCheckIn::where('volunteer_id', $vol->id)->count());
    }

    public function test_check_in_validates_volunteer_exists(): void
    {
        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.store', $this->event), [
                 'volunteer_id' => 99999,
             ])
             ->assertSessionHasErrors(['volunteer_id']);
    }

    // ─── Bulk check-in ───────────────────────────────────────────────────────

    public function test_bulk_check_in_creates_rows_for_every_assigned_not_yet_checked_in(): void
    {
        $a = $this->makeVolunteer('Bulk', 'One');
        $b = $this->makeVolunteer('Bulk', 'Two');
        $c = $this->makeVolunteer('Bulk', 'Three');
        $this->event->assignedVolunteers()->attach([$a->id, $b->id, $c->id]);

        // One of them is already checked in — must NOT be double-inserted.
        $this->event->volunteerCheckIns()->create([
            'volunteer_id'  => $b->id,
            'role'          => 'Loader',
            'source'        => 'pre_assigned',
            'checked_in_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.bulk', $this->event))
             ->assertRedirect();

        // Two new rows + the pre-existing one = 3 total, never duplicates.
        $this->assertSame(3, VolunteerCheckIn::where('event_id', $this->event->id)->count());
        $this->assertTrue(VolunteerCheckIn::where('volunteer_id', $a->id)->exists());
        $this->assertTrue(VolunteerCheckIn::where('volunteer_id', $c->id)->exists());
    }

    public function test_bulk_check_in_with_no_assigned_volunteers_returns_friendly_error(): void
    {
        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.bulk', $this->event))
             ->assertRedirect()
             ->assertSessionHas('error');
    }

    public function test_bulk_check_in_when_everyone_already_checked_in_is_a_clean_noop(): void
    {
        $a = $this->makeVolunteer('Done', 'One');
        $this->event->assignedVolunteers()->attach($a->id);
        $this->event->volunteerCheckIns()->create([
            'volunteer_id'  => $a->id,
            'role'          => 'Loader',
            'source'        => 'pre_assigned',
            'checked_in_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.bulk', $this->event))
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertSame(1, VolunteerCheckIn::where('event_id', $this->event->id)->count());
    }

    // ─── Checkout ────────────────────────────────────────────────────────────

    public function test_checkout_records_time_and_computes_hours_served(): void
    {
        $vol = $this->makeVolunteer('Out', 'One');
        $checkedInAt = now()->subHours(3)->subMinutes(30); // 3.5 hours ago
        $ci = $this->event->volunteerCheckIns()->create([
            'volunteer_id'  => $vol->id,
            'role'          => 'Loader',
            'source'        => 'pre_assigned',
            'checked_in_at' => $checkedInAt,
        ]);

        $this->actingAs($this->admin)
             ->patch(route('events.volunteer-checkins.checkout', [$this->event, $ci]))
             ->assertRedirect();

        $ci->refresh();
        $this->assertNotNull($ci->checked_out_at);
        // Allow ±0.05 hours (3 min) tolerance for test runtime.
        $this->assertEqualsWithDelta(3.5, (float) $ci->hours_served, 0.05);
    }

    public function test_checkout_with_explicit_time_uses_admin_provided_value(): void
    {
        $vol = $this->makeVolunteer('Out', 'Two');
        $checkedInAt = now()->subHours(2);
        $ci = $this->event->volunteerCheckIns()->create([
            'volunteer_id'  => $vol->id,
            'role'          => 'Loader',
            'source'        => 'pre_assigned',
            'checked_in_at' => $checkedInAt,
        ]);

        $explicitOut = $checkedInAt->copy()->addMinutes(90); // exactly 1.5 hours later

        $this->actingAs($this->admin)
             ->patch(route('events.volunteer-checkins.checkout', [$this->event, $ci]), [
                 'checked_out_at' => $explicitOut->format('Y-m-d\TH:i'),
             ])
             ->assertRedirect();

        $ci->refresh();
        // Delta covers the sub-minute precision loss from the datetime-local
        // input format (Y-m-d\TH:i, no seconds). Real-world tolerance.
        $this->assertEqualsWithDelta(1.5, (float) $ci->hours_served, 0.05);
    }

    public function test_checkout_rejects_already_closed_row(): void
    {
        $vol = $this->makeVolunteer('Out', 'Three');
        $ci = $this->event->volunteerCheckIns()->create([
            'volunteer_id'   => $vol->id,
            'role'           => 'Loader',
            'source'         => 'pre_assigned',
            'checked_in_at'  => now()->subHour(),
            'checked_out_at' => now(),
            'hours_served'   => 1.0,
        ]);

        $this->actingAs($this->admin)
             ->patch(route('events.volunteer-checkins.checkout', [$this->event, $ci]))
             ->assertStatus(422);
    }

    // ─── Bulk checkout ───────────────────────────────────────────────────────

    public function test_bulk_checkout_closes_every_active_check_in_for_this_event(): void
    {
        $a = $this->makeVolunteer('Bulk', 'Out1');
        $b = $this->makeVolunteer('Bulk', 'Out2');
        $c = $this->makeVolunteer('Bulk', 'Out3');

        $this->event->volunteerCheckIns()->createMany([
            ['volunteer_id' => $a->id, 'role' => 'Loader', 'source' => 'pre_assigned',
             'checked_in_at' => now()->subHours(2)],
            ['volunteer_id' => $b->id, 'role' => 'Loader', 'source' => 'pre_assigned',
             'checked_in_at' => now()->subHour()],
            // Already checked out — must be left alone (no overwrite).
            ['volunteer_id' => $c->id, 'role' => 'Loader', 'source' => 'pre_assigned',
             'checked_in_at'  => now()->subHours(3),
             'checked_out_at' => now()->subHours(1),
             'hours_served'   => 2.0],
        ]);

        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.bulk-checkout', $this->event))
             ->assertRedirect()
             ->assertSessionHas('success');

        // The two open rows should now be closed with computed hours.
        $rowA = VolunteerCheckIn::where('volunteer_id', $a->id)->firstOrFail();
        $rowB = VolunteerCheckIn::where('volunteer_id', $b->id)->firstOrFail();
        $rowC = VolunteerCheckIn::where('volunteer_id', $c->id)->firstOrFail();

        $this->assertNotNull($rowA->checked_out_at);
        $this->assertNotNull($rowB->checked_out_at);
        $this->assertEqualsWithDelta(2.0, (float) $rowA->hours_served, 0.05);
        $this->assertEqualsWithDelta(1.0, (float) $rowB->hours_served, 0.05);

        // Pre-existing closed row is untouched.
        $this->assertEqualsWithDelta(2.0, (float) $rowC->hours_served, 0.001);
    }

    public function test_bulk_checkout_with_no_active_check_ins_is_a_clean_noop(): void
    {
        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.bulk-checkout', $this->event))
             ->assertRedirect()
             ->assertSessionHas('success');
    }

    public function test_bulk_checkout_unauthenticated_redirects_to_login(): void
    {
        $this->post(route('events.volunteer-checkins.bulk-checkout', $this->event))
             ->assertRedirect('/login');
    }

    public function test_bulk_checkout_only_affects_this_event(): void
    {
        // Create open rows in TWO events; bulk-checkout on event A must not
        // touch event B's rows. Pins event-scope safety.
        $other = Event::create([
            'name' => 'Other', 'date' => now()->toDateString(),
            'status' => 'current', 'lanes' => 1,
        ]);
        $vA = $this->makeVolunteer('Scope', 'A');
        $vB = $this->makeVolunteer('Scope', 'B');

        $this->event->volunteerCheckIns()->create([
            'volunteer_id'  => $vA->id, 'role' => 'Loader', 'source' => 'pre_assigned',
            'checked_in_at' => now()->subHour(),
        ]);
        $other->volunteerCheckIns()->create([
            'volunteer_id'  => $vB->id, 'role' => 'Loader', 'source' => 'pre_assigned',
            'checked_in_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
             ->post(route('events.volunteer-checkins.bulk-checkout', $this->event))
             ->assertRedirect();

        $this->assertNotNull(VolunteerCheckIn::where('volunteer_id', $vA->id)->first()->checked_out_at);
        $this->assertNull(VolunteerCheckIn::where('volunteer_id', $vB->id)->first()->checked_out_at);
    }

    public function test_checkout_refuses_cross_event_id(): void
    {
        // Create a check-in row for a DIFFERENT event, then attempt to
        // close it via THIS event's URL. Must 404 — guards against URL
        // tampering that would otherwise let an admin close a check-in on
        // an event they're not viewing.
        $other = Event::create([
            'name' => 'Other Event', 'date' => now()->toDateString(),
            'status' => 'current', 'lanes' => 1,
        ]);
        $vol = $this->makeVolunteer('Cross', 'Event');
        $ci = $other->volunteerCheckIns()->create([
            'volunteer_id'  => $vol->id,
            'role'          => 'Loader',
            'source'        => 'pre_assigned',
            'checked_in_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
             ->patch(route('events.volunteer-checkins.checkout', [$this->event, $ci]))
             ->assertNotFound();
    }
}
