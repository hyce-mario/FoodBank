<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase 6.5.d — refuses a household merge when it cannot complete safely:
 *
 *   - 'open_visit'           — both households have an active (non-exited)
 *                              visit at the same event. Auto-resolving would
 *                              corrupt queue position / loader state, so the
 *                              admin must close one side first.
 *   - 'representative_cycle' — re-pointing the duplicate's represented
 *                              households at the keeper would close a loop
 *                              in the representative chain (Phase 6.3
 *                              cycle invariant).
 *
 * The pre-registration "both have a confirmed pre-reg for the same event"
 * case is handled INSIDE the service by auto-cancelling the duplicate's
 * pre-reg (match_status='cancelled', household_id=null) and is not surfaced
 * as a conflict — the merge proceeds.
 *
 * Carries the conflicting IDs so the controller can render specific copy.
 * For 'open_visit' these are event IDs; for 'representative_cycle' they are
 * household IDs (the represented households whose chain would loop).
 */
class HouseholdMergeConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $conflictType,
        public readonly array $conflictingIds,
        ?string $message = null,
    ) {
        parent::__construct($message ?? match ($conflictType) {
            'open_visit' => 'Both households have an active visit at the same event(s); close one before merging.',
            'representative_cycle' => 'Merging would create a circular link in the representative chain.',
            default => 'Household merge cannot complete because of a data conflict.',
        });
    }
}
