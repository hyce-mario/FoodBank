<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Models\VolunteerGroup;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5.12 — Volunteer roster list-level Print + CSV exports.
 *
 * Pins:
 *   - auth gate: unauth → /login, viewer-without-perms → 403, admin → 200
 *   - print: branded HTML carries org name + table rows
 *   - csv: UTF-8 BOM, header row, one row per matched volunteer
 *   - active filters (search / role / group) apply to the export so a
 *     filtered view exports the same subset
 *
 * (Roster PDF was shipped earlier and removed by user request — the per-
 *  record service-history exports stay separate and are not tested here.)
 */
class VolunteerListExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);

        $viewerRole = Role::create(['name' => 'VIEWER', 'display_name' => 'Viewer', 'description' => '']);
        // No permissions — VolunteerPolicy::viewAny will deny.

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('p'),
            'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);
        $this->viewer = User::create([
            'name' => 'Viewer', 'email' => 'viewer@test.local', 'password' => bcrypt('p'),
            'role_id' => $viewerRole->id, 'email_verified_at' => now(),
        ]);
    }

    // ─── Auth gate ────────────────────────────────────────────────────────────

    public function test_unauth_redirects_to_login_for_both_endpoints(): void
    {
        foreach (['print', 'csv'] as $kind) {
            $this->get(route("volunteers.export.{$kind}"))->assertRedirect('/login');
        }
    }

    public function test_viewer_without_perms_gets_403(): void
    {
        foreach (['print', 'csv'] as $kind) {
            $this->actingAs($this->viewer)
                 ->get(route("volunteers.export.{$kind}"))
                 ->assertForbidden();
        }
    }

    // ─── Print ────────────────────────────────────────────────────────────────

    public function test_print_renders_branded_html_with_volunteers(): void
    {
        Volunteer::create(['first_name' => 'Mary',  'last_name' => 'Johnson', 'phone' => '5550001']);
        Volunteer::create(['first_name' => 'David', 'last_name' => 'Chen',    'phone' => '5550002']);

        $response = $this->actingAs($this->admin)
                         ->get(route('volunteers.export.print'))
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Volunteer Roster Report', $html);
        $this->assertStringContainsString('Mary Johnson', $html);
        $this->assertStringContainsString('David Chen',   $html);
        // Stat strip
        $this->assertStringContainsString('Total Volunteers', $html);
        // Auto-print is fired by the embedded script on load
        $this->assertStringContainsString('window.print()', $html);
    }

    // ─── CSV ──────────────────────────────────────────────────────────────────

    public function test_csv_streams_with_utf8_bom_and_header_plus_data_rows(): void
    {
        Volunteer::create(['first_name' => 'Mary',  'last_name' => 'Johnson', 'phone' => '5550001']);
        Volunteer::create(['first_name' => 'David', 'last_name' => 'Chen',    'phone' => '5550002']);

        $response = $this->actingAs($this->admin)
                         ->get(route('volunteers.export.csv'))
                         ->assertOk();

        $body = $response->streamedContent();

        // UTF-8 BOM in the first 3 bytes — Excel respects this for proper
        // encoding when the file is opened directly.
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        // Strip BOM, parse with PHP's csv reader, assert structure
        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        $this->assertSame(
            ['First Name', 'Last Name', 'Phone', 'Email', 'Role', 'Groups', 'Events Served', 'Created'],
            $rows[0],
        );
        $names = collect(array_slice($rows, 1))
            ->map(fn ($r) => $r[0] . ' ' . $r[1])
            ->all();
        $this->assertContains('Mary Johnson', $names);
        $this->assertContains('David Chen', $names);
    }

    public function test_csv_filename_includes_volunteers_prefix_and_date(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get(route('volunteers.export.csv'))
                         ->assertOk();

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('volunteers-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    public function test_csv_includes_group_names_joined_with_semicolons(): void
    {
        $vol = Volunteer::create(['first_name' => 'Mary', 'last_name' => 'Johnson', 'phone' => '5550001']);
        $packing = VolunteerGroup::create(['name' => 'Packing']);
        $intake  = VolunteerGroup::create(['name' => 'Intake']);
        $vol->groups()->attach([$packing->id, $intake->id]);

        $body = $this->actingAs($this->admin)
                     ->get(route('volunteers.export.csv'))
                     ->assertOk()
                     ->streamedContent();

        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        // The "Groups" column is index 5
        $maryRow = collect($rows)->first(fn ($r) => ($r[0] ?? '') === 'Mary');
        $this->assertNotNull($maryRow);
        // Both groups present, joined by semicolon (order can vary)
        $this->assertStringContainsString('Packing', $maryRow[5]);
        $this->assertStringContainsString('Intake',  $maryRow[5]);
        $this->assertStringContainsString(';', $maryRow[5]);
    }

    // ─── Filters apply to exports ─────────────────────────────────────────────

    public function test_search_filter_applies_to_csv_export(): void
    {
        Volunteer::create(['first_name' => 'Mary',  'last_name' => 'Johnson', 'phone' => '5550001']);
        Volunteer::create(['first_name' => 'David', 'last_name' => 'Chen',    'phone' => '5550002']);

        $body = $this->actingAs($this->admin)
                     ->get(route('volunteers.export.csv', ['search' => 'Mary']))
                     ->assertOk()
                     ->streamedContent();

        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        $names = collect(array_slice($rows, 1))->map(fn ($r) => $r[0] . ' ' . $r[1])->all();
        $this->assertContains('Mary Johnson', $names);
        $this->assertNotContains('David Chen', $names);
    }

    public function test_role_filter_applies_to_print_export(): void
    {
        Volunteer::create(['first_name' => 'Mary',  'last_name' => 'J', 'phone' => '5550001', 'role' => 'Driver']);
        Volunteer::create(['first_name' => 'David', 'last_name' => 'C', 'phone' => '5550002', 'role' => 'Loader']);

        $html = $this->actingAs($this->admin)
                     ->get(route('volunteers.export.print', ['role' => 'Driver']))
                     ->assertOk()
                     ->getContent();

        $this->assertStringContainsString('Mary J', $html);
        $this->assertStringNotContainsString('David C', $html);
        // Filter strip surfaces what was applied
        $this->assertStringContainsString('Role: Driver', $html);
    }

    public function test_group_filter_applies_to_csv_export(): void
    {
        $packing = VolunteerGroup::create(['name' => 'Packing']);
        $intake  = VolunteerGroup::create(['name' => 'Intake']);

        $mary  = Volunteer::create(['first_name' => 'Mary',  'last_name' => 'J', 'phone' => '5550001']);
        $david = Volunteer::create(['first_name' => 'David', 'last_name' => 'C', 'phone' => '5550002']);
        $mary->groups()->attach($packing->id);
        $david->groups()->attach($intake->id);

        $body = $this->actingAs($this->admin)
                     ->get(route('volunteers.export.csv', ['group' => $packing->id]))
                     ->assertOk()
                     ->streamedContent();

        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        $names = collect(array_slice($rows, 1))->map(fn ($r) => $r[0] . ' ' . $r[1])->all();
        $this->assertContains('Mary J', $names);
        $this->assertNotContains('David C', $names);
    }

    public function test_events_served_count_appears_in_csv(): void
    {
        $event = Event::create([
            'name' => 'E1', 'date' => now()->toDateString(), 'status' => 'past', 'lanes' => 1,
        ]);
        $vol = Volunteer::create(['first_name' => 'Mary', 'last_name' => 'J', 'phone' => '5550001']);
        VolunteerCheckIn::create([
            'event_id' => $event->id, 'volunteer_id' => $vol->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => true,
            'checked_in_at' => now()->subHours(2), 'checked_out_at' => now(), 'hours_served' => 2.0,
        ]);

        $body = $this->actingAs($this->admin)
                     ->get(route('volunteers.export.csv'))
                     ->assertOk()
                     ->streamedContent();

        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        // Events Served column is index 6
        $maryRow = collect($rows)->first(fn ($r) => ($r[0] ?? '') === 'Mary');
        $this->assertSame('1', $maryRow[6]);
    }
}
