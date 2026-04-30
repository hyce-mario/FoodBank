<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id'          => ['required', 'exists:events,id'],
            'household_id'      => ['required', 'exists:households,id'],
            'lane'              => ['required', 'integer', 'min:1'],
            'represented_ids'   => ['nullable', 'array'],
            'represented_ids.*' => ['integer', 'exists:households,id'],
            // Phase 1.3 supervisor override: when the re-check-in policy is
            // 'override' and a household has a prior exited visit at this
            // event, the staff member can resubmit with force=1 + a reason.
            // The reason is logged via Log::warning('checkin.override', …)
            // (Phase 4 will move this to formal audit_logs).
            'force'             => ['nullable', 'boolean'],
            'override_reason'   => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $eventId = $this->input('event_id');
            $lane    = (int) $this->input('lane');

            if ($eventId && $lane) {
                $event = Event::find($eventId);
                if ($event && $lane > $event->lanes) {
                    $v->errors()->add(
                        'lane',
                        "Lane {$lane} exceeds this event's lane count ({$event->lanes})."
                    );
                }
            }

            // force=1 without a non-empty reason defeats the audit's whole
            // point. Enforce in the after-hook so we can use boolean()
            // semantics (handles "1", "true", true, "on", "yes" uniformly)
            // instead of declaring `required_if:force,1` which only matches
            // the literal string "1". Note: $this->boolean() does NOT trim
            // whitespace, so " 1" or " true" would silently fall back to
            // policy enforcement (no override attempted) — the user sees a
            // normal "supervisor override required" 422, not a hidden bypass.
            if ($this->boolean('force')) {
                $reason = trim((string) $this->input('override_reason', ''));
                if ($reason === '') {
                    $v->errors()->add(
                        'override_reason',
                        'A reason is required when overriding a re-check-in block.'
                    );
                }
            }
        });
    }
}
