<?php

namespace Tests\Feature;

use App\Exceptions\HouseholdAlreadyServedException;
use App\Models\CheckInOverride;
use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for EventCheckInService::checkIn() across Phase 1.1.b, 1.2.b, and 1.3.
 *
 * - 1.1.b — read-then-insert wrapped in DB::transaction + lockForUpdate so
 *   two concurrent same-lane check-ins cannot produce duplicate positions.
 * - 1.1.c.1 — markDone clears queue_position so exited rows don't collide
 *   with active reorder positions.
 * - 1.2.b — pivot snapshot at attach time keeps historical reports temporally
 *   stable when a household is edited after a visit.
 * - 1.3 — three-mode re-check-in policy ('allow' / 'override' / 'deny') with
 *   force-flag + Log::warning audit on supervisor override; active-duplicate
 *   case stays a hard RuntimeException invariant regardless of policy.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1.b, §1.2, §1.3.
 *
 * SQLite tests cannot reproduce a real cross-process race, but we can prove:
 *   - sequential calls produce monotonically increasing positions
 *   - the active-check is consistent with the position read (transaction)
 *   - failed paths roll back without leaving orphan visits
 *   - the unique index from Phase 1.1.a is never tripped by valid usage
 *   - the policy matrix correctly throws/proceeds across the three modes
 */
class EventCheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private EventCheckInService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // SettingService caches in a class-static property that survives
        // RefreshDatabase. Without flushing, a setting written in one test
        // would leak into the next when the cache disagrees with the empty DB.
        SettingService::flush();

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

    // ─── Phase 1.3 — re-check-in policy matrix ────────────────────────────────

    /**
     * Phase 1.3, policy='allow': preserves the pre-1.3 behavior. Once a visit
     * is exited (end_time set), the same household may be re-checked-in
     * without any override. Also pins the 1.1.c.1 contract that the exited
     * visit's queue_position is NULL, so the new check-in starts at position 1.
     */
    public function test_re_check_in_after_exit_with_policy_allow_succeeds(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'allow');

        $h1 = $this->makeHousehold('Ann', 'Adams');

        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        $second = $this->service->checkIn($this->event, $h1, 1);

        $this->assertNotSame($first->id, $second->id);
        // Exited visit released position 1 (NULL), so the new check-in gets 1.
        $this->assertSame(1, $second->queue_position);
    }

    /**
     * Phase 1.3, policy='override' (the audited default): a re-check-in
     * after exit throws HouseholdAlreadyServedException unless the caller
     * passes $force=true. The exception carries enough context for the
     * controller to render an override modal (event id + offending IDs +
     * allowOverride=true).
     */
    public function test_re_check_in_after_exit_with_policy_override_throws_without_force(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h1 = $this->makeHousehold('Ann', 'Adams');

        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        try {
            $this->service->checkIn($this->event, $h1, 1);
            $this->fail('Expected HouseholdAlreadyServedException under policy=override');
        } catch (HouseholdAlreadyServedException $e) {
            $this->assertTrue($e->allowOverride, 'override policy must mark exception as overrideable');
            $this->assertSame($this->event->id, $e->eventId);
            $this->assertContains($h1->id, $e->householdIds);
        }

        $this->assertSame(1, Visit::count(), 'no second visit should have been created');
    }

    /**
     * Phase 1.3, policy='override' + force=true: supervisor override succeeds
     * and writes a structured row to checkin_overrides. Phase 1.3.c moved
     * this from Log::warning to a real DB table so Phase 4's admin audit-log
     * viewer inherits real history.
     */
    public function test_re_check_in_after_exit_with_policy_override_succeeds_with_force(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h1 = $this->makeHousehold('Ann', 'Adams');
        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        $this->assertSame(0, CheckInOverride::count(), 'baseline: no override rows yet');

        $second = $this->service->checkIn(
            $this->event, $h1, 1,
            representedIds: null,
            force: true,
            overrideReason: 'forgot a bag',
        );

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Visit::count());
    }

    /**
     * Phase 1.3.c — structured override audit row. The supervisor click
     * persists a checkin_overrides row with user_id (caller is currently
     * unauthenticated in this test so it's NULL), event_id, the resolved
     * representative + offending household IDs, the prior visit IDs, and
     * the reason. Replaces the prior Log::warning approach.
     */
    public function test_supervisor_override_persists_checkin_override_row(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h1 = $this->makeHousehold('Audrey', 'Audit');
        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        $second = $this->service->checkIn(
            $this->event, $h1, 1,
            representedIds: null,
            force: true,
            overrideReason: 'second bag — supervisor confirmed',
        );

        $this->assertSame(1, CheckInOverride::count());

        $override = CheckInOverride::first();
        $this->assertNull($override->user_id, 'no Auth::user in unit-test context → NULL preserves audit row');
        $this->assertSame($this->event->id, $override->event_id);
        $this->assertSame($h1->id, $override->representative_household_id);
        $this->assertSame([$h1->id], $override->household_ids);
        $this->assertSame([$first->id], $override->prior_visit_ids);
        $this->assertSame('second bag — supervisor confirmed', $override->reason);
        $this->assertNotNull($override->created_at);
    }

    /**
     * Phase 1.3.c — when checkIn fails AFTER the override audit row is
     * staged (e.g. an FK violation on attach() of a bad represented ID),
     * the surrounding DB::transaction must roll BOTH the visit AND the
     * audit row back. No orphan override rows for visits that never landed.
     */
    public function test_failed_check_in_after_override_rolls_back_audit_row(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h1 = $this->makeHousehold('Roll', 'Back');
        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        try {
            // 99999 is a non-existent represented id → service throws
            // RuntimeException → transaction rolls back the override row too.
            $this->service->checkIn(
                $this->event, $h1, 1,
                representedIds: [99999],
                force: true,
                overrideReason: 'will fail mid-transaction',
            );
            $this->fail('Expected RuntimeException for non-existent represented household');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(
            0,
            CheckInOverride::count(),
            'audit row must roll back when the surrounding visit creation fails'
        );
    }

    /**
     * Phase 1.3.c — service-level guard against empty reason on the override
     * path. CheckInRequest already validates this for HTTP callers, but a
     * direct service caller (test, console command, future internal code)
     * could pass force=true with a null/empty reason. The DB's NOT NULL
     * would catch this with a confusing integrity-violation stack; the
     * service catches it first with a clear contract violation. Throwing
     * inside the transaction also rolls the visit back, so no partial state.
     */
    public function test_force_with_empty_reason_throws_invalid_argument_and_rolls_back(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h1 = $this->makeHousehold('Empty', 'Reason');
        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        try {
            $this->service->checkIn(
                $this->event, $h1, 1,
                representedIds: null,
                force: true,
                overrideReason: '   ', // whitespace only — must be rejected
            );
            $this->fail('Expected InvalidArgumentException for empty override reason');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('non-empty reason', $e->getMessage());
        }

        $this->assertSame(1, Visit::count(), 'no second visit should have been created');
        $this->assertSame(0, CheckInOverride::count(), 'no audit row should have been created');
    }

    /**
     * Phase 1.3.c — policy='allow' must NOT write an audit row even when
     * the caller passes force=true. The audit only captures genuine
     * overrides (i.e., a guard that was actively bypassed). policy='allow'
     * has no guard to bypass.
     */
    public function test_policy_allow_with_force_does_not_write_audit_row(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'allow');

        $h1 = $this->makeHousehold('Sil', 'Ent');
        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        $this->service->checkIn(
            $this->event, $h1, 1,
            representedIds: null,
            force: true,
            overrideReason: 'force on allow policy is a no-op',
        );

        $this->assertSame(0, CheckInOverride::count());
    }

    /**
     * Phase 1.3, policy='deny': re-check-in is blocked even when force=true is
     * supplied. The exception's allowOverride=false signals to the controller
     * that there is no override path to offer.
     */
    public function test_re_check_in_after_exit_with_policy_deny_throws_even_with_force(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'deny');

        $h1 = $this->makeHousehold('Ann', 'Adams');
        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->service->markDone($first);

        try {
            $this->service->checkIn(
                $this->event, $h1, 1,
                representedIds: null,
                force: true,
                overrideReason: 'attempting to bypass deny',
            );
            $this->fail('Expected HouseholdAlreadyServedException — deny policy must not allow force');
        } catch (HouseholdAlreadyServedException $e) {
            $this->assertFalse($e->allowOverride, 'deny policy must mark exception as non-overrideable');
        }

        $this->assertSame(1, Visit::count());
    }

    /**
     * Phase 1.3: a representative pickup where any of the represented
     * households has a prior exited visit at this event triggers the
     * already-served guard. The exception's householdIds carry the full
     * candidate set so the controller can resolve labels and render the
     * conflict modal.
     */
    public function test_re_check_in_with_overlapping_represented_household_triggers_policy(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $rep  = $this->makeHousehold('Primary', 'Rep');
        $rep1 = $this->makeHousehold('Member',  'One');
        $rep2 = $this->makeHousehold('Member',  'Two');

        // rep1 was checked-in alone earlier and exited.
        $earlier = $this->service->checkIn($this->event, $rep1, 1);
        $this->service->markDone($earlier);

        try {
            $this->service->checkIn($this->event, $rep, 1, [$rep1->id, $rep2->id]);
            $this->fail('Expected HouseholdAlreadyServedException for overlap with prior visit');
        } catch (HouseholdAlreadyServedException $e) {
            $this->assertTrue($e->allowOverride);
            // The exception carries ONLY the offending subset, not the full
            // candidate set — so the controller can render exactly which
            // household(s) collided without a follow-up query.
            $this->assertSame([$rep1->id], $e->householdIds);
        }

        $this->assertSame(1, Visit::count(), 'no new visit should have been created');
    }

    /**
     * Phase 1.3 invariant: the active-already-served case (a visit still in
     * the queue) is NOT subject to the policy and is NOT overrideable. Even
     * with policy='allow' and force=true, attempting to check the same
     * household in twice while the first visit is still active throws the
     * original RuntimeException — never HouseholdAlreadyServedException.
     */
    public function test_active_already_checked_in_blocks_regardless_of_policy_or_force(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'allow');

        $h1 = $this->makeHousehold('Ann', 'Adams');
        $this->service->checkIn($this->event, $h1, 1);
        // No markDone — visit is still active.

        try {
            $this->service->checkIn(
                $this->event, $h1, 1,
                representedIds: null,
                force: true,
                overrideReason: 'should not bypass active block',
            );
            $this->fail('Expected RuntimeException for active duplicate even with force=true and policy=allow');
        } catch (HouseholdAlreadyServedException $e) {
            $this->fail('Active duplicates must throw the original RuntimeException, not HouseholdAlreadyServedException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already has an active check-in', $e->getMessage());
        }

        $this->assertSame(1, Visit::count());
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
