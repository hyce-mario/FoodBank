<?php

namespace App\Observers;

use App\Models\Household;
use App\Models\Visit;

/**
 * Phase 6.7 — keeps the cached households.events_attended_count column
 * in sync when a Visit is deleted (rare, admin-only). The increment side
 * lives in EventCheckInService::checkIn (atomic with the attach).
 */
class VisitObserver
{
    public function deleting(Visit $visit): void
    {
        // Capture household ids while the pivot still exists.
        $householdIds = $visit->households()->pluck('households.id');

        if ($householdIds->isEmpty()) {
            return;
        }

        // Decrement, clamping at zero so we can never produce a negative count
        // even if a stale row sneaks through.
        Household::whereIn('id', $householdIds)
            ->where('events_attended_count', '>', 0)
            ->decrement('events_attended_count');
    }
}
