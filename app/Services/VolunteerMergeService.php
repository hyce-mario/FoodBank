<?php

namespace App\Services;

use App\Exceptions\VolunteerMergeConflictException;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Phase 5.8 — merges two Volunteer records into one.
 *
 * The DB-level dedup (5.6.g UNIQUE on phone+email) and the public-form
 * dedup (5.6.h match by phone) close the source of new duplicates, but
 * legacy duplicates from before those fixes are still in the data.
 * This service drains that backlog: pick a keeper + duplicate, transfer
 * everything attached to the duplicate over to the keeper atomically,
 * then delete the duplicate row.
 *
 * What moves:
 *   - volunteer_group_memberships (skip-if-keeper-already-in)
 *   - event_volunteer (assigned events; skip-if-already)
 *   - volunteer_check_ins (re-pointed via UPDATE; refused if both have
 *     OPEN rows for the same event — see VolunteerMergeConflictException)
 *
 * What does NOT move:
 *   - The duplicate's own row (deleted at the end of the transaction)
 *   - The duplicate's contact info (phone/email/name) — keeper keeps its
 *     own. Admin should reconcile manually if the duplicate had better
 *     info; the merge UI surfaces both rows side-by-side so this is a
 *     conscious choice.
 */
class VolunteerMergeService
{
    /**
     * Merge the duplicate into the keeper. Returns a summary of what
     * was transferred for the caller's flash message.
     *
     * @return array{check_ins_transferred:int, groups_transferred:int, events_transferred:int, merged_volunteer_name:string, keeper_id:int}
     *
     * @throws InvalidArgumentException     when the same volunteer is passed for both
     * @throws VolunteerMergeConflictException when both have OPEN check-ins for the same event
     */
    public function merge(Volunteer $keeper, Volunteer $duplicate): array
    {
        if ($keeper->id === $duplicate->id) {
            throw new InvalidArgumentException('Cannot merge a volunteer with themselves.');
        }

        return DB::transaction(function () use ($keeper, $duplicate) {
            // 1. Conflict check — both have OPEN check-ins for the same
            // event. We refuse this case; admin must close one side
            // first. Done inside the transaction with the rows locked
            // so a concurrent check-in can't slip in between the check
            // and the UPDATE in step 4.
            $conflicts = VolunteerCheckIn::where('volunteer_id', $keeper->id)
                ->whereNull('checked_out_at')
                ->whereIn('event_id', function ($q) use ($duplicate) {
                    $q->from('volunteer_check_ins')
                      ->select('event_id')
                      ->where('volunteer_id', $duplicate->id)
                      ->whereNull('checked_out_at');
                })
                ->lockForUpdate()
                ->pluck('event_id')
                ->all();

            if (! empty($conflicts)) {
                throw new VolunteerMergeConflictException($conflicts);
            }

            // 2. Group memberships — attach to keeper (skip those already
            // in) then detach all from duplicate. Avoids the pivot
            // UNIQUE(volunteer_id, group_id) violation that a naive
            // UPDATE volunteer_id=keeper would trigger on overlap.
            $duplicateGroupIds = $duplicate->groups()->pluck('volunteer_groups.id')->all();
            $keeperGroupIds    = $keeper->groups()->pluck('volunteer_groups.id')->all();
            $newForKeeperGroups = array_values(array_diff($duplicateGroupIds, $keeperGroupIds));

            if ($newForKeeperGroups) {
                $payload = [];
                foreach ($newForKeeperGroups as $gid) {
                    $payload[$gid] = ['joined_at' => now()];
                }
                $keeper->groups()->attach($payload);
            }
            $duplicate->groups()->detach();

            // 3. Event assignments — same skip-if-exists pattern via
            // attach with array of IDs. event_volunteer also has UNIQUE
            // (event_id, volunteer_id), so we must dedup before attach.
            $duplicateEventIds  = $duplicate->assignedEvents()->pluck('events.id')->all();
            $keeperEventIds     = $keeper->assignedEvents()->pluck('events.id')->all();
            $newForKeeperEvents = array_values(array_diff($duplicateEventIds, $keeperEventIds));

            if ($newForKeeperEvents) {
                $keeper->assignedEvents()->attach($newForKeeperEvents);
            }
            $duplicate->assignedEvents()->detach();

            // 4. Re-route check-in rows. After 5.6.b the (event_id,
            // volunteer_id) pair is no longer UNIQUE on this table, so
            // a bulk UPDATE is safe. Step 1 already established no two
            // OPEN rows will collide on the at-most-one-open invariant.
            $checkInsTransferred = VolunteerCheckIn::where('volunteer_id', $duplicate->id)
                ->update(['volunteer_id' => $keeper->id]);

            // 5. Delete the duplicate. By now no FK-bearing row points
            // at it (5.6.f's restrict FK on volunteer_check_ins.
            // volunteer_id is satisfied because step 4 re-pointed
            // them all), and pivot rows were detached.
            $duplicateName = $duplicate->full_name;
            $duplicate->delete();

            Log::info('volunteer.merged', [
                'keeper_id'             => $keeper->id,
                'duplicate_id'          => $duplicate->id,
                'duplicate_name'        => $duplicateName,
                'check_ins_transferred' => $checkInsTransferred,
                'groups_transferred'    => count($newForKeeperGroups),
                'events_transferred'    => count($newForKeeperEvents),
            ]);

            return [
                'keeper_id'             => $keeper->id,
                'merged_volunteer_name' => $duplicateName,
                'check_ins_transferred' => $checkInsTransferred,
                'groups_transferred'    => count($newForKeeperGroups),
                'events_transferred'    => count($newForKeeperEvents),
            ];
        });
    }
}
