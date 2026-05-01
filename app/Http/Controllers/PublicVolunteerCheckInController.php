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

    public function signUp(Request $request): JsonResponse
    {
        $event = $this->activeEvent();

        if (! $event) {
            return response()->json(['ok' => false, 'message' => 'No active event right now.'], 422);
        }

        $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:200'],
        ]);

        ['volunteer' => $volunteer, 'checkIn' => $checkIn] = $this->service->createAndCheckIn(
            $event,
            $request->only(['first_name', 'last_name', 'phone', 'email']),
        );

        return response()->json([
            'ok'            => true,
            'time'          => $checkIn->checked_in_at->format('g:i A'),
            'is_first_timer'=> true,
            'full_name'     => $volunteer->full_name,
            'id'            => $volunteer->id,
            'checked_in_count' => $event->volunteerCheckIns()->count(),
        ]);
    }
}
