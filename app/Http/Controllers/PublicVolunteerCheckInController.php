<?php

namespace App\Http\Controllers;

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

    public function search(Request $request): JsonResponse
    {
        $event = $this->activeEvent();

        if (! $event) {
            return response()->json(['results' => []]);
        }

        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
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
        $alreadyIn = $event->volunteerCheckIns
            ->where('volunteer_id', $volunteer->id)
            ->isNotEmpty();

        if ($alreadyIn) {
            return response()->json(['ok' => false, 'message' => "{$volunteer->full_name} is already checked in."], 422);
        }

        $checkIn = $this->service->checkIn($event, $volunteer);

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

        [
            'volunteer'   => $volunteer,
            'checkIn'     => $checkIn,
            'is_existing' => $isExisting,
        ] = $this->service->createAndCheckIn($event, $data);

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
