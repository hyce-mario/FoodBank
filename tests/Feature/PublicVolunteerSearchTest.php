<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Models\VolunteerGroup;
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

    /**
     * Phase 5.11 — search response surfaces groups so the redesigned
     * Confirm card can render team badges. id+name only — no membership
     * metadata leaked.
     */
    public function test_match_includes_volunteer_groups_array(): void
    {
        $vol = Volunteer::create([
            'first_name' => 'Grouped',
            'last_name'  => 'Vol',
            'phone'      => '5553333',
        ]);

        $packing = VolunteerGroup::create(['name' => 'Packing']);
        $intake  = VolunteerGroup::create(['name' => 'Intake']);
        $vol->groups()->attach([$packing->id, $intake->id]);

        $row = $this->getJson(route('volunteer-checkin.search') . '?q=5553333')
                    ->json('results.0');

        $this->assertIsArray($row['groups']);
        $this->assertCount(2, $row['groups']);
        $names = collect($row['groups'])->pluck('name')->all();
        $this->assertContains('Packing', $names);
        $this->assertContains('Intake', $names);

        // id + name only — pivot metadata (joined_at, timestamps) must
        // not leak through the public endpoint.
        $this->assertSame(['id', 'name'], array_keys($row['groups'][0]));
    }

    /**
     * Phase 5.11 — ISO timestamp powers the live elapsed clock on the
     * Confirm card for Check-Out and View Status flows. Should be
     * present whenever there's an active check-in row, null otherwise.
     */
    public function test_match_includes_iso_check_in_timestamp(): void
    {
        $vol = Volunteer::create([
            'first_name' => 'Elapsed',
            'last_name'  => 'Clock',
            'phone'      => '5554444',
        ]);
        VolunteerCheckIn::create([
            'event_id'       => $this->event->id,
            'volunteer_id'   => $vol->id,
            'role'           => 'Other',
            'source'         => 'walk_in',
            'is_first_timer' => false,
            'checked_in_at'  => now()->subMinutes(30),
        ]);

        $row = $this->getJson(route('volunteer-checkin.search') . '?q=5554444')
                    ->json('results.0');

        $this->assertNotNull($row['checked_in_at_iso']);
        // ISO 8601 format with offset, e.g. 2026-05-04T13:30:00+00:00
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/',
            $row['checked_in_at_iso']
        );
    }

    public function test_iso_timestamp_is_null_when_not_checked_in(): void
    {
        Volunteer::create([
            'first_name' => 'Never',
            'last_name'  => 'In',
            'phone'      => '5555555',
        ]);

        $row = $this->getJson(route('volunteer-checkin.search') . '?q=5555555')
                    ->json('results.0');

        $this->assertNull($row['checked_in_at_iso']);
        $this->assertSame([], $row['groups']);
    }

    /**
     * Phase 5.11 — fuzzy phone match. Stripping non-digits from both
     * sides means typed punctuation no longer prevents a hit on a
     * stored bare-digit phone.
     */
    public function test_search_matches_when_typed_phone_has_punctuation(): void
    {
        $vol = Volunteer::create([
            'first_name' => 'Punctuated',
            'last_name'  => 'Search',
            'phone'      => '5556661',
        ]);

        foreach (['(555) 6661', '555-6661', '+555 6661', '555.6661', '555/6661'] as $typed) {
            $row = $this->getJson(route('volunteer-checkin.search') . '?q=' . urlencode($typed))
                        ->json('results.0');
            $this->assertSame($vol->id, $row['id'], "expected match for typed value: $typed");
        }
    }

    /**
     * Reverse direction — stored phone has formatting, typed phone is
     * bare digits. Same digits-only comparison applies on both sides.
     */
    public function test_search_matches_when_stored_phone_has_punctuation(): void
    {
        $vol = Volunteer::create([
            'first_name' => 'Stored',
            'last_name'  => 'Formatted',
            'phone'      => '(555) 777-2222',
        ]);

        $row = $this->getJson(route('volunteer-checkin.search') . '?q=5557772222')
                    ->json('results.0');

        $this->assertSame($vol->id, $row['id']);
    }

    /**
     * Empty-after-strip queries (e.g. "()" or "+-") must NOT match
     * volunteers with NULL phone — that would surface every row.
     */
    public function test_search_does_not_match_when_typed_value_strips_to_empty(): void
    {
        Volunteer::create([
            'first_name' => 'Null',
            'last_name'  => 'Phone',
            'phone'      => null,
        ]);

        $this->getJson(route('volunteer-checkin.search') . '?q=()-+')
             ->assertOk()->assertJson(['results' => []]);
    }
}
