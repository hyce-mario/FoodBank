<?php

namespace App\Services;

use App\Exceptions\HouseholdMergeConflictException;
use App\Models\CheckInOverride;
use App\Models\EventPreRegistration;
use App\Models\Household;
use App\Models\Pledge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Phase 6.5.d — merges two Household records into one.
 *
 * Phase 6.5.c added fuzzy duplicate detection at create time, but it does
 * not reach back through legacy duplicates already in the data. This service
 * drains that backlog: pick a keeper + duplicate, transfer everything
 * attached to the duplicate over to the keeper atomically, then delete the
 * duplicate row.
 *
 * Models the same shape as VolunteerMergeService (Phase 5.8). Households
 * differ in three ways that make this slightly heavier:
 *
 *   1. They have more incoming FKs — visits (pivot), pre-registrations
 *      (twice — confirmed + potential), pledges, check-in overrides
 *      (FK + JSON column), and a self-FK for represented households.
 *   2. The visit-pivot UNIQUE(visit_id, household_id) means same-visit
 *      collisions need to be silently deduped before the bulk UPDATE
 *      (correct semantics — both rows attended that visit as one entity).
 *   3. The denormalized `events_attended_count` cache (Phase 6.7) must be
 *      recomputed on the keeper because the visit set has changed.
 *
 * Conflict handling:
 *   - Open visit at the same event: refused via HouseholdMergeConflictException
 *     ('open_visit'). Auto-closing would corrupt queue state.
 *   - Confirmed pre-registration at the same event: AUTO-CANCELLED (the
 *     duplicate's pre-reg gets match_status='cancelled' and household_id=null).
 *     This is more forgiving than the volunteer-merge precedent because a
 *     stale pre-reg is benign — it'll just no longer count toward the event
 *     forecast. The keeper's pre-reg becomes the survivor automatically.
 *   - Representative-chain cycle: refused via 'representative_cycle'. Same
 *     pre-existing-cycle guard as HouseholdService::attach (Phase 6.3).
 *
 * What does NOT move:
 *   - The duplicate's contact info / demographics / vehicle. Keeper keeps
 *     its own. Admin should reconcile manually if the duplicate had better
 *     info; the merge UI surfaces both rows side-by-side so this is a
 *     conscious choice.
 *   - The duplicate's audit_logs rows. Historical; leaving them preserves
 *     the trail of "what happened to row #X". Same precedent as volunteer
 *     merge.
 *   - The duplicate's row itself (deleted at the end of the transaction;
 *     Auditable trait emits the delete row).
 */
class HouseholdMergeService
{
    /**
     * Merge the duplicate into the keeper. Returns a summary of what was
     * transferred for the caller's flash message.
     *
     * @return array{
     *     keeper_id:int,
     *     merged_household_name:string,
     *     merged_household_number:string,
     *     visits_transferred:int,
     *     visit_pivot_dedups:int,
     *     pre_regs_transferred:int,
     *     pre_regs_cancelled:int,
     *     potential_pre_regs_transferred:int,
     *     pledges_transferred:int,
     *     represented_transferred:int,
     *     overrides_transferred:int,
     *     override_json_rewrites:int
     * }
     *
     * @throws InvalidArgumentException             when the same household is passed for both
     * @throws HouseholdMergeConflictException      when an unsafe collision is detected
     */
    public function merge(Household $keeper, Household $duplicate): array
    {
        if ($keeper->id === $duplicate->id) {
            throw new InvalidArgumentException('Cannot merge a household with itself.');
        }

        return DB::transaction(function () use ($keeper, $duplicate) {
            // ── Step 0: Lock both household rows in deterministic ID order.
            // Prevents deadlocks if two admins click Merge concurrently with
            // the same pair in opposite roles (A merges B into A while B
            // merges A into B). Lower ID first, always.
            [$lowId, $highId] = $keeper->id < $duplicate->id
                ? [$keeper->id, $duplicate->id]
                : [$duplicate->id, $keeper->id];
            Household::whereIn('id', [$lowId, $highId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Re-fetch with row locks so any concurrent writer is blocked
            // until commit. The model instances passed in may be stale.
            $keeper    = Household::lockForUpdate()->findOrFail($keeper->id);
            $duplicate = Household::lockForUpdate()->findOrFail($duplicate->id);

            // ── Step 1: Conflict — both have an ACTIVE (non-exited) visit
            // at the same event. Resolving silently would corrupt queue
            // position state. Refuse.
            $activeStatuses = ['checked_in', 'queued', 'loading', 'loaded'];
            $conflictingEventIds = DB::table('visit_households as vh_keeper')
                ->join('visits as v_keeper', 'v_keeper.id', '=', 'vh_keeper.visit_id')
                ->join('visit_households as vh_dupe', function ($j) use ($duplicate) {
                    $j->on('vh_dupe.visit_id', '<>', 'vh_keeper.visit_id')
                      ->where('vh_dupe.household_id', '=', $duplicate->id);
                })
                ->join('visits as v_dupe', function ($j) {
                    $j->on('v_dupe.id', '=', 'vh_dupe.visit_id')
                      ->on('v_dupe.event_id', '=', 'v_keeper.event_id');
                })
                ->where('vh_keeper.household_id', $keeper->id)
                ->whereIn('v_keeper.visit_status', $activeStatuses)
                ->whereIn('v_dupe.visit_status', $activeStatuses)
                ->distinct()
                ->pluck('v_keeper.event_id')
                ->all();

            if (! empty($conflictingEventIds)) {
                throw new HouseholdMergeConflictException('open_visit', $conflictingEventIds);
            }

            // ── Step 2: Conflict — representative-chain cycle. Walk every
            // chain that currently passes through the duplicate, simulate
            // the re-point to keeper, and refuse if any chain returns to
            // its starting node. Mirrors HouseholdService::attach (6.3).
            $cycleOffenders = $this->detectRepresentativeCycles($keeper, $duplicate);
            if (! empty($cycleOffenders)) {
                throw new HouseholdMergeConflictException('representative_cycle', $cycleOffenders);
            }

            // ── Step 3: Auto-cancel duplicate's confirmed pre-registrations
            // that conflict with keeper's confirmed pre-registrations on
            // the same event. The keeper's row survives; the duplicate's
            // becomes a stale audit record (match_status='cancelled',
            // household_id=null) instead of moving over.
            $keeperConfirmedEventIds = EventPreRegistration::where('household_id', $keeper->id)
                ->pluck('event_id')
                ->all();

            $preRegsCancelled = 0;
            if (! empty($keeperConfirmedEventIds)) {
                $preRegsCancelled = EventPreRegistration::where('household_id', $duplicate->id)
                    ->whereIn('event_id', $keeperConfirmedEventIds)
                    ->update([
                        'match_status' => 'cancelled',
                        'household_id' => null,
                    ]);
            }

            // ── Step 4: De-dup visit_households on shared visits. UNIQUE
            // (visit_id, household_id) would block the bulk re-point on
            // any visit the keeper is already attached to. Delete the
            // duplicate's pivot row(s) for those visits — the visit
            // already counts the household via the keeper row, so dropping
            // the dupe's row is the correct semantic.
            $sharedVisitIds = DB::table('visit_households')
                ->where('household_id', $keeper->id)
                ->whereIn('visit_id', function ($q) use ($duplicate) {
                    $q->from('visit_households')
                      ->select('visit_id')
                      ->where('household_id', $duplicate->id);
                })
                ->pluck('visit_id')
                ->all();

            $visitPivotDedups = 0;
            if (! empty($sharedVisitIds)) {
                $visitPivotDedups = DB::table('visit_households')
                    ->where('household_id', $duplicate->id)
                    ->whereIn('visit_id', $sharedVisitIds)
                    ->delete();
            }

            // ── Step 5: Re-point every FK that points at duplicate.
            $visitsTransferred = DB::table('visit_households')
                ->where('household_id', $duplicate->id)
                ->update(['household_id' => $keeper->id]);

            $preRegsTransferred = EventPreRegistration::where('household_id', $duplicate->id)
                ->update(['household_id' => $keeper->id]);

            $potentialPreRegsTransferred = EventPreRegistration::where('potential_household_id', $duplicate->id)
                ->update(['potential_household_id' => $keeper->id]);

            $pledgesTransferred = Pledge::where('household_id', $duplicate->id)
                ->update(['household_id' => $keeper->id]);

            $representedTransferred = Household::where('representative_household_id', $duplicate->id)
                ->update(['representative_household_id' => $keeper->id]);

            // If the keeper was REPRESENTED by the duplicate, after step 5
            // the keeper would point at itself — break the self-loop.
            if ($keeper->representative_household_id === $duplicate->id) {
                Household::where('id', $keeper->id)
                    ->update(['representative_household_id' => null]);
            }

            // ── Step 6: checkin_overrides — both the FK column and the
            // JSON `household_ids` array. The JSON rewrite preserves audit
            // fidelity ("which household IDs triggered the override") even
            // after the dupe is gone.
            $overridesTransferred = CheckInOverride::where('representative_household_id', $duplicate->id)
                ->update(['representative_household_id' => $keeper->id]);

            $overrideJsonRewrites = $this->rewriteOverrideJson($duplicate->id, $keeper->id);

            // ── Step 7: Recompute events_attended_count on the keeper.
            // The cached counter (Phase 6.7) is now wrong because the
            // visit set has changed. COUNT(DISTINCT event_id) — same
            // formula the migration used to backfill, portable across
            // MySQL and SQLite (no JSON or driver-specific functions).
            DB::statement(<<<'SQL'
                UPDATE households
                SET events_attended_count = (
                    SELECT COUNT(DISTINCT visits.event_id)
                    FROM visit_households
                    JOIN visits ON visits.id = visit_households.visit_id
                    WHERE visit_households.household_id = households.id
                )
                WHERE id = ?
            SQL, [$keeper->id]);

            // ── Step 8: Delete the duplicate. By now no FK row points at
            // it (cascade-FKs are satisfied via re-point; nullOnDelete FKs
            // would be safe even if we didn't re-point but we did so the
            // semantic relationship is preserved). Auditable trait emits
            // the audit_logs delete row.
            $duplicateName   = $duplicate->full_name;
            $duplicateNumber = $duplicate->household_number;
            $duplicate->delete();

            Log::info('household.merged', [
                'keeper_id'                       => $keeper->id,
                'duplicate_id'                    => $duplicate->id,
                'duplicate_name'                  => $duplicateName,
                'duplicate_household_number'      => $duplicateNumber,
                'visits_transferred'              => $visitsTransferred,
                'visit_pivot_dedups'              => $visitPivotDedups,
                'pre_regs_transferred'            => $preRegsTransferred,
                'pre_regs_cancelled'              => $preRegsCancelled,
                'potential_pre_regs_transferred'  => $potentialPreRegsTransferred,
                'pledges_transferred'             => $pledgesTransferred,
                'represented_transferred'         => $representedTransferred,
                'overrides_transferred'           => $overridesTransferred,
                'override_json_rewrites'          => $overrideJsonRewrites,
            ]);

            return [
                'keeper_id'                       => $keeper->id,
                'merged_household_name'           => $duplicateName,
                'merged_household_number'         => $duplicateNumber,
                'visits_transferred'              => $visitsTransferred,
                'visit_pivot_dedups'              => $visitPivotDedups,
                'pre_regs_transferred'            => $preRegsTransferred,
                'pre_regs_cancelled'              => $preRegsCancelled,
                'potential_pre_regs_transferred'  => $potentialPreRegsTransferred,
                'pledges_transferred'             => $pledgesTransferred,
                'represented_transferred'         => $representedTransferred,
                'overrides_transferred'           => $overridesTransferred,
                'override_json_rewrites'          => $overrideJsonRewrites,
            ];
        });
    }

    /**
     * Walk every chain that passes through the duplicate, simulate the
     * re-point to keeper, and return household IDs whose chain would loop.
     * Mirrors the cycle-prevention logic in HouseholdService::attach (6.3).
     *
     * Returns an empty array when no cycle would form.
     *
     * @return int[]
     */
    private function detectRepresentativeCycles(Household $keeper, Household $duplicate): array
    {
        // Build an in-memory edge map of (household_id -> rep_id) so the
        // simulation does not run thousands of single-row queries.
        $edges = Household::whereNotNull('representative_household_id')
            ->pluck('representative_household_id', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Apply the simulated re-points: every household pointing at the
        // duplicate would now point at the keeper.
        foreach ($edges as $from => $to) {
            if ($to === (int) $duplicate->id) {
                $edges[$from] = (int) $keeper->id;
            }
        }

        // The duplicate row will be deleted; its outgoing edge is irrelevant.
        unset($edges[$duplicate->id]);

        // Self-loop guard: if the keeper was represented BY the duplicate,
        // the main flow nulls keeper.rep_id post-merge. After the re-point
        // above, edges[keeper.id] now equals keeper.id (a self-loop) — drop
        // it so the cycle walker sees the post-merge state honestly.
        if (isset($edges[$keeper->id]) && $edges[$keeper->id] === (int) $keeper->id) {
            unset($edges[$keeper->id]);
        }

        $offenders = [];
        foreach (array_keys($edges) as $start) {
            $current = $start;
            $visited = [];
            while (isset($edges[$current])) {
                if (in_array($current, $visited, true)) {
                    $offenders[] = (int) $start;
                    break;
                }
                $visited[] = $current;
                $current   = $edges[$current];
                if ($current === (int) $start) {
                    $offenders[] = (int) $start;
                    break;
                }
            }
        }

        return array_values(array_unique($offenders));
    }

    /**
     * Rewrite duplicate-id occurrences inside checkin_overrides.household_ids
     * (JSON column) to keeper-id, deduplicating any resulting duplicates so
     * the array stays a unique-set. Returns the number of rows rewritten.
     *
     * Done in PHP rather than via MySQL JSON_REPLACE / JSON_REMOVE so the
     * code path is identical on the SQLite test runner. The model casts
     * `household_ids` to `array`, so Eloquent handles the (de)serialisation.
     */
    private function rewriteOverrideJson(int $duplicateId, int $keeperId): int
    {
        $rewrites = 0;

        // Stream rows so a large overrides table doesn't blow memory.
        // Filter in PHP — JSON_CONTAINS / JSON_SEARCH are not portable to
        // SQLite for tests.
        CheckInOverride::query()
            ->lazy(500)
            ->each(function (CheckInOverride $row) use ($duplicateId, $keeperId, &$rewrites) {
                $ids = $row->household_ids ?? [];
                if (! is_array($ids) || ! in_array($duplicateId, $ids, true)) {
                    return;
                }

                // Replace dupe id with keeper id, then unique-ify so the
                // array doesn't end up with [keeper, keeper] when both
                // appeared in the same override.
                $rewritten = array_values(array_unique(array_map(
                    fn ($id) => (int) $id === $duplicateId ? $keeperId : (int) $id,
                    $ids,
                )));

                $row->household_ids = $rewritten;
                $row->save();
                $rewrites++;
            });

        return $rewrites;
    }
}
