<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5.6.f — pins the new RESTRICT behavior on volunteer_check_ins
 * foreign keys. Pre-fix, deleting a volunteer or event silently wiped
 * its check-in history via cascadeOnDelete; post-fix, both
 * VolunteerController::destroy and EventController::destroy refuse
 * the delete with a friendly error when prior check-ins exist, and
 * the DB-level FK is RESTRICT as a defense-in-depth backstop.
 */
class VolunteerCheckInsRestrictDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function makeEventWithVolunteer(): array
    {
        $event = Event::create([
            'name'   => 'Restrict Test',
            'date'   => now()->toDateString(),
            'status' => 'past',
            'lanes'  => 1,
        ]);
        $vol = Volunteer::create([
            'first_name' => 'Hist',
            'last_name'  => 'Service',
            'phone'      => '5550000',
        ]);
        $checkIn = VolunteerCheckIn::create([
            'event_id'       => $event->id,
            'volunteer_id'   => $vol->id,
            'role'           => 'Other',
            'source'         => 'walk_in',
            'is_first_timer' => true,
            'checked_in_at'  => now()->subHours(3),
            'checked_out_at' => now()->subHour(),
            'hours_served'   => 2.0,
        ]);
        return compact('event', 'vol', 'checkIn');
    }

    // ─── Volunteer destroy ──────────────────────────────────────────────────

    public function test_deleting_volunteer_with_check_ins_is_refused_with_friendly_error(): void
    {
        ['vol' => $vol] = $this->makeEventWithVolunteer();

        $this->actingAs($this->admin)
             ->from(route('volunteers.show', $vol))
             ->delete(route('volunteers.destroy', $vol))
             ->assertRedirect(route('volunteers.show', $vol))
             ->assertSessionHas('error');

        $this->assertDatabaseHas('volunteers', ['id' => $vol->id]);
        $this->assertDatabaseHas('volunteer_check_ins', ['volunteer_id' => $vol->id]);
    }

    public function test_deleting_volunteer_with_no_check_ins_succeeds(): void
    {
        $vol = Volunteer::create(['first_name' => 'A', 'last_name' => 'B', 'phone' => '5559999']);

        $this->actingAs($this->admin)
             ->delete(route('volunteers.destroy', $vol))
             ->assertRedirect(route('volunteers.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseMissing('volunteers', ['id' => $vol->id]);
    }

    // ─── Event destroy ──────────────────────────────────────────────────────

    public function test_deleting_event_with_volunteer_check_ins_is_refused(): void
    {
        ['event' => $event] = $this->makeEventWithVolunteer();

        $this->actingAs($this->admin)
             ->from(route('events.show', $event))
             ->delete(route('events.destroy', $event))
             ->assertRedirect(route('events.show', $event))
             ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_deleting_event_with_no_volunteer_check_ins_succeeds(): void
    {
        $event = Event::create([
            'name'   => 'Empty',
            'date'   => now()->toDateString(),
            'status' => 'past',
            'lanes'  => 1,
        ]);

        $this->actingAs($this->admin)
             ->delete(route('events.destroy', $event))
             ->assertRedirect(route('events.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }
}
