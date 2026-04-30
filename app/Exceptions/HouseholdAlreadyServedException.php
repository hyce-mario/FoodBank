<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by EventCheckInService::checkIn() when a household (or one of its
 * represented households) has previously been served at this event AND the
 * configured re-check-in policy refuses the new check-in.
 *
 * Carries enough context for the controller to render a "this family was
 * already served — override?" modal: which household IDs collided (the
 * actual offending subset of the candidate set, not all candidates), which
 * event, and whether the policy permits a supervisor override.
 *
 * Controllers MUST NOT echo {@see getMessage()} verbatim into a JSON 422
 * response sent to public clients — message strings are intended for staff
 * UI / logs and may be tightened over time. Render copy from the typed
 * fields ($eventId, $householdIds, $allowOverride) instead, resolving
 * household names via the controller's own query.
 *
 * The active-already-served case (a visit still in the queue, end_time NULL)
 * is intentionally NOT this exception — it stays a plain RuntimeException so
 * the data-integrity guard can never be reinterpreted as overrideable.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.3.
 */
class HouseholdAlreadyServedException extends RuntimeException
{
    /**
     * @param  int    $eventId        The event the conflict was detected on.
     * @param  int[]  $householdIds   IDs of households that already have an exited visit at this event.
     * @param  bool   $allowOverride  True when policy is 'override' (a force=true retry can succeed); false when 'deny'.
     */
    public function __construct(
        public readonly int $eventId,
        public readonly array $householdIds,
        public readonly bool $allowOverride,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? 'One or more households have already been served at this event.'
        );
    }
}
