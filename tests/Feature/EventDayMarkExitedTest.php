<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP feature tests for EventDayController::markExited()
 *
 * markExited is the trigger that:
 *   - Flips visit_status loaded → exited
 *   - Sets exited_at + end_time + nulls queue_position (releases the slot)
 *   - Computes served_bags from the event's ruleset, summed across ALL
 *     households on the visit (covers representative-pickup multi-household
 *     visits)
 *
 * Auth model: session key ed_{event_id}_exit = true. Same pattern as
 * EventDayMarkLoadedTest. The route is PATCH /ed/{event}/visits/{visit}/exited
 * registered under the `event-day` route group.
 *
 * Pinned cases:
 *   1. Auth — unauth returns 401
 *   2. Scope — visit on another event returns 404
 *   3. Wrong status — visit not yet loaded returns 422
 *   4. Happy path with no ruleset — served_bags = 0
 *   5. Happy path with ruleset, single household — bags computed via
 *      ruleset->getBagsFor(household_size)
 *   6. Multi-household pickup — bags summed across all attached households
 *   7. queue_position nulled on exit (releases the lane slot)
 *   8. exited_at + end_time both timestamped
 */
class EventDayMarkExitedTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $this->event = Event::create([
            'name'  => 'Mark Exited Test Event',
            'date'  => '2026-06-01',
            'lanes' => 1,
        ]);
    }

    private function exitSession(): array
    {
        return ['ed_' . $this->event->id . '_exit' => true];
    }

    private function patchExited(Visit $visit, ?Event $event = null): \Illuminate\Testing\TestResponse
    {
        $event ??= $this->event;
        return $this->withSession($this->exitSession())
                    ->patch("/ed/{$event->id}/visits/{$visit->id}/exited");
    }

    private function makeHousehold(int $size = 3): Household
    {
        static $h = 0;
        $h++;
        return Household::create([
            'household_number' => 'EX' . str_pad((string) $h, 5, '0', STR_PAD_LEFT),
            'first_name'       => 'Test',
            'last_name'        => "EX{$h}",
            'household_size'   => $size,
            'adults_count'     => $size,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    private function makeLoadedVisit(?int $lane = 1, ?int $position = 1, ?Event $event = null): Visit
    {
        $event ??= $this->event;
        return Visit::create([
            'event_id'             => $event->id,
            'lane'                 => $lane,
            'queue_position'       => $position,
            'visit_status'         => 'loaded',
            'start_time'           => now()->subMinutes(30),
            'queued_at'            => now()->subMinutes(20),
            'loading_completed_at' => now()->subMinutes(2),
        ]);
    }

    /**
     * Bag-curve ruleset. The column is `rules` (JSON cast), each rule shaped
     * {min, max, bags} per AllocationRuleset::getBagsFor().
     */
    private function attachRuleset(array $bagsBySize = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6]): AllocationRuleset
    {
        $rules = [];
        foreach ($bagsBySize as $size => $bags) {
            $rules[] = ['min' => $size, 'max' => $size, 'bags' => $bags];
        }

        $ruleset = AllocationRuleset::create([
            'name'      => 'Test Ruleset',
            'rules'     => $rules,
            'is_active' => true,
        ]);

        $this->event->update(['ruleset_id' => $ruleset->id]);
        $this->event->refresh();

        return $ruleset;
    }

    // ─── Test 1 — Auth guard ──────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $visit = $this->makeLoadedVisit();

        $response = $this->patch("/ed/{$this->event->id}/visits/{$visit->id}/exited");

        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorised']);

        $this->assertSame('loaded', $visit->fresh()->visit_status,
            'visit status should be untouched when caller has no exit session');
    }

    // ─── Test 2 — Cross-event scope check ─────────────────────────────────────

    public function test_visit_belonging_to_different_event_returns_404(): void
    {
        $otherEvent = Event::create([
            'name'  => 'Other Event',
            'date'  => '2026-06-15',
            'lanes' => 1,
        ]);
        $visit = $this->makeLoadedVisit(event: $otherEvent);

        $response = $this->withSession($this->exitSession())
                         ->patch("/ed/{$this->event->id}/visits/{$visit->id}/exited");

        $response->assertStatus(404);
        $this->assertSame('loaded', $visit->fresh()->visit_status);
    }

    // ─── Test 3 — Wrong status guard ──────────────────────────────────────────

    public function test_visit_not_in_loaded_status_returns_422(): void
    {
        $visit = $this->makeLoadedVisit();
        $visit->update(['visit_status' => 'queued']);

        $response = $this->patchExited($visit);

        $response->assertStatus(422)
                 ->assertJson(['error' => 'Visit is not loaded yet.']);

        $this->assertSame('queued', $visit->fresh()->visit_status);
    }

    // ─── Test 4 — Happy path, no ruleset ──────────────────────────────────────

    public function test_exit_succeeds_with_no_ruleset_and_zero_bags(): void
    {
        $visit = $this->makeLoadedVisit();
        $visit->households()->attach($this->makeHousehold(3)->id, [
            'household_size' => 3, 'adults_count' => 3, 'children_count' => 0, 'seniors_count' => 0,
        ]);

        $response = $this->patchExited($visit);

        $response->assertOk()->assertJson(['ok' => true]);

        $fresh = $visit->fresh();
        $this->assertSame('exited', $fresh->visit_status);
        $this->assertSame(0, (int) $fresh->served_bags,
            'no ruleset means no bag curve; served_bags should be 0');
    }

    // ─── Test 5 — Happy path with ruleset, single household ───────────────────

    public function test_exit_with_ruleset_computes_served_bags_for_single_household(): void
    {
        $this->attachRuleset([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]);
        $visit = $this->makeLoadedVisit();
        $visit->households()->attach($this->makeHousehold(4)->id, [
            'household_size' => 4, 'adults_count' => 2, 'children_count' => 2, 'seniors_count' => 0,
        ]);

        $response = $this->patchExited($visit);

        $response->assertOk();
        $this->assertSame(4, (int) $visit->fresh()->served_bags,
            'ruleset getBagsFor(4) should equal 4');
    }

    // ─── Test 6 — Multi-household representative pickup ───────────────────────

    public function test_multi_household_pickup_sums_bags_across_all_households(): void
    {
        $this->attachRuleset([1 => 1, 2 => 2, 3 => 3, 4 => 4]);
        $visit = $this->makeLoadedVisit();

        // Representative + two represented = 3 households on one visit.
        // Sizes 2 + 3 + 4 → bags 2 + 3 + 4 = 9
        $rep    = $this->makeHousehold(2);
        $repd1  = $this->makeHousehold(3);
        $repd2  = $this->makeHousehold(4);

        $visit->households()->attach($rep->id,   ['household_size' => 2, 'adults_count' => 2, 'children_count' => 0, 'seniors_count' => 0]);
        $visit->households()->attach($repd1->id, ['household_size' => 3, 'adults_count' => 3, 'children_count' => 0, 'seniors_count' => 0]);
        $visit->households()->attach($repd2->id, ['household_size' => 4, 'adults_count' => 4, 'children_count' => 0, 'seniors_count' => 0]);

        $response = $this->patchExited($visit);

        $response->assertOk();
        $this->assertSame(9, (int) $visit->fresh()->served_bags,
            'bags should sum across all attached households (2+3+4=9)');
    }

    // ─── Test 7 — queue_position nulled on exit ───────────────────────────────

    public function test_exit_nulls_queue_position_to_free_the_slot(): void
    {
        $visit = $this->makeLoadedVisit(lane: 1, position: 7);

        $response = $this->patchExited($visit);

        $response->assertOk();
        $fresh = $visit->fresh();
        $this->assertNull($fresh->queue_position,
            'queue_position must be NULL after exit so a later reorder/check-in can reuse the slot');
        $this->assertSame(1, (int) $fresh->lane, 'lane should be preserved');
    }

    // ─── Test 8 — exited_at + end_time both timestamped ───────────────────────

    public function test_exit_sets_both_exited_at_and_end_time(): void
    {
        $visit = $this->makeLoadedVisit();
        $this->assertNull($visit->exited_at);
        $this->assertNull($visit->end_time);

        $this->patchExited($visit)->assertOk();

        $fresh = $visit->fresh();
        $this->assertNotNull($fresh->exited_at, 'exited_at must be set so reports can compute service time');
        $this->assertNotNull($fresh->end_time,  'end_time must be set so the row counts as a closed visit');
    }
}
