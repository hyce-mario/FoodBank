<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\Event;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\EventCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D — pin the per-household event-report exports (Print HTML, PDF, XLSX).
 *
 * Mirrors the Phase C contract (MIME types, full row inclusion) but scoped
 * to a single household's history and including the rep-pickup label semantic
 * from Phase B. Status mapping (`exited` → "Served", anything else → "In
 * Progress") is also pinned here.
 */
class HouseholdEventReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Administrator', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $adminRole->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeHousehold(string $first, int $size = 2): Household
    {
        return Household::create([
            'household_number' => substr(md5($first . microtime(true)), 0, 6),
            'first_name'       => $first,
            'last_name'        => 'Family',
            'household_size'   => $size,
            'children_count'   => 0,
            'adults_count'     => $size,
            'seniors_count'    => 0,
            'qr_token'         => substr(md5($first . random_int(0, 99999)), 0, 32),
        ]);
    }

    private function makeEvent(string $name, string $date, ?int $rulesetId = null): Event
    {
        return Event::create([
            'name'       => $name,
            'date'       => $date,
            'lanes'      => 1,
            'status'     => 'current',
            'ruleset_id' => $rulesetId,
        ]);
    }

    private function makeRuleset(int $bags = 2): AllocationRuleset
    {
        return AllocationRuleset::create([
            'name'               => 'Phase D Ruleset',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 20,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => $bags]],
        ]);
    }

    private function exit(Visit $visit): void
    {
        $visit->update(['visit_status' => 'exited', 'exited_at' => now()]);
    }

    public function test_print_export_returns_html_with_all_rows(): void
    {
        $ruleset = $this->makeRuleset();
        $hh      = $this->makeHousehold('Alice');

        for ($i = 1; $i <= 12; $i++) {
            $event = $this->makeEvent("Event {$i}", "2026-04-{$i}", $ruleset->id);
            $v     = app(EventCheckInService::class)->checkIn($event, $hh, 1);
            $this->exit($v);
        }

        $response = $this->actingAs($this->admin)->get(route('households.event-report.print', $hh));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');

        // Spot check first and last so we know all 12 rendered (no pagination).
        $response->assertSee('Event 1');
        $response->assertSee('Event 12');
        $response->assertSee($hh->full_name);
        $response->assertSee('12 events');
    }

    public function test_pdf_export_returns_pdf_mime(): void
    {
        $ruleset = $this->makeRuleset();
        $hh      = $this->makeHousehold('Bob');
        $event   = $this->makeEvent('Solo Event', '2026-04-10', $ruleset->id);
        $this->exit(app(EventCheckInService::class)->checkIn($event, $hh, 1));

        $response = $this->actingAs($this->admin)->get(route('households.event-report.pdf', $hh));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_xlsx_export_returns_xlsx_mime(): void
    {
        $ruleset = $this->makeRuleset();
        $hh      = $this->makeHousehold('Carol');
        $event   = $this->makeEvent('Solo Event', '2026-04-12', $ruleset->id);
        $this->exit(app(EventCheckInService::class)->checkIn($event, $hh, 1));

        $response = $this->actingAs($this->admin)->get(route('households.event-report.xlsx', $hh));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }

    public function test_export_includes_in_progress_visits_not_just_exited(): void
    {
        // Phase B confirmed: stat cards count only exited; the table (and
        // therefore exports) shows everything — confirm here.
        $ruleset = $this->makeRuleset();
        $hh      = $this->makeHousehold('Diana');

        $eventDone     = $this->makeEvent('Completed Event',   '2026-04-01', $ruleset->id);
        $eventOngoing  = $this->makeEvent('Ongoing Event',     '2026-04-15', $ruleset->id);

        $this->exit(app(EventCheckInService::class)->checkIn($eventDone, $hh, 1));
        app(EventCheckInService::class)->checkIn($eventOngoing, $hh, 1); // stays checked_in

        $response = $this->actingAs($this->admin)->get(route('households.event-report.print', $hh));
        $response->assertOk();
        $response->assertSee('Completed Event');
        $response->assertSee('Served');
        $response->assertSee('Ongoing Event');
        $response->assertSee('In Progress');
    }

    public function test_export_includes_picked_up_by_for_rep_pickups(): void
    {
        $ruleset = $this->makeRuleset();
        $linda   = $this->makeHousehold('Linda');
        $bob     = $this->makeHousehold('Bob');
        $event   = $this->makeEvent('Rep Pickup Event', '2026-04-20', $ruleset->id);

        $this->exit(app(EventCheckInService::class)->checkIn($event, $linda, 1, [$bob->id]));

        // Bob's report must surface Linda as the driver.
        $response = $this->actingAs($this->admin)->get(route('households.event-report.print', $bob));
        $response->assertOk();
        $response->assertSee('Picked up by');
        $response->assertSee('Linda Family');
    }

    public function test_xlsx_contents_match_history_rows(): void
    {
        $ruleset = $this->makeRuleset(bags: 3);
        $hh      = $this->makeHousehold('Eve', size: 4);
        $event   = $this->makeEvent('Audit Event', '2026-04-22', $ruleset->id);
        $this->exit(app(EventCheckInService::class)->checkIn($event, $hh, 1));

        $response = $this->actingAs($this->admin)->get(route('households.event-report.xlsx', $hh));

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_evt_');
        file_put_contents($tmp, $response->streamedContent());
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        $cells       = $spreadsheet->getActiveSheet()->toArray();
        unlink($tmp);

        $flat = collect($cells)->flatten()->filter()->implode(' | ');
        $this->assertStringContainsString('Audit Event', $flat);
        $this->assertStringContainsString('Eve Family', $flat);
        $this->assertStringContainsString('Served', $flat);
        // Ruleset gives 3 bags for any size — confirm it's in the workbook.
        $this->assertStringContainsString(' | 3 | ', ' | ' . $flat . ' | ');
    }

    public function test_unauthorized_user_cannot_export_event_report(): void
    {
        $hh         = $this->makeHousehold('Frank');
        $noPermRole = Role::create(['name' => 'NONE', 'display_name' => 'Nothing', 'description' => '']);
        $user       = User::create([
            'name'              => 'Nobody',
            'email'             => 'nobody2@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $noPermRole->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('households.event-report.pdf', $hh));
        $response->assertForbidden();
    }
}
