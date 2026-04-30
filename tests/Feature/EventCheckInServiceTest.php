<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use App\Services\EventCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1.1.b — verifies EventCheckInService::checkIn uses a transaction
 * with lockForUpdate so two concurrent check-ins on the same lane cannot
 * silently produce duplicate queue positions.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1.b, Part 3 #1.
 *
 * SQLite tests cannot reproduce a real cross-process race (PHPUnit runs
 * sequentially and SQLite serializes writes anyway), but we can prove:
 *   - sequential calls produce monotonically increasing positions
 *   - the active-check is consistent with the position read (transaction)
 *   - failed paths roll back without leaving orphan visits
 *   - the unique index from Phase 1.1.a is never tripped by valid usage
 */
class EventCheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private EventCheckInService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name'  => 'Phase 1.1.b Test Event',
            'date'  => '2026-05-01',
            'lanes' => 2,
        ]);

        $this->service = app(EventCheckInService::class);
    }

    private function makeHousehold(string $first, string $last): Household
    {
        static $counter = 0;
        $counter++;

        return Household::create([
            'household_number' => 'TST' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'first_name'       => $first,
            'last_name'        => $last,
            'household_size'   => 1,
            'adults_count'     => 1,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    public function test_sequential_check_ins_produce_sequential_positions(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');
        $h2 = $this->makeHousehold('Ben', 'Brown');
        $h3 = $this->makeHousehold('Cal', 'Cohen');

        $v1 = $this->service->checkIn($this->event, $h1, 1);
        $v2 = $this->service->checkIn($this->event, $h2, 1);
        $v3 = $this->service->checkIn($this->event, $h3, 1);

        $this->assertSame(1, $v1->queue_position);
        $this->assertSame(2, $v2->queue_position);
        $this->assertSame(3, $v3->queue_position);
    }

    public function test_positions_are_independent_per_lane(): void
    {
        $h1 = $this->makeHousehold('Ann',  'Adams');
        $h2 = $this->makeHousehold('Ben',  'Brown');
        $h3 = $this->makeHousehold('Cal',  'Cohen');
        $h4 = $this->makeHousehold('Dana', 'Day');

        $v1 = $this->service->checkIn($this->event, $h1, 1); // lane 1, pos 1
        $v2 = $this->service->checkIn($this->event, $h2, 2); // lane 2, pos 1
        $v3 = $this->service->checkIn($this->event, $h3, 1); // lane 1, pos 2
        $v4 = $this->service->checkIn($this->event, $h4, 2); // lane 2, pos 2

        $this->assertSame([1, 1], [$v1->queue_position, $v1->lane]);
        $this->assertSame([1, 2], [$v2->queue_position, $v2->lane]);
        $this->assertSame([2, 1], [$v3->queue_position, $v3->lane]);
        $this->assertSame([2, 2], [$v4->queue_position, $v4->lane]);
    }

    public function test_already_active_check_in_throws_and_does_not_create_a_second_visit(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');

        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->assertSame(1, Visit::count(), 'first check-in should create exactly one visit');

        try {
            $this->service->checkIn($this->event, $h1, 1);
            $this->fail('Expected RuntimeException for already-active check-in');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(1, Visit::count(), 'failed re-check-in must not leave an orphan visit');
        $this->assertSame($first->id, Visit::first()->id);
    }

    /**
     * Once a visit is exited (end_time set), the same household may be
     * re-checked-in. The Phase 1.3 work will tighten this with a
     * "one-visit-per-event" guard with explicit override; this test pins
     * the current behavior so the 1.1.b/1.1.c changes don't silently alter it.
     *
     * After Phase 1.1.c.1 the exited visit's queue_position is NULL, so the
     * re-check-in starts at position 1 (not 2). The visit count goes 1 → 2,
     * proving a new visit was created.
     */
    public function test_re_check_in_after_exit_succeeds_with_next_position(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');

        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        $second = $this->service->checkIn($this->event, $h1, 1);

        $this->assertNotSame($first->id, $second->id);
        // Exited visit released position 1 (NULL), so the new check-in gets 1.
        $this->assertSame(1, $second->queue_position);
    }

    /**
     * Phase 1.1.c.1: marking a visit done (exiting it) must clear its
     * queue_position so a later reorder of active visits won't collide
     * with a position still occupied by an exited row.
     */
    public function test_mark_done_clears_queue_position(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');
        $visit = $this->service->checkIn($this->event, $h1, 1);

        $this->assertSame(1, $visit->queue_position);

        $this->service->markDone($visit);
        $visit->refresh();

        $this->assertNull($visit->queue_position, 'exited visit must release its queue_position');
        $this->assertSame('exited', $visit->visit_status);
    }

    /**
     * Phase 1.1.c.1: with queue_position nullable, multiple exited visits
     * can coexist with NULL positions on the same (event_id, lane). The
     * unique index from Phase 1.1.a treats NULLs as distinct so it does
     * not constrain exited rows.
     */
    public function test_multiple_exited_visits_can_share_null_position(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');
        $h2 = $this->makeHousehold('Ben', 'Brown');
        $h3 = $this->makeHousehold('Cal', 'Cohen');

        $v1 = $this->service->checkIn($this->event, $h1, 1);
        $v2 = $this->service->checkIn($this->event, $h2, 1);
        $v3 = $this->service->checkIn($this->event, $h3, 1);

        $this->service->markDone($v1);
        $this->service->markDone($v2);
        $this->service->markDone($v3);

        $exitedVisits = Visit::where('event_id', $this->event->id)
            ->where('visit_status', 'exited')
            ->get();

        $this->assertCount(3, $exitedVisits);
        $this->assertTrue($exitedVisits->every(fn ($v) => $v->queue_position === null));
    }

    /**
     * Phase 1.1.c.1: after a visit exits and releases its position, a
     * fresh check-in on the same lane gets its position computed against
     * the remaining active visits — exited rows are skipped because their
     * position is NULL (and SQL MAX() ignores NULLs).
     */
    public function test_check_in_after_exits_uses_active_max_only(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');
        $h2 = $this->makeHousehold('Ben', 'Brown');
        $h3 = $this->makeHousehold('Cal', 'Cohen');

        $v1 = $this->service->checkIn($this->event, $h1, 1);
        $v2 = $this->service->checkIn($this->event, $h2, 1);
        $this->service->markDone($v1);
        $this->service->markDone($v2);

        // Both prior visits are exited (positions NULL). The next check-in
        // should restart from position 1, not continue from 3.
        $v3 = $this->service->checkIn($this->event, $h3, 1);

        $this->assertSame(1, $v3->queue_position);
    }

    /**
     * If a household attach fails inside the transaction, the visit creation
     * must roll back. Pre-1.2.b this was triggered by an FK violation when
     * a missing household_id was passed straight to attach(); 1.2.b's
     * bulk-load + explicit-existence-check translates that into a clear
     * RuntimeException, but the rollback contract is unchanged — the
     * surrounding DB::transaction must reverse the visit row.
     */
    public function test_failed_attach_rolls_back_visit_creation(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');

        $initialVisitCount = Visit::count();

        try {
            // 99999 is a non-existent household id.
            $this->service->checkIn($this->event, $h1, 1, [99999]);
            $this->fail('Expected an exception from attaching a non-existent household');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(
            $initialVisitCount,
            Visit::count(),
            'visit must be rolled back when a downstream attach fails'
        );
    }

    // ─── Phase 1.2.b — pivot snapshot tests ──────────────────────────────────

    /**
     * The headline 1.2.b regression: editing a household's demographics
     * AFTER a check-in must NOT change the pivot snapshot for that visit.
     * This is the entire point of snapshotting — historical reports stay
     * stable.
     */
    public function test_editing_household_after_check_in_does_not_change_pivot_snapshot(): void
    {
        $h1 = $this->makeHousehold('Eve', 'Edit');
        $h1->update([
            'household_size' => 4,
            'adults_count'   => 2,
            'children_count' => 2,
            'seniors_count'  => 0,
            'vehicle_make'   => 'Toyota',
            'vehicle_color'  => 'Blue',
        ]);

        $visit = $this->service->checkIn($this->event, $h1->fresh(), 1);

        // Mutate the household after the visit is recorded.
        $h1->update([
            'household_size' => 99,
            'adults_count'   => 50,
            'children_count' => 49,
            'seniors_count'  => 0,
            'vehicle_make'   => 'Lamborghini',
            'vehicle_color'  => 'Gold',
        ]);

        $pivot = $visit->fresh()->households->first()->pivot;

        $this->assertSame(4, (int) $pivot->household_size, 'snapshot must reflect attach-time household_size');
        $this->assertSame(2, (int) $pivot->adults_count);
        $this->assertSame(2, (int) $pivot->children_count);
        $this->assertSame(0, (int) $pivot->seniors_count);
        $this->assertSame('Toyota', $pivot->vehicle_make);
        $this->assertSame('Blue',   $pivot->vehicle_color);
    }

    public function test_check_in_snapshots_demographics_on_pivot(): void
    {
        $h1 = $this->makeHousehold('Sam', 'Snap');
        $h1->update([
            'household_size' => 5,
            'adults_count'   => 2,
            'children_count' => 2,
            'seniors_count'  => 1,
            'vehicle_make'   => 'Honda',
            'vehicle_color'  => 'Silver',
        ]);

        $visit = $this->service->checkIn($this->event, $h1->fresh(), 1);
        $pivot = $visit->fresh()->households->first()->pivot;

        $this->assertSame(5, (int) $pivot->household_size);
        $this->assertSame(2, (int) $pivot->adults_count);
        $this->assertSame(2, (int) $pivot->children_count);
        $this->assertSame(1, (int) $pivot->seniors_count);
        $this->assertSame('Honda', $pivot->vehicle_make);
        $this->assertSame('Silver', $pivot->vehicle_color);
    }

    /**
     * Each represented household gets its OWN snapshot — the rep's
     * demographics must NOT be smeared across the represented entries.
     */
    public function test_check_in_snapshots_each_represented_household_separately(): void
    {
        $rep   = $this->makeHousehold('Rep',  'Resentative');
        $rep->update(['household_size' => 2, 'adults_count' => 2, 'vehicle_make' => 'Subaru']);
        $rep1  = $this->makeHousehold('Rep1', 'Member');
        $rep1->update(['household_size' => 4, 'adults_count' => 2, 'children_count' => 2, 'vehicle_make' => null]);
        $rep2  = $this->makeHousehold('Rep2', 'Member');
        $rep2->update(['household_size' => 6, 'adults_count' => 3, 'seniors_count' => 3, 'vehicle_make' => 'Ford']);

        $visit = $this->service->checkIn($this->event, $rep->fresh(), 1, [$rep1->id, $rep2->id]);
        $byId  = $visit->fresh()->households->keyBy('id');

        $this->assertSame(2, (int) $byId[$rep->id]->pivot->household_size);
        $this->assertSame('Subaru', $byId[$rep->id]->pivot->vehicle_make);

        $this->assertSame(4, (int) $byId[$rep1->id]->pivot->household_size);
        $this->assertSame(2, (int) $byId[$rep1->id]->pivot->children_count);
        $this->assertNull($byId[$rep1->id]->pivot->vehicle_make);

        $this->assertSame(6, (int) $byId[$rep2->id]->pivot->household_size);
        $this->assertSame(3, (int) $byId[$rep2->id]->pivot->seniors_count);
        $this->assertSame('Ford', $byId[$rep2->id]->pivot->vehicle_make);
    }

    /**
     * Pin the contract that demographic snapshot columns are non-null after
     * any successful check-in. With the NOT NULL DB constraint, this is
     * effectively the constraint's own assertion — but worth pinning here
     * so a future regression in the service (e.g. payload typo) gets a
     * clear failure mode at the unit-test layer instead of only at insert.
     */
    public function test_pivot_demographic_snapshot_columns_are_non_null_after_check_in(): void
    {
        $h1 = $this->makeHousehold('NoNull', 'Test');
        $visit = $this->service->checkIn($this->event, $h1, 1);

        $row = \Illuminate\Support\Facades\DB::table('visit_households')
            ->where('visit_id', $visit->id)
            ->where('household_id', $h1->id)
            ->first();

        $this->assertNotNull($row->household_size);
        $this->assertNotNull($row->children_count);
        $this->assertNotNull($row->adults_count);
        $this->assertNotNull($row->seniors_count);
    }
}
