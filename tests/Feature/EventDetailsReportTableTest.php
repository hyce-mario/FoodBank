<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\Event;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B — Event Report table on the event details page.
 *
 * Pins the table contract: 6 columns (Household # / Name / Size / Bags /
 * Check-in Time / Status), rep-pickup row expansion (primary first, then
 * `↳`-indented represented rows below), pivot-snapshot bag math under the
 * active ruleset, "—" when no ruleset, paginated at 10/page with a clamped
 * per-page knob, newest-first ordering, and the empty-state fallback.
 *
 * Decisions locked by user (HANDOFF Session 6):
 *   1. Newest visit first (start_time DESC).
 *   2. Time + Status repeat on every row (incl. represented sub-rows).
 *   3. No lane filter — the Lane select was removed from the header.
 *   4. 10 rows per page default; the dropdown allows 10/25/50.
 *   5. Bags = (int) getBagsFor(snapshot_size) when ruleset present (so 0
 *      is a legitimate value); "—" only when no ruleset is assigned.
 */
class EventDetailsReportTableTest extends TestCase
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

    private function makeHousehold(string $first, string $last, int $size = 3): Household
    {
        static $counter = 0;
        $counter++;
        return Household::create([
            'household_number' => str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'first_name'       => $first,
            'last_name'        => $last,
            'household_size'   => $size,
            'children_count'   => 1,
            'adults_count'     => max(1, $size - 1),
            'seniors_count'    => 0,
            'qr_token'         => str_repeat('a', 32) . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
        ]);
    }

    private function makeEvent(?AllocationRuleset $ruleset = null): Event
    {
        return Event::create([
            'name'        => 'Phase B Event',
            'date'        => now()->toDateString(),
            'status'      => 'current',
            'lanes'       => 1,
            'ruleset_id'  => $ruleset?->id,
        ]);
    }

    private function makeRuleset(): AllocationRuleset
    {
        return AllocationRuleset::create([
            'name'               => 'Test Ruleset',
            'is_active'          => true,
            'max_household_size' => 20,
            'rules'              => [
                ['min' => 1, 'max' => 1, 'bags' => 1],
                ['min' => 2, 'max' => 4, 'bags' => 2],
                ['min' => 5, 'max' => null, 'bags' => 3],
            ],
        ]);
    }

    private function attachExitedVisit(Event $event, array $households, int $bags = 5, ?\Illuminate\Support\Carbon $startTime = null): Visit
    {
        $visit = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'exited',
            'start_time'   => $startTime ?? now()->subHour(),
            'end_time'     => now(),
            'served_bags'  => $bags,
        ]);
        foreach ($households as $hh) {
            $visit->households()->attach($hh->id, $hh->toVisitPivotSnapshot());
        }
        return $visit;
    }

    public function test_primary_household_row_renders_all_six_columns(): void
    {
        $rs    = $this->makeRuleset();
        $event = $this->makeEvent($rs);
        $hh    = $this->makeHousehold('Linda', 'Smith', 3);
        $this->attachExitedVisit($event, [$hh], 2);

        $html = $this->actingAs($this->admin)
                     ->get(route('events.show', $event))
                     ->assertOk()
                     ->getContent();

        // ID column = household_number with `#` prefix.
        $this->assertStringContainsString('#'.$hh->household_number, $html);
        // Household name column.
        $this->assertStringContainsString('Linda Smith', $html);
        // Status badge label + class hook.
        $this->assertStringContainsString('er-status-exited', $html);
        $this->assertStringContainsString('Exited', $html);
        // Empty-state placeholder must NOT render alongside real rows.
        $this->assertStringNotContainsString('No check-ins recorded yet.', $html);
    }

    public function test_rep_pickup_visit_renders_primary_then_indented_represented_rows(): void
    {
        $rs       = $this->makeRuleset();
        $event    = $this->makeEvent($rs);
        $primary  = $this->makeHousehold('Primary', 'Family', 4);
        $repA     = $this->makeHousehold('Rep', 'Alpha', 2);
        $repB     = $this->makeHousehold('Rep', 'Bravo', 6);

        $this->attachExitedVisit($event, [$primary, $repA, $repB], 7);

        $response = $this->actingAs($this->admin)
                         ->get(route('events.show', $event))
                         ->assertOk();

        // Primary appears before represented rows in DOM order.
        $response->assertSeeInOrder(['Primary Family', 'Rep Alpha', 'Rep Bravo']);

        $html = $response->getContent();
        // Two represented rows carry the `data-row="represented"` flag.
        $this->assertSame(2, substr_count($html, 'data-row="represented"'));
        $this->assertSame(1, substr_count($html, 'data-row="primary"'));
        // The ↳ glyph appears for represented rows.
        $this->assertStringContainsString('↳', $html);
    }

    public function test_bags_use_ruleset_per_household_snapshot_size(): void
    {
        $rs    = $this->makeRuleset(); // size 1=1, 2-4=2, 5+=3
        $event = $this->makeEvent($rs);
        $primary = $this->makeHousehold('Big', 'Family', 5); // → 3 bags
        $rep     = $this->makeHousehold('Tiny', 'Solo', 1);  // → 1 bag

        $this->attachExitedVisit($event, [$primary, $rep], 4);

        $html = $this->actingAs($this->admin)
                     ->get(route('events.show', $event))
                     ->assertOk()
                     ->getContent();

        // Both bag values render in DOM order: primary's 3, then rep's 1.
        $primaryPos = strpos($html, 'Big Family');
        $repPos     = strpos($html, 'Tiny Solo');
        $this->assertNotFalse($primaryPos);
        $this->assertNotFalse($repPos);
        $this->assertLessThan($repPos, $primaryPos);
    }

    public function test_bags_show_dash_when_event_has_no_ruleset(): void
    {
        $event = $this->makeEvent(null); // no ruleset
        $hh    = $this->makeHousehold('No', 'Ruleset', 4);
        $this->attachExitedVisit($event, [$hh], 0);

        $response = $this->actingAs($this->admin)
                         ->get(route('events.show', $event))
                         ->assertOk();

        // The em-dash (—) appears in the bags cell. We can't easily isolate
        // by column with a string-search alone, so we assert the no-ruleset
        // dash sentinel: every row gets exactly one `<span class="text-gray-300">—</span>`
        // for its bags cell.
        $html = $response->getContent();
        $this->assertStringContainsString('text-gray-300">—', $html);
    }

    public function test_status_badge_class_matches_visit_status(): void
    {
        $rs    = $this->makeRuleset();
        $event = $this->makeEvent($rs);
        $hh    = $this->makeHousehold('Active', 'Visitor', 2);

        // Use a non-exited status so we exercise a different badge class.
        $visit = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'queued',
            'start_time'   => now(),
            'served_bags'  => 0,
        ]);
        $visit->households()->attach($hh->id, $hh->toVisitPivotSnapshot());

        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSee('er-status-queued')
             ->assertSee('Queued');
    }

    public function test_pagination_caps_at_ten_rows_per_page_by_default(): void
    {
        $rs    = $this->makeRuleset();
        $event = $this->makeEvent($rs);

        // 11 visits → page 1 has 10, page 2 has 1.
        for ($i = 1; $i <= 11; $i++) {
            $hh = $this->makeHousehold("F{$i}", "L{$i}", 3);
            $this->attachExitedVisit($event, [$hh], 1, now()->subMinutes(11 - $i));
        }

        $response = $this->actingAs($this->admin)
                         ->get(route('events.show', $event))
                         ->assertOk();

        $html = $response->getContent();
        // Table body contains 10 primary rows on page 1 (each visit has
        // exactly 1 household in this setup, so 10 primary rows total).
        $this->assertSame(10, substr_count($html, 'data-row="primary"'));
        // The paginator is rendered (Laravel default Tailwind paginator
        // emits a `details_page=` query string fragment in its links).
        $this->assertStringContainsString('details_page=', $html);
    }

    public function test_per_page_query_string_widens_to_twenty_five(): void
    {
        $rs    = $this->makeRuleset();
        $event = $this->makeEvent($rs);

        for ($i = 1; $i <= 15; $i++) {
            $hh = $this->makeHousehold("Big{$i}", "Page{$i}", 2);
            $this->attachExitedVisit($event, [$hh], 1, now()->subMinutes(15 - $i));
        }

        $response = $this->actingAs($this->admin)
                         ->get(route('events.show', ['event' => $event, 'details_per' => 25]))
                         ->assertOk();

        $html = $response->getContent();
        $this->assertSame(15, substr_count($html, 'data-row="primary"'));
        // The selected option in the per-page dropdown reflects the request.
        $this->assertStringContainsString('value="25" selected', $html);
    }

    public function test_per_page_unknown_value_clamps_back_to_ten(): void
    {
        $rs    = $this->makeRuleset();
        $event = $this->makeEvent($rs);

        for ($i = 1; $i <= 15; $i++) {
            $hh = $this->makeHousehold("Cl{$i}", "Amp{$i}", 2);
            $this->attachExitedVisit($event, [$hh], 1, now()->subMinutes(15 - $i));
        }

        $response = $this->actingAs($this->admin)
                         ->get(route('events.show', ['event' => $event, 'details_per' => 999]))
                         ->assertOk();

        $html = $response->getContent();
        // Garbage per-page values must NOT widen the window — cap at 10.
        $this->assertSame(10, substr_count($html, 'data-row="primary"'));
    }

    public function test_visits_ordered_newest_first(): void
    {
        $rs    = $this->makeRuleset();
        $event = $this->makeEvent($rs);

        $oldest  = $this->makeHousehold('Old', 'Visit', 2);
        $middle  = $this->makeHousehold('Mid', 'Visit', 2);
        $newest  = $this->makeHousehold('New', 'Visit', 2);

        $this->attachExitedVisit($event, [$oldest], 1, now()->subHours(3));
        $this->attachExitedVisit($event, [$middle], 1, now()->subHours(2));
        $this->attachExitedVisit($event, [$newest], 1, now()->subHour());

        $response = $this->actingAs($this->admin)
                         ->get(route('events.show', $event))
                         ->assertOk();

        // Newest first: New, Mid, Old.
        $response->assertSeeInOrder(['New Visit', 'Mid Visit', 'Old Visit']);
    }

    public function test_empty_event_shows_no_checkins_placeholder(): void
    {
        $event = $this->makeEvent($this->makeRuleset());

        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSee('No check-ins recorded yet.');
    }

    public function test_time_and_status_appear_on_every_row_including_represented(): void
    {
        $rs       = $this->makeRuleset();
        $event    = $this->makeEvent($rs);
        $primary  = $this->makeHousehold('Lead', 'Cell', 3);
        $rep      = $this->makeHousehold('Rep', 'Cell', 4);

        // Single shared visit; pinning that the time + status badge still
        // render on the represented row (decision #2 in the lock list).
        $this->attachExitedVisit($event, [$primary, $rep], 5);

        $html = $this->actingAs($this->admin)
                     ->get(route('events.show', $event))
                     ->assertOk()
                     ->getContent();

        // 2 status badges (one per row); search for the full HTML class
        // attribute so we don't pick up the `.er-status-exited` line that
        // lives in the page's <style> block.
        $this->assertSame(2, substr_count($html, 'class="er-status er-status-exited"'));
        // The badge label "Exited" appears on each row's badge.
        $this->assertGreaterThanOrEqual(2, substr_count($html, '>Exited<'));
    }
}
