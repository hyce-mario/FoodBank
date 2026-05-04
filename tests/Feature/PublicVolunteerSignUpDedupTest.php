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
 * Phase 5.6.h — public sign-up dedups by phone instead of always creating
 * a new Volunteer row.
 *
 * Contract pinned here:
 *   - phone is required on signup (the dedup key)
 *   - phone match → existing volunteer is checked in, no new row
 *   - phone match → submitted name + email are IGNORED (cannot trust
 *     unauthenticated public input to update an existing record)
 *   - phone match + already-checked-in → idempotent (no double row,
 *     no error — relies on 5.6.b's checkIn() open-row guard)
 *   - phone is new + email collision → 422 friendly error, no row
 *   - phone is new + email is new → fresh Volunteer + check-in row
 *   - response shape includes is_existing flag so frontend can vary
 *     copy ("Welcome back" vs "Thanks for signing up")
 */
class PublicVolunteerSignUpDedupTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle middleware doesn't matter for these tests since
        // we only POST a few times; leaving it on exercises the live
        // route binding.
        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);

        // Public check-in needs a current event (Event::current() scope).
        $this->event = Event::create([
            'name'   => 'Public Signup Dedup Test',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);
    }

    public function test_phone_is_required_on_signup(): void
    {
        $this->postJson(route('volunteer-checkin.signup'), [
                 'first_name' => 'A',
                 'last_name'  => 'B',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors('phone');

        $this->assertSame(0, Volunteer::count());
    }

    public function test_new_phone_creates_volunteer_and_checks_in(): void
    {
        $this->postJson(route('volunteer-checkin.signup'), [
                 'first_name' => 'New',
                 'last_name'  => 'Person',
                 'phone'      => '5551111',
             ])
             ->assertOk()
             ->assertJson([
                 'ok'             => true,
                 'is_existing'    => false,
                 'is_first_timer' => true,
                 'full_name'      => 'New Person',
             ]);

        $this->assertSame(1, Volunteer::count());
        $this->assertSame(1, VolunteerCheckIn::count());
        $this->assertDatabaseHas('volunteers', [
            'first_name' => 'New',
            'phone'      => '5551111',
        ]);
    }

    public function test_existing_phone_checks_in_existing_volunteer_no_new_row(): void
    {
        $existing = Volunteer::create([
            'first_name' => 'Existing',
            'last_name'  => 'Bob',
            'phone'      => '5552222',
            'email'      => 'bob@test.local',
            'role'       => 'Driver',
        ]);

        $this->postJson(route('volunteer-checkin.signup'), [
                 // Submitted name should be IGNORED — record stays as-is.
                 'first_name' => 'Different',
                 'last_name'  => 'Name',
                 'phone'      => '5552222',
                 'email'      => 'different@test.local',
             ])
             ->assertOk()
             ->assertJson([
                 'ok'           => true,
                 'is_existing'  => true,
                 'full_name'    => 'Existing Bob',
                 'id'           => $existing->id,
             ]);

        $this->assertSame(1, Volunteer::count(), 'No new volunteer row created');
        $this->assertSame(
            'Existing',
            $existing->fresh()->first_name,
            'Submitted name must NOT overwrite existing record',
        );
        $this->assertSame(
            'bob@test.local',
            $existing->fresh()->email,
            'Submitted email must NOT overwrite existing record',
        );
        $this->assertDatabaseHas('volunteer_check_ins', [
            'volunteer_id' => $existing->id,
            'event_id'     => $this->event->id,
        ]);
    }

    public function test_phone_match_when_already_checked_in_is_idempotent(): void
    {
        $existing = Volunteer::create([
            'first_name' => 'Already',
            'last_name'  => 'In',
            'phone'      => '5553333',
        ]);
        VolunteerCheckIn::create([
            'event_id'       => $this->event->id,
            'volunteer_id'   => $existing->id,
            'role'           => 'Other',
            'source'         => 'walk_in',
            'is_first_timer' => false,
            'checked_in_at'  => now()->subMinutes(10),
        ]);

        $this->postJson(route('volunteer-checkin.signup'), [
                 'first_name' => 'Already',
                 'last_name'  => 'In',
                 'phone'      => '5553333',
             ])
             ->assertOk()
             ->assertJson(['is_existing' => true]);

        $this->assertSame(
            1,
            VolunteerCheckIn::where('volunteer_id', $existing->id)
                ->where('event_id', $this->event->id)->count(),
            'Re-signup while already checked in must not insert a second row',
        );
    }

    public function test_email_collision_with_different_phone_returns_422(): void
    {
        Volunteer::create([
            'first_name' => 'Original',
            'last_name'  => 'Owner',
            'phone'      => '5554444',
            'email'      => 'owned@test.local',
        ]);

        $this->postJson(route('volunteer-checkin.signup'), [
                 'first_name' => 'Different',
                 'last_name'  => 'Person',
                 'phone'      => '5559999',          // new phone
                 'email'      => 'owned@test.local', // taken
             ])
             ->assertStatus(422)
             ->assertJson([
                 'ok' => false,
             ]);

        $this->assertSame(1, Volunteer::count(), 'No row inserted on email collision');
    }

    public function test_phone_match_ignores_email_collision(): void
    {
        // Owner of the email below.
        Volunteer::create([
            'first_name' => 'Email',
            'last_name'  => 'Owner',
            'phone'      => '5556666',
            'email'      => 'shared@test.local',
        ]);
        // Will be matched on phone; submitted email collides but is ignored.
        $matched = Volunteer::create([
            'first_name' => 'Phone',
            'last_name'  => 'Owner',
            'phone'      => '5557777',
            'email'      => 'matched@test.local',
        ]);

        $this->postJson(route('volunteer-checkin.signup'), [
                 'first_name' => 'Whoever',
                 'last_name'  => 'Submits',
                 'phone'      => '5557777',           // matches $matched
                 'email'      => 'shared@test.local', // owned by Email Owner
             ])
             ->assertOk()
             ->assertJson(['is_existing' => true, 'id' => $matched->id]);

        $this->assertSame('matched@test.local', $matched->fresh()->email,
            'Existing record email must not change');
    }
}
