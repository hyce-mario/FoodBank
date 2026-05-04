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
 * Phase 5.6.e — pins the public-search endpoint contract:
 *   - lookup is by EXACT phone match (was: name/phone/email fuzzy)
 *   - response strips phone + email (PII leak fix)
 *   - returns is_assigned + checked_in flags so the frontend can
 *     style the matched card
 *   - empty / no-match queries return an empty results array
 */
class PublicVolunteerSearchTest extends TestCase
{
    use RefreshDatabase;

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
            'name'   => 'Public Search Test',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);
    }

    public function test_empty_query_returns_empty_results(): void
    {
        Volunteer::create(['first_name' => 'A', 'last_name' => 'B', 'phone' => '5551234']);

        $this->getJson(route('volunteer-checkin.search') . '?q=')
             ->assertOk()
             ->assertJson(['results' => []]);
    }

    public function test_unmatched_phone_returns_empty_results(): void
    {
        Volunteer::create(['first_name' => 'A', 'last_name' => 'B', 'phone' => '5551234']);

        $this->getJson(route('volunteer-checkin.search') . '?q=9999999')
             ->assertOk()
             ->assertJson(['results' => []]);
    }

    public function test_matched_phone_returns_volunteer_without_pii(): void
    {
        $vol = Volunteer::create([
            'first_name' => 'Alice',
            'last_name'  => 'A',
            'phone'      => '5551234',
            'email'      => 'alice@test.local',
        ]);

        $this->event->assignedVolunteers()->attach($vol->id);

        $response = $this->getJson(route('volunteer-checkin.search') . '?q=5551234')
                         ->assertOk();

        $results = $response->json('results');
        $this->assertCount(1, $results);

        $row = $results[0];
        $this->assertSame($vol->id, $row['id']);
        $this->assertSame('Alice A', $row['full_name']);
        $this->assertTrue($row['is_assigned']);
        $this->assertFalse($row['checked_in']);

        // Phase 5.6.e PII strip: phone + email must NOT be in the
        // public response, even though the caller already typed the
        // phone (defends against accidental future use of the field).
        $this->assertArrayNotHasKey('phone', $row);
        $this->assertArrayNotHasKey('email', $row);
    }

    public function test_search_does_not_match_on_name_or_email(): void
    {
        // Pre-fix this would have been a fuzzy match; post-fix only
        // exact phone matches are returned.
        Volunteer::create([
            'first_name' => 'Searchable',
            'last_name'  => 'Name',
            'phone'      => '5551234',
            'email'      => 'searchable@test.local',
        ]);

        $this->getJson(route('volunteer-checkin.search') . '?q=Searchable')
             ->assertOk()->assertJson(['results' => []]);

        $this->getJson(route('volunteer-checkin.search') . '?q=searchable@test.local')
             ->assertOk()->assertJson(['results' => []]);
    }

    public function test_match_includes_check_in_status_when_already_checked_in(): void
    {
        $vol = Volunteer::create([
            'first_name' => 'Already',
            'last_name'  => 'In',
            'phone'      => '5552222',
        ]);
        VolunteerCheckIn::create([
            'event_id'       => $this->event->id,
            'volunteer_id'   => $vol->id,
            'role'           => 'Other',
            'source'         => 'walk_in',
            'is_first_timer' => false,
            'checked_in_at'  => now()->subMinutes(15),
        ]);

        $row = $this->getJson(route('volunteer-checkin.search') . '?q=5552222')
                    ->json('results.0');

        $this->assertTrue($row['checked_in']);
        $this->assertNotNull($row['checkin_time']);
        $this->assertFalse($row['checked_out']);
    }
}
