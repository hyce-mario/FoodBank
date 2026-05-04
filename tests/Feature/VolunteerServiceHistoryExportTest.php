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
 * Phase 5.9 — pins the volunteer service-history export contract:
 *   GET /volunteers/{volunteer}/service-history/print
 *   GET /volunteers/{volunteer}/service-history/export.csv
 *
 * Both require `volunteers.view` (read access) and authorize through
 * VolunteerPolicy::view. CSV ships UTF-8 BOM for Excel compatibility
 * and one row per check-in session ordered by checked_in_at descending.
 */
class VolunteerServiceHistoryExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $stranger;     // logged in but NO volunteers.view
    private Volunteer $vol;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $admin->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $admin->id,
            'email_verified_at' => now(),
        ]);

        $stranger = Role::create(['name' => 'STRANGER', 'display_name' => 'Stranger', 'description' => '']);
        // No volunteer perms granted.
        $this->stranger = User::create([
            'name'              => 'Stranger',
            'email'             => 'stranger@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $stranger->id,
            'email_verified_at' => now(),
        ]);

        $this->vol = Volunteer::create([
            'first_name' => 'Service',
            'last_name'  => 'Tester',
            'phone'      => '5550199',
            'email'      => 'svc@test.local',
            'role'       => 'Driver',
        ]);

        $this->event = Event::create([
            'name'   => 'June Distribution',
            'date'   => now()->subWeek()->toDateString(),
            'status' => 'past',
            'lanes'  => 1,
        ]);

        // Two completed sessions on the same event (multi-session, post-5.6.b).
        VolunteerCheckIn::create([
            'event_id'       => $this->event->id,
            'volunteer_id'   => $this->vol->id,
            'role'           => 'Driver',
            'source'         => 'pre_assigned',
            'is_first_timer' => true,
            'checked_in_at'  => now()->subWeek()->setTime(8, 0),
            'checked_out_at' => now()->subWeek()->setTime(11, 0),
            'hours_served'   => 3.0,
        ]);
        VolunteerCheckIn::create([
            'event_id'       => $this->event->id,
            'volunteer_id'   => $this->vol->id,
            'role'           => 'Driver',
            'source'         => 'pre_assigned',
            'is_first_timer' => false,
            'checked_in_at'  => now()->subWeek()->setTime(13, 0),
            'checked_out_at' => now()->subWeek()->setTime(15, 30),
            'hours_served'   => 2.5,
        ]);
    }

    // ─── Print ──────────────────────────────────────────────────────────────

    public function test_unauthenticated_print_redirects_to_login(): void
    {
        $this->get(route('volunteers.service-history.print', $this->vol))
             ->assertRedirect('/login');
    }

    public function test_authed_without_view_permission_is_forbidden_on_print(): void
    {
        $this->actingAs($this->stranger)
             ->get(route('volunteers.service-history.print', $this->vol))
             ->assertForbidden();
    }

    public function test_admin_renders_print_with_volunteer_and_session_rows(): void
    {
        $this->actingAs($this->admin)
             ->get(route('volunteers.service-history.print', $this->vol))
             ->assertOk()
             ->assertSee('Service Tester', false)
             ->assertSee('SERVICE HISTORY', false)
             ->assertSee('June Distribution', false)
             // Both sessions render — different check-in times.
             ->assertSee('8:00 AM', false)
             ->assertSee('1:00 PM', false)
             // Stat strip shows total hours
             ->assertSee('5.5h', false);
    }

    // ─── CSV ────────────────────────────────────────────────────────────────

    public function test_unauthenticated_csv_redirects_to_login(): void
    {
        $this->get(route('volunteers.service-history.csv', $this->vol))
             ->assertRedirect('/login');
    }

    public function test_authed_without_view_permission_is_forbidden_on_csv(): void
    {
        $this->actingAs($this->stranger)
             ->get(route('volunteers.service-history.csv', $this->vol))
             ->assertForbidden();
    }

    public function test_csv_content_type_filename_and_bom(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get(route('volunteers.service-history.csv', $this->vol))
                         ->assertOk()
                         ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('service-history-service-tester-', $disposition);

        $body = $response->streamedContent();
        // UTF-8 BOM is the first three bytes.
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));
    }

    public function test_csv_has_header_row_and_one_row_per_session(): void
    {
        $body = $this->actingAs($this->admin)
                     ->get(route('volunteers.service-history.csv', $this->vol))
                     ->streamedContent();

        // Strip BOM.
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

        $rows = array_filter(explode("\n", trim($body)));
        // 1 header row + 2 session rows.
        $this->assertCount(3, $rows);

        // PHP's fputcsv quotes fields with spaces; check each header label
        // independently rather than the full literal row.
        foreach (['Event', 'Event Date', 'Role', 'Source', 'Check-In Time', 'Check-Out Time', 'Hours Served', 'First Timer'] as $label) {
            $this->assertStringContainsString($label, $rows[0]);
        }
        // CSV is ordered newest-first (matches the Show page).
        // Row 1 = the 1 PM (2.5h, not first-timer), row 2 = the 8 AM (3.0h, first-timer).
        $this->assertStringContainsString('June Distribution', $rows[1]);
        $this->assertStringContainsString('2.50', $rows[1]);
        $this->assertStringContainsString('No', $rows[1]);
        $this->assertStringContainsString('3.00', $rows[2]);
        $this->assertStringContainsString('Yes', $rows[2]);
    }

    public function test_volunteer_with_no_history_renders_empty_csv_with_only_header(): void
    {
        $bare = Volunteer::create(['first_name' => 'Bare', 'last_name' => 'Volunteer', 'phone' => '5550000']);

        $body = $this->actingAs($this->admin)
                     ->get(route('volunteers.service-history.csv', $bare))
                     ->streamedContent();

        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $rows = array_filter(explode("\n", trim($body)));
        $this->assertCount(1, $rows, 'Header row only when no sessions');
    }
}
