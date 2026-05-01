<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C.3 — Branded printable sheet + streamed CSV download.
 *
 * Pins both export surfaces:
 * - GET /events/{event}/attendees/print  — standalone HTML, branded header,
 *   stat strip, table of pre-regs, auto-fires window.print().
 * - GET /events/{event}/attendees/export.csv — text/csv body, UTF-8 BOM,
 *   header row, one row per pre-reg with the agreed column set.
 *
 * Auth-gated; non-admin viewers fall through to the EventPolicy::view check.
 */
class EventAttendeesExportTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function makeEvent(): Event
    {
        return Event::create([
            'name'   => 'Spring Drive 2026',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);
    }

    private function addPreReg(Event $event, array $overrides = []): EventPreRegistration
    {
        static $counter = 0;
        $counter++;
        return EventPreRegistration::create(array_merge([
            'event_id'         => $event->id,
            'attendee_number'  => str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'first_name'       => "First{$counter}",
            'last_name'        => "Last{$counter}",
            'email'            => "person{$counter}@test.local",
            'household_size'   => 3,
            'children_count'   => 1,
            'adults_count'     => 2,
            'seniors_count'    => 0,
            'city'             => 'Springfield',
            'state'            => 'IL',
            'zipcode'          => '62701',
            'match_status'     => 'unmatched',
        ], $overrides));
    }

    // ─── Print sheet ──────────────────────────────────────────────────────────

    public function test_print_sheet_requires_authentication(): void
    {
        $event = $this->makeEvent();
        $this->get(route('events.attendees.print', $event))
             ->assertRedirect(route('login'));
    }

    public function test_print_sheet_renders_branded_standalone_doc(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event, ['first_name' => 'Linda', 'last_name' => 'Smith']);

        $response = $this->actingAs($this->admin)
                         ->get(route('events.attendees.print', $event))
                         ->assertOk();

        $html = $response->getContent();

        // Standalone doc — no app sidebar / topbar markup.
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('ATTENDEES', $html);
        $this->assertStringContainsString('Spring Drive 2026', $html);
        $this->assertStringContainsString('Linda Smith', $html);
        // Auto-print on load.
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_print_sheet_renders_empty_state_with_zero_attendees(): void
    {
        $event = $this->makeEvent();

        $this->actingAs($this->admin)
             ->get(route('events.attendees.print', $event))
             ->assertOk()
             ->assertSee('No attendees pre-registered for this event.');
    }

    public function test_print_sheet_includes_stat_strip(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event, ['children_count' => 2, 'adults_count' => 2, 'seniors_count' => 1, 'household_size' => 5]);
        $this->addPreReg($event, ['children_count' => 1, 'adults_count' => 1, 'seniors_count' => 0, 'household_size' => 2]);

        $response = $this->actingAs($this->admin)
                         ->get(route('events.attendees.print', $event))
                         ->assertOk();

        $response->assertSeeInOrder(['Total Attendees', 'Children', 'Adults', 'Seniors']);
    }

    // ─── CSV export ───────────────────────────────────────────────────────────

    public function test_csv_requires_authentication(): void
    {
        $event = $this->makeEvent();
        $this->get(route('events.attendees.csv', $event))
             ->assertRedirect(route('login'));
    }

    public function test_csv_has_correct_content_type_and_filename(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event);

        $response = $this->actingAs($this->admin)
                         ->get(route('events.attendees.csv', $event))
                         ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attendees-spring-drive-2026', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_csv_starts_with_utf8_bom(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event);

        $body = $this->actingAs($this->admin)
                     ->get(route('events.attendees.csv', $event))
                     ->streamedContent();

        // "\xEF\xBB\xBF" — Excel reads this as a hint to treat the file as UTF-8.
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));
    }

    public function test_csv_includes_header_row_and_data_rows(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event, [
            'first_name'     => 'Linda',
            'last_name'      => 'Smith',
            'email'          => 'linda@test.local',
            'household_size' => 4,
            'children_count' => 2,
            'adults_count'   => 2,
            'seniors_count'  => 0,
            'city'           => 'Chicago',
            'state'          => 'IL',
            'zipcode'        => '60601',
            'match_status'   => 'matched',
        ]);

        $body = $this->actingAs($this->admin)
                     ->get(route('events.attendees.csv', $event))
                     ->streamedContent();

        // Header row — fputcsv quotes any field containing whitespace.
        $this->assertStringContainsString('"Attendee #","First Name","Last Name",Email', $body);
        $this->assertStringContainsString('"Match Status","Registered At"', $body);
        // Data row.
        $this->assertStringContainsString('Linda', $body);
        $this->assertStringContainsString('Smith', $body);
        $this->assertStringContainsString('linda@test.local', $body);
        $this->assertStringContainsString('Chicago', $body);
        $this->assertStringContainsString('matched', $body);
    }

    public function test_csv_streams_only_pre_regs_for_the_target_event(): void
    {
        $eventA = $this->makeEvent();
        $eventB = Event::create([
            'name' => 'Other', 'date' => now()->toDateString(), 'status' => 'current', 'lanes' => 1,
        ]);

        $this->addPreReg($eventA, ['first_name' => 'OnlyA']);
        $this->addPreReg($eventB, ['first_name' => 'OnlyB']);

        $body = $this->actingAs($this->admin)
                     ->get(route('events.attendees.csv', $eventA))
                     ->streamedContent();

        $this->assertStringContainsString('OnlyA', $body);
        $this->assertStringNotContainsString('OnlyB', $body);
    }
}
