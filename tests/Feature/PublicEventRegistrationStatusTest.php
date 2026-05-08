<?php

namespace Tests\Feature;

use App\Http\Middleware\BotDefense;
use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the EventPreRegistration::match_status semantics that the admin
 * UI in events/show.blade.php depends on.
 *
 * Three legitimate states a public registration can land in:
 *
 *   • 'new'             — brand-new household auto-created during
 *                          registration (no pre-existing match). Linked
 *                          via household_id from the start. Admin sees
 *                          a green "New" pill + household number with
 *                          NO action button.
 *
 *   • 'potential_match' — a name- or email-match against an existing
 *                          household was detected. Admin sees the
 *                          orange "Potential Match" pill + Confirm/Not them
 *                          buttons.
 *
 *   • 'matched'         — reserved for "linked to a PRE-EXISTING household
 *                          AFTER admin review" (matchAttendee or
 *                          registerAttendee actions). Public registration
 *                          MUST NOT produce this — that was the original
 *                          bug ("new household shows as matched").
 */
class PublicEventRegistrationStatusTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name'   => 'Status Semantics Test',
            'date'   => now()->addDays(7)->toDateString(),
            'status' => 'upcoming',
            'lanes'  => 1,
        ]);
    }

    public function test_brand_new_household_registration_is_new_not_matched(): void
    {
        $this->post(route('public.submit', $this->event), [
            'first_name'     => 'Brand',
            'last_name'      => 'New',
            'email'          => 'brand.new@example.test',
            'children_count' => 1,
            'adults_count'   => 2,
            'seniors_count'  => 0,
            '_form_ts'       => BotDefense::signedTimestamp(time() - 5),
        ])->assertRedirect();

        $reg = EventPreRegistration::firstOrFail();

        $this->assertSame('new', $reg->match_status,
            'A brand-new household must NOT be marked as "matched" — that semantic '.
            'is reserved for admin-confirmed links to pre-existing households.');
        $this->assertNotNull($reg->household_id, 'Auto-created household must be linked');
        $this->assertNull($reg->potential_household_id, 'No potential match exists for a brand-new household');

        // Sanity — the household row was actually created.
        $this->assertSame(1, Household::count());
    }

    public function test_registration_matching_existing_household_by_name_is_potential_match(): void
    {
        $existing = Household::create([
            'household_number' => 'H001',
            'first_name'       => 'Returning',
            'last_name'        => 'Visitor',
            'email'            => 'old@example.test',
            'household_size'   => 3,
            'qr_token'         => str_repeat('a', 32),
        ]);

        $this->post(route('public.submit', $this->event), [
            // Same name, different email — matched by full-name lower-case rule.
            'first_name'     => 'Returning',
            'last_name'      => 'Visitor',
            'email'          => 'new-address@example.test',
            'children_count' => 0,
            'adults_count'   => 1,
            'seniors_count'  => 0,
            '_form_ts'       => BotDefense::signedTimestamp(time() - 5),
        ])->assertRedirect();

        $reg = EventPreRegistration::firstOrFail();

        $this->assertSame('potential_match', $reg->match_status);
        $this->assertSame($existing->id, $reg->potential_household_id);
        $this->assertNull($reg->household_id, 'Potential match must NOT auto-link — admin needs to confirm');

        // No second household created.
        $this->assertSame(1, Household::count());
    }

    public function test_registration_matching_existing_household_by_email_is_potential_match(): void
    {
        $existing = Household::create([
            'household_number' => 'H002',
            'first_name'       => 'Married',
            'last_name'        => 'NameChanged',
            'email'            => 'shared@example.test',
            'household_size'   => 2,
            'qr_token'         => str_repeat('b', 32),
        ]);

        $this->post(route('public.submit', $this->event), [
            // Different name, same email — matched by email lower-case rule.
            'first_name'     => 'Different',
            'last_name'      => 'Surname',
            'email'          => 'shared@example.test',
            'children_count' => 0,
            'adults_count'   => 1,
            'seniors_count'  => 0,
            '_form_ts'       => BotDefense::signedTimestamp(time() - 5),
        ])->assertRedirect();

        $reg = EventPreRegistration::firstOrFail();

        $this->assertSame('potential_match', $reg->match_status);
        $this->assertSame($existing->id, $reg->potential_household_id);
        $this->assertNull($reg->household_id);
    }

    public function test_dismissing_a_potential_match_marks_attendee_as_new_without_db_error(): void
    {
        // Reproduces the bug the user hit: clicking "Not them" on a
        // potential_match attendee threw SQLSTATE[23000] "Column
        // 'match_status' cannot be null" on production MySQL because the
        // controller wrote NULL into a NOT-NULL column. SQLite (test
        // suite) silently coerced NULL to the default('new') so the bug
        // never surfaced in CI. Asserting the post-state value pins the
        // fix and the not-error makes the regression test concrete.
        $existing = Household::create([
            'household_number' => 'H010',
            'first_name'       => 'Pre',
            'last_name'        => 'Existing',
            'email'            => 'pre@example.test',
            'household_size'   => 2,
            'qr_token'         => str_repeat('c', 32),
        ]);

        $reg = EventPreRegistration::create([
            'event_id'               => $this->event->id,
            'attendee_number'        => '00001',
            'first_name'             => 'Pre',
            'last_name'              => 'Existing',
            'email'                  => 'someone-else@example.test',
            'children_count'         => 0,
            'adults_count'           => 1,
            'seniors_count'          => 0,
            'household_size'         => 1,
            'potential_household_id' => $existing->id,
            'match_status'           => 'potential_match',
        ]);

        // Admin user with events.edit (the route now requires it).
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('events.attendees.dismiss', [$this->event, $reg]))
            ->assertRedirect();

        $reg->refresh();
        $this->assertSame('new', $reg->match_status, 'Dismissed attendee must drop to "new" — not null');
        $this->assertNull($reg->potential_household_id, 'Potential link must be cleared');
        $this->assertNull($reg->household_id, 'No household auto-assigned by dismiss');
    }

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        return User::create([
            'name'              => 'Admin',
            'email'             => 'admin-status-test@example.test',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }
}
