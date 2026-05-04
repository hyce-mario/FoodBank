<?php

namespace App\Http\Controllers;

use App\Exceptions\VolunteerCheckedInRecentlyException;
use App\Models\Event;
use App\Models\Volunteer;
use App\Services\VolunteerCheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicVolunteerCheckInController extends Controller
{
    public function __construct(
        protected VolunteerCheckInService $service,
    ) {}

    // ─── Resolve the active event (status = current) ──────────────────────────

    private function activeEvent(): ?Event
    {
        return Event::current()->first();
    }

    // ─── Show public check-in page ────────────────────────────────────────────

    public function index(): View
    {
        $event = $this->activeEvent();

        if ($event) {
            $event->loadMissing('assignedVolunteers', 'volunteerCheckIns');
            $checkedInCount   = $event->volunteerCheckIns->count();
            $preAssignedCount = $event->assignedVolunteers->count();
        } else {
            $checkedInCount   = 0;
            $preAssignedCount = 0;
        }

        return view('volunteer-checkin.index', compact(
            'event',
            'checkedInCount',
            'preAssignedCount',
        ));
    }

    // ─── AJAX search ─────────────────────────────────────────────────────────

    /**
     * Phase 5.6.e — phone-only lookup. The query is the phone number the
     * user typed; service does an exact match against volunteers.phone
     * (UNIQUE per 5.6.g) and the response strips PII (no phone or email
     * echoed back). Empty result for empty / no-match queries — same
     * shape as before so the frontend doesn't need a special case.
     */
    public function search(Request $request): JsonResponse
    {
        $event = $this->activeEvent();

        if (! $event) {
            return response()->json(['results' => []]);
        }

        $request->validate([
            // Tightened from the old name/phone/email max:100 — phone-shaped
            // input only. Permissive character set so users can type
            // formatted numbers ("(555) 123-4567") if they want, even
            // though the lookup is by exact stored value.
            'q' => ['nullable', 'string', 'max:30'],
        ]);

        $event->loadMissing('assignedVolunteers', 'volunteerCheckIns');

        $results = $this->service->search($event, (string) $request->input('q', ''));

        return response()->json(['results' => $results->values()]);
    }

    // ─── Check in existing volunteer (AJAX) ──────────────────────────────────

    public function checkIn(Request $request): JsonResponse
    {
        $event = $this->activeEvent();

        if (! $event) {
            return response()->json(['ok' => false, 'message' => 'No active event right now.'], 422);
        }

        $request->validate([
            'volunteer_id' => ['required', 'integer', 'exists:volunteers,id'],
        ]);

        $event->loadMissing('assignedVolunteers', 'volunteerCheckIns');

        $volunteer = Volunteer::findOrFail($request->volunteer_id);

        // Phase 5.6.b made multiple rows-per-(event, volunteer) legal (one
        // per session), so this pre-check must filter to OPEN rows only —
        // a CLOSED row from an earlier session is a legitimate
        // re-check-in candidate, not a duplicate.
        $alreadyIn = $event->volunteerCheckIns
            ->where('volunteer_id', $volunteer->id)
            ->whereNull('checked_out_at')
            ->isNotEmpty();

        if ($alreadyIn) {
            return response()->json(['ok' => false, 'message' => "{$volunteer->full_name} is already checked in."], 422);
        }

        try {
            $checkIn = $this->service->checkIn($event, $volunteer);
        } catch (VolunteerCheckedInRecentlyException $e) {
            // Phase 5.6.j Mode B — staff-language details stay in the
            // exception; controller writes its own public-facing copy.
            $minutes = (int) ceil($e->secondsRemaining / 60);
            return response()->json([
                'ok'      => false,
                'message' => "You just checked out — please wait about {$minutes} minute" . ($minutes === 1 ? '' : 's') . ' before checking back in.',
            ], 422);
        }

        return response()->json([
            'ok'            => true,
            'time'          => $checkIn->checked_in_at->format('g:i A'),
            'is_first_timer'=> $checkIn->is_first_timer,
            'full_name'     => $volunteer->full_name,
            'checked_in_count' => $event->volunteerCheckIns()->count(),
        ]);
    }

    // ─── Check out (AJAX) ────────────────────────────────────────────────────

    public function checkOut(Request $request): JsonResponse
    {
        $event = $this->activeEvent();

        if (! $event) {
            return response()->json(['ok' => false, 'message' => 'No active event right now.'], 422);
        }

        $request->validate([
            'volunteer_id' => ['required', 'integer', 'exists:volunteers,id'],
        ]);

        $volunteer = Volunteer::findOrFail($request->volunteer_id);

        try {
            $checkIn = $this->service->checkOut($event, $volunteer);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['ok' => false, 'message' => "{$volunteer->full_name} is not currently checked in."], 422);
        }

        return response()->json([
            'ok'            => true,
            'checkout_time' => $checkIn->checked_out_at->format('g:i A'),
            'hours_served'  => number_format((float) $checkIn->hours_served, 1),
            'full_name'     => $volunteer->full_name,
        ]);
    }

    // ─── Create new volunteer and check in (AJAX) ────────────────────────────

    /**
     * Phase 5.6.h: phone is required and is the dedup key. If the phone
     * matches an existing volunteer, that volunteer is checked in (no
     * new row); otherwise a fresh volunteer is created and checked in.
     * Email is optional; an email collision against a *different* phone
     * surfaces as a 422 with a friendly message rather than a DB
     * integrity 500.
     */
    public function signUp(Request $request): JsonResponse
    {
        $event = $this->activeEvent();

        if (! $event) {
            return response()->json(['ok' => false, 'message' => 'No active event right now.'], 422);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            // Phone is required + the dedup key. Existing-volunteer match
            // is by literal phone equality.
            'phone'      => ['required', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:200'],
        ]);

        // Email-collision pre-check: only relevant when the phone does
        // NOT match an existing row (that path uses the existing record's
        // email regardless of what was submitted).
        $phoneMatch = \App\Models\Volunteer::where('phone', trim($data['phone']))->exists();
        if (! $phoneMatch && ! empty($data['email'])) {
            $emailTaken = \App\Models\Volunteer::where('email', $data['email'])->exists();
            if ($emailTaken) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'This email is already registered to a different phone number. Please use the original phone to check in.',
                ], 422);
            }
        }

        try {
            [
                'volunteer'   => $volunteer,
                'checkIn'     => $checkIn,
                'is_existing' => $isExisting,
            ] = $this->service->createAndCheckIn($event, $data);
        } catch (VolunteerCheckedInRecentlyException $e) {
            // Existing-phone path went through service->checkIn() and
            // hit the min-gap rail (Phase 5.6.j Mode B).
            $minutes = (int) ceil($e->secondsRemaining / 60);
            return response()->json([
                'ok'      => false,
                'message' => "This phone just checked out — please wait about {$minutes} minute" . ($minutes === 1 ? '' : 's') . ' before checking back in.',
            ], 422);
        }

        return response()->json([
            'ok'               => true,
            'time'             => $checkIn->checked_in_at->format('g:i A'),
            // Snapshot from the row, not always-true. An existing volunteer
            // who has prior service history will correctly read false here.
            'is_first_timer'   => (bool) $checkIn->is_first_timer,
            'is_existing'      => $isExisting,
            'full_name'        => $volunteer->full_name,
            'id'               => $volunteer->id,
            'checked_in_count' => $event->volunteerCheckIns()->count(),
        ]);
    }
}
