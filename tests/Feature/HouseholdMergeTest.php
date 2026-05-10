<?php

namespace Tests\Feature;

use App\Exceptions\HouseholdMergeConflictException;
use App\Models\CheckInOverride;
use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Household;
use App\Models\Pledge;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\HouseholdMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6.5.d — pins the household-merge contract.
 *
 * The service moves every FK pointing at the duplicate over to the keeper
 * atomically, rewrites duplicate IDs inside checkin_overrides.household_ids,
 * recomputes the cached events_attended_count on the keeper, and deletes
 * the duplicate row. Conflict cases:
 *
 *   - open visit at same event       → refused (open_visit)
 *   - representative-chain cycle     → refused (representative_cycle)
 *   - confirmed pre-reg same event   → auto-cancelled (match_status='cancelled',
 *                                      household_id=null) — merge proceeds
 */
class HouseholdMergeTest extends TestCase
{
    use RefreshDatabase;

    private HouseholdMergeService $service;
    private User $admin;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $admin->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin-hh-merge@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $admin->id,
            'email_verified_at' => now(),
        ]);

        $viewer = Role::create(['name' => 'VIEWER', 'display_name' => 'Viewer', 'description' => '']);
        RolePermission::create(['role_id' => $viewer->id, 'permission' => 'households.view']);
        $this->viewer = User::create([
            'name'              => 'Viewer',
            'email'             => 'viewer-hh-merge@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $viewer->id,
            'email_verified_at' => now(),
        ]);

        $this->service = app(HouseholdMergeService::class);
    }

    private function makeHousehold(string $first, ?string $phone = null, array $overrides = []): Household
    {
        static $serial = 0;
        $serial++;

        return Household::create([
            'household_number' => str_pad((string) (10000 + $serial), 5, '0', STR_PAD_LEFT),
            'first_name'       => $first,
            'last_name'        => 'Family',
            'phone'            => $phone,
            'household_size'   => 1,
            'children_count'   => 0,
            'adults_count'     => 1,
            'seniors_count'    => 0,
            'qr_token'         => bin2hex(random_bytes(16)),
            ...$overrides,
        ]);
    }

    private function makeEvent(string $name = 'Test Event'): Event
    {
        return Event::create([
            'name'   => $name,
            'date'   => now()->subDay()->toDateString(),
            'status' => 'past',
            'lanes'  => 1,
        ]);
    }

    private function makeVisit(Event $event, Household $primary, string $status = 'exited'): Visit
    {
        $visit = Visit::create([
            'event_id'     => $event->id,
            'visit_status' => $status,
            'lane_number'  => 1,
            'start_time'   => now(),
        ]);
        $visit->households()->attach($primary->id, $primary->toVisitPivotSnapshot());
        return $visit;
    }

    // ─── Service-level: atomic transfer ─────────────────────────────────────

    public function test_visits_are_re_pointed_at_the_keeper(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $eventA = $this->makeEvent('A');
        $eventB = $this->makeEvent('B');

        $this->makeVisit($eventA, $keeper);
        $this->makeVisit($eventB, $dupe);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['visits_transferred']);
        $this->assertSame(0, DB::table('visit_households')->where('household_id', $dupe->id)->count());
        $this->assertSame(2, DB::table('visit_households')->where('household_id', $keeper->id)->count());
        $this->assertNull(Household::find($dupe->id));
    }

    public function test_pledges_are_re_pointed_at_the_keeper(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');

        Pledge::create([
            'household_id'    => $dupe->id,
            'source_or_payee' => 'Dupe Family',
            'amount'          => 100,
            'pledged_at'      => now()->toDateString(),
            'expected_at'     => now()->addDays(30)->toDateString(),
            'status'          => 'open',
        ]);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['pledges_transferred']);
        $this->assertSame(1, Pledge::where('household_id', $keeper->id)->count());
        $this->assertSame(0, Pledge::where('household_id', $dupe->id)->count());
    }

    public function test_pre_registrations_both_kinds_are_re_pointed(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $event  = $this->makeEvent();

        EventPreRegistration::create([
            'event_id'        => $event->id,
            'attendee_number' => '00001',
            'first_name'      => 'Dupe',
            'last_name'       => 'Family',
            'email'           => 'dupe@example.test',
            'household_size'  => 1,
            'children_count'  => 0,
            'adults_count'    => 1,
            'seniors_count'   => 0,
            'household_id'    => $dupe->id,
            'match_status'    => 'matched',
        ]);
        EventPreRegistration::create([
            'event_id'                => $event->id,
            'attendee_number'         => '00002',
            'first_name'              => 'Some',
            'last_name'               => 'One',
            'email'                   => 'someone@example.test',
            'household_size'          => 1,
            'children_count'          => 0,
            'adults_count'            => 1,
            'seniors_count'           => 0,
            'potential_household_id'  => $dupe->id,
            'match_status'            => 'potential_match',
        ]);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['pre_regs_transferred']);
        $this->assertSame(1, $result['potential_pre_regs_transferred']);
        $this->assertSame(1, EventPreRegistration::where('household_id', $keeper->id)->count());
        $this->assertSame(1, EventPreRegistration::where('potential_household_id', $keeper->id)->count());
    }

    public function test_conflicting_pre_registration_on_same_event_is_auto_cancelled(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $event  = $this->makeEvent();

        // Keeper already confirmed for this event.
        EventPreRegistration::create([
            'event_id'        => $event->id, 'attendee_number' => '00001',
            'first_name' => 'K', 'last_name' => 'X', 'email' => 'k@example.test',
            'household_size' => 1, 'children_count' => 0, 'adults_count' => 1, 'seniors_count' => 0,
            'household_id' => $keeper->id, 'match_status' => 'matched',
        ]);
        // Dupe also confirmed for the same event — collision.
        $dupeReg = EventPreRegistration::create([
            'event_id'        => $event->id, 'attendee_number' => '00002',
            'first_name' => 'D', 'last_name' => 'X', 'email' => 'd@example.test',
            'household_size' => 1, 'children_count' => 0, 'adults_count' => 1, 'seniors_count' => 0,
            'household_id' => $dupe->id, 'match_status' => 'matched',
        ]);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['pre_regs_cancelled']);
        $this->assertSame(0, $result['pre_regs_transferred']); // already cancelled before re-point
        $dupeReg->refresh();
        $this->assertSame('cancelled', $dupeReg->match_status);
        $this->assertNull($dupeReg->household_id);
        // Keeper's confirmed reg untouched.
        $this->assertSame(1, EventPreRegistration::where('household_id', $keeper->id)->where('match_status', 'matched')->count());
    }

    public function test_represented_households_are_re_pointed(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $rep1   = $this->makeHousehold('Rep1', null, ['representative_household_id' => $dupe->id]);
        $rep2   = $this->makeHousehold('Rep2', null, ['representative_household_id' => $dupe->id]);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(2, $result['represented_transferred']);
        $this->assertSame($keeper->id, $rep1->fresh()->representative_household_id);
        $this->assertSame($keeper->id, $rep2->fresh()->representative_household_id);
    }

    public function test_keeper_self_loop_is_cleared_when_keeper_was_represented_by_duplicate(): void
    {
        $dupe   = $this->makeHousehold('Dupe');
        $keeper = $this->makeHousehold('Keep', null, ['representative_household_id' => $dupe->id]);

        $this->service->merge($keeper, $dupe);

        // Without the self-loop guard, keeper.rep_id would point at itself.
        $this->assertNull($keeper->fresh()->representative_household_id);
    }

    public function test_events_attended_count_is_recomputed_on_keeper(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $eventA = $this->makeEvent('A');
        $eventB = $this->makeEvent('B');
        $eventC = $this->makeEvent('C');

        // Keeper attended A. Dupe attended A (same as keeper) and B and C.
        // After merge, COUNT(DISTINCT event_id) = 3 (A, B, C — A only counts once).
        $this->makeVisit($eventA, $keeper);
        $this->makeVisit($eventA, $dupe);
        $this->makeVisit($eventB, $dupe);
        $this->makeVisit($eventC, $dupe);

        $this->service->merge($keeper, $dupe);

        $this->assertSame(3, (int) $keeper->fresh()->events_attended_count);
    }

    public function test_same_visit_dedup_does_not_violate_unique_constraint(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $event  = $this->makeEvent();

        // Both households on the SAME visit — possible if both were attached
        // as represented households on a single check-in.
        $visit = Visit::create([
            'event_id'     => $event->id,
            'visit_status' => 'exited',
            'lane_number'  => 1,
            'start_time'   => now(),
        ]);
        $visit->households()->attach($keeper->id, $keeper->toVisitPivotSnapshot());
        $visit->households()->attach($dupe->id, $dupe->toVisitPivotSnapshot());

        $result = $this->service->merge($keeper, $dupe);

        // The dupe's pivot row is dropped (visit already counts the household via keeper),
        // and the bulk re-point UPDATEs the remaining zero rows.
        $this->assertSame(1, $result['visit_pivot_dedups']);
        $this->assertSame(0, $result['visits_transferred']);
        $this->assertSame(1, DB::table('visit_households')->where('visit_id', $visit->id)->count());
    }

    public function test_open_visit_conflict_at_same_event_throws_and_rolls_back(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $event  = $this->makeEvent();

        $this->makeVisit($event, $keeper, 'checked_in');
        $this->makeVisit($event, $dupe, 'queued');

        // Side-state we'd hate to lose if a partial merge slipped through.
        Pledge::create([
            'household_id'    => $dupe->id,
            'source_or_payee' => 'Dupe Family',
            'amount'          => 50, 'pledged_at' => now()->toDateString(),
            'expected_at'     => now()->addDays(7)->toDateString(),
            'status'          => 'open',
        ]);

        try {
            $this->service->merge($keeper, $dupe);
            $this->fail('Expected HouseholdMergeConflictException');
        } catch (HouseholdMergeConflictException $e) {
            $this->assertSame('open_visit', $e->conflictType);
            $this->assertSame([$event->id], $e->conflictingIds);
        }

        // Rollback verification — duplicate still owns its own state.
        $this->assertNotNull(Household::find($dupe->id));
        $this->assertSame(1, Pledge::where('household_id', $dupe->id)->count());
    }

    public function test_open_visit_at_different_events_does_not_block_merge(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $eventA = $this->makeEvent('A');
        $eventB = $this->makeEvent('B');

        $this->makeVisit($eventA, $keeper, 'checked_in');
        $this->makeVisit($eventB, $dupe, 'queued');

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['visits_transferred']);
        $this->assertNull(Household::find($dupe->id));
    }

    public function test_representative_cycle_throws_and_rolls_back(): void
    {
        // Setup a chain that would loop after re-point:
        //   keeper -> X -> dupe (currently)
        // After merge: keeper -> X -> keeper (cycle on X)
        $keeper = $this->makeHousehold('Keep');
        $x      = $this->makeHousehold('X', null, ['representative_household_id' => $keeper->id]);
        $dupe   = $this->makeHousehold('Dupe');
        // Manually build the loop seed: keeper currently rep'd by dupe
        // (which would self-loop on merge unless detected).
        $keeper->update(['representative_household_id' => $dupe->id]);
        // X is rep'd by keeper. Dupe is rep'd by no one. So when dupe is
        // merged and any Y rep'd by dupe gets repointed to keeper, no
        // immediate cycle. But: keeper rep'd by dupe → after merge we
        // null keeper.rep_id (handled). The actual cycle test: introduce
        // a Y rep'd by dupe such that Y -> keeper -> X -> Y.
        $y = $this->makeHousehold('Y', null, ['representative_household_id' => $dupe->id]);
        // Now: x.rep_id=keeper, keeper.rep_id=dupe, y.rep_id=dupe.
        // After merge: y.rep_id=keeper (re-pointed), keeper.rep_id=null
        // (self-loop guard), x.rep_id=keeper (unchanged). No cycle.
        // To force a cycle, set x.rep_id=y so the chain is:
        //   x -> y -> dupe (will become keeper) -> ??
        $x->update(['representative_household_id' => $y->id]);
        // Now: x -> y -> dupe; keeper -> dupe; y -> dupe.
        // After merge: x -> y, y -> keeper, keeper.rep_id=null. Still no
        // cycle. The cycle requires keeper.rep_id to land somewhere
        // downstream — manually introduce: let keeper -> x.
        $keeper->update(['representative_household_id' => $x->id]);
        // Now: x -> y, y -> dupe, keeper -> x.
        // After merge: y -> keeper, x -> y, keeper -> x. CYCLE: keeper -> x -> y -> keeper.
        try {
            $this->service->merge($keeper, $dupe);
            $this->fail('Expected HouseholdMergeConflictException(representative_cycle)');
        } catch (HouseholdMergeConflictException $e) {
            $this->assertSame('representative_cycle', $e->conflictType);
            $this->assertNotEmpty($e->conflictingIds);
        }

        // Rollback verification — duplicate row still exists.
        $this->assertNotNull(Household::find($dupe->id));
    }

    public function test_self_merge_is_refused_at_the_service_layer(): void
    {
        $hh = $this->makeHousehold('Same');
        $this->expectException(\InvalidArgumentException::class);
        $this->service->merge($hh, $hh);
    }

    public function test_checkin_overrides_fk_and_json_are_rewritten(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $other  = $this->makeHousehold('Other');
        $event  = $this->makeEvent();

        $override = CheckInOverride::create([
            'user_id'                     => $this->admin->id,
            'event_id'                    => $event->id,
            'representative_household_id' => $dupe->id,
            'household_ids'               => [$dupe->id, $other->id],
            'prior_visit_ids'             => [],
            'reason'                      => 'pretend',
        ]);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['overrides_transferred']);
        $this->assertSame(1, $result['override_json_rewrites']);
        $override->refresh();
        $this->assertSame($keeper->id, $override->representative_household_id);
        $this->assertEqualsCanonicalizing([$keeper->id, $other->id], $override->household_ids);
    }

    public function test_checkin_overrides_json_dedups_when_keeper_already_in_array(): void
    {
        $keeper = $this->makeHousehold('Keep');
        $dupe   = $this->makeHousehold('Dupe');
        $event  = $this->makeEvent();

        $override = CheckInOverride::create([
            'user_id'                     => $this->admin->id,
            'event_id'                    => $event->id,
            'representative_household_id' => $keeper->id,
            // Both ids in the same row — dedup must happen post-rewrite.
            'household_ids'               => [$keeper->id, $dupe->id],
            'prior_visit_ids'             => [],
            'reason'                      => 'pretend',
        ]);

        $this->service->merge($keeper, $dupe);

        $override->refresh();
        $this->assertSame([$keeper->id], $override->household_ids);
    }

    // ─── HTTP layer ─────────────────────────────────────────────────────────

    public function test_unauthenticated_merge_redirects_to_login(): void
    {
        $keeper = $this->makeHousehold('K');
        $dupe   = $this->makeHousehold('D');

        $this->post(route('households.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertRedirect('/login');
    }

    public function test_viewer_cannot_merge(): void
    {
        $keeper = $this->makeHousehold('K');
        $dupe   = $this->makeHousehold('D');

        $this->actingAs($this->viewer)
             ->post(route('households.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertForbidden();

        $this->assertNotNull(Household::find($dupe->id));
    }

    public function test_admin_can_merge_via_http(): void
    {
        $keeper = $this->makeHousehold('K');
        $dupe   = $this->makeHousehold('D');

        $this->actingAs($this->admin)
             ->post(route('households.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertRedirect(route('households.show', $keeper))
             ->assertSessionHas('success');

        $this->assertNull(Household::find($dupe->id));
    }

    public function test_self_merge_is_refused_at_the_validator(): void
    {
        $hh = $this->makeHousehold('Self');

        $this->actingAs($this->admin)
             ->from(route('households.show', $hh))
             ->post(route('households.merge', $hh), ['keeper_id' => $hh->id])
             ->assertSessionHasErrors('keeper_id');

        $this->assertNotNull(Household::find($hh->id));
    }

    public function test_open_visit_conflict_renders_friendly_error_via_http(): void
    {
        $keeper = $this->makeHousehold('K');
        $dupe   = $this->makeHousehold('D');
        $event  = $this->makeEvent();
        $this->makeVisit($event, $keeper, 'checked_in');
        $this->makeVisit($event, $dupe, 'queued');

        $this->actingAs($this->admin)
             ->from(route('households.show', $dupe))
             ->post(route('households.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertRedirect(route('households.show', $dupe))
             ->assertSessionHas('error');

        $this->assertNotNull(Household::find($dupe->id));
    }
}
