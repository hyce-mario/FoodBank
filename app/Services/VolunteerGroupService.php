<?php

namespace App\Services;

use App\Models\VolunteerGroup;
use Illuminate\Support\Facades\DB;

class VolunteerGroupService
{
    /**
     * Sync the volunteer membership list for a group.
     *
     * - Volunteers in $volunteerIds but NOT currently members → attached with joined_at = now()
     * - Volunteers currently members but NOT in $volunteerIds → detached
     * - Existing members in $volunteerIds → untouched (joined_at preserved)
     *
     * Wrapped in a transaction to keep membership state consistent.
     */
    public function syncMembers(VolunteerGroup $group, array $volunteerIds): void
    {
        DB::transaction(function () use ($group, $volunteerIds) {
            $existing = $group->volunteers()->pluck('volunteers.id')->toArray();

            $toAdd    = array_diff($volunteerIds, $existing);
            $toRemove = array_diff($existing, $volunteerIds);

            if ($toRemove) {
                $group->volunteers()->detach($toRemove);
            }

            if ($toAdd) {
                $pivotData = collect($toAdd)->mapWithKeys(
                    fn ($id) => [$id => ['joined_at' => now()]]
                )->all();

                $group->volunteers()->attach($pivotData);
            }
        });
    }
}
