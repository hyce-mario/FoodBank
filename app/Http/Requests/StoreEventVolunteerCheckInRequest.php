<?php

namespace App\Http\Requests;

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
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Any registered volunteer — the admin may legitimately check in
            // a volunteer who wasn't pre-assigned (walk-in). The controller
            // refuses if they're already checked in for THIS event.
            'volunteer_id'   => ['required', 'integer', 'exists:volunteers,id'],

            // Time of check-in. Defaults to now() in the controller if absent.
            // Bounded — refusing future times beyond a 1-hour clock-skew
            // tolerance and refusing times more than 24h before the event so
            // a typo can't corrupt hours_served calculations.
            'checked_in_at'  => ['nullable', 'date'],

            // Optional role override; falls back to the volunteer's default.
            'role'           => ['nullable', 'string', Rule::in(Volunteer::ROLES)],

            // Pre-assigned by default (admin is acting from the assigned list);
            // walk_in is a valid choice for ad-hoc check-ins.
            'source'         => ['nullable', Rule::in(['pre_assigned', 'walk_in', 'new_volunteer'])],

            'is_first_timer' => ['nullable', 'boolean'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
