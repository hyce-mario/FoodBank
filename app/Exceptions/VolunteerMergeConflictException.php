<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase 5.8 — refuses a merge when the keeper and the duplicate both
 * have OPEN check-ins for the same event. Resolving silently (e.g. by
 * auto-closing one or the other) would corrupt hours_served on whichever
 * row got closed at the merge timestamp instead of the original
 * checkout. Refusing forces an admin to close the open rows manually
 * first, preserving accurate session boundaries.
 *
 * Carries the conflicting event IDs so the controller can render
 * specific copy ("both open at 'June 5 Distribution' and 2 others").
 */
class VolunteerMergeConflictException extends RuntimeException
{
    public function __construct(
        public readonly array $conflictingEventIds,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? 'Both volunteers have open check-ins for the same event(s); close them before merging.'
        );
    }
}
