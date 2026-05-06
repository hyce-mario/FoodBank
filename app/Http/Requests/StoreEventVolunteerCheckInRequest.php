<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Volunteer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin-side check-in for a single volunteer at a specific event.
 * Used by the "Check In" button on the Pre-Assigned / Not Yet Checked In
 * list inside the Event details page volunteers tab. Distinct from the
 * public PublicVolunteerCheckInController, which uses different validation
 * (CAPTCHA-friendly, no admin authority).
 */
class StoreEventVolunteerCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on volunteers.edit. Pre-Tier-2 was just "logged in"
        // which let any authenticated user check in volunteers and inflate
        // their hours_served — a payroll/grants-reporting input.
        return (bool) $this->user()?->hasPermission('volunteers.edit');
    }

    public function rules(): array
    {
        // Bound the check-in timestamp on both sides:
        //   - earliest = midnight on the day BEFORE the event date.
        //     A typo like 2025-05-04 instead of 2026-05-04 would otherwise
        //     compute hundreds of fictitious hours of service.
        //   - latest = now + 1 hour, to absorb client / server clock skew
        //     without letting an admin schedule a future check-in.
        // Falls back to "any date" when the event isn't bound (won't happen
        // through the route, but defends test paths that instantiate the
        // request directly).
        $event = $this->route('event');
        $bounds = ($event instanceof Event)
            ? [
                'after_or_equal:'  . $event->date->copy()->subDay()->startOfDay()->toDateTimeString(),
                'before_or_equal:' . now()->addHour()->toDateTimeString(),
            ]
            : [];

        return [
            // Any registered volunteer — the admin may legitimately check in
            // a volunteer who wasn't pre-assigned (walk-in). The controller
            // refuses if they're already checked in for THIS event.
            'volunteer_id'   => ['required', 'integer', 'exists:volunteers,id'],

            // Time of check-in. Defaults to now() in the controller if absent.
            'checked_in_at'  => ['nullable', 'date', ...$bounds],

            // Optional role override; falls back to the volunteer's default.
            'role'           => ['nullable', 'string', Rule::in(Volunteer::ROLES)],

            // Pre-assigned by default (admin is acting from the assigned list);
            // walk_in is a valid choice for ad-hoc check-ins.
            'source'         => ['nullable', Rule::in(['pre_assigned', 'walk_in', 'new_volunteer'])],

            'is_first_timer' => ['nullable', 'boolean'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'checked_in_at.after_or_equal'  => 'Check-in time is too far before the event date — likely a typo.',
            'checked_in_at.before_or_equal' => 'Check-in time can\'t be more than an hour in the future.',
        ];
    }
}
