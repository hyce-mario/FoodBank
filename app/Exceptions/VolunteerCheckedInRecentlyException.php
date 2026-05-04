<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase 5.6.j — multi-check-in safety rail: minimum session gap.
 *
 * Thrown by VolunteerCheckInService::checkIn() when a volunteer attempts
 * to start a new session for an event but their most-recent CLOSED row's
 * checked_out_at is more recent than the configured min session gap
 * (volunteer_checkin.volunteer_min_session_gap_minutes). Prevents the
 * accidental double-tap on the public form, and a trivial gaming
 * vector where someone cycles check-in / check-out rapidly.
 *
 * Carries the seconds-remaining-until-allowed so the controller can
 * render a precise "wait N seconds" message rather than a generic
 * "you just checked out".
 *
 * Public controllers should map this to a 422 with a friendly message
 * (do NOT echo getMessage() — staff-language). Admin controllers
 * (EventVolunteerCheckInController) bypass this rail entirely and
 * therefore won't see this exception.
 */
class VolunteerCheckedInRecentlyException extends RuntimeException
{
    public function __construct(
        public readonly int $secondsRemaining,
        public readonly int $eventId,
        public readonly int $volunteerId,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? "Volunteer {$volunteerId} checked out recently; {$secondsRemaining}s remaining before re-check-in is allowed."
        );
    }
}
