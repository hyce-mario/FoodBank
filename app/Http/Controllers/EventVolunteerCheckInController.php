<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventVolunteerCheckInRequest;
use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side volunteer check-in / checkout actions scoped to a single event.
 * Lives separately from PublicVolunteerCheckInController so the public form
 * (no auth, throttled, narrow contract) can't accidentally inherit admin
 * powers like back-dating times or bulk operations.
 */
class EventVolunteerCheckInController extends Controller
{
    // ─── Single check-in ──────────────────────────────────────────────────────

    public function store(StoreEventVolunteerCheckInRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();

        $volunteer = Volunteer::findOrFail($data['volunteer_id']);

        // Refuse a duplicate check-in. The same volunteer should never have
        // two simultaneous open rows for the same event.
        $existing = VolunteerCheckIn::where('event_id', $event->id)
            ->where('volunteer_id', $volunteer->id)
            ->whereNull('checked_out_at')
            ->exists();

        if ($existing) {
            return back()
                ->with('open_tab', 'volunteers')
                ->with('error', "{$volunteer->full_name} is already checked in to this event.");
        }

        VolunteerCheckIn::create([
            'event_id'       => $event->id,
            'volunteer_id'   => $volunteer->id,
            'role'           => $data['role']           ?? $volunteer->role,
            'source'         => $data['source']         ?? 'pre_assigned',
            'is_first_timer' => (bool) ($data['is_first_timer'] ?? false),
            'checked_in_at'  => isset($data['checked_in_at']) && $data['checked_in_at']
                ? Carbon::parse($data['checked_in_at'])
                : now(),
            'notes'          => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('events.show', $event)
            ->with('open_tab', 'volunteers')
            ->with('success', "{$volunteer->full_name} has been checked in.");
    }

    // ─── Bulk check-in (every assigned volunteer not already checked in) ────

    public function bulkStore(Request $request, Event $event): RedirectResponse
    {
        // Snapshot the IDs of volunteers already checked in so we don't
        // double-create rows for them. We deliberately re-evaluate inside
        // the transaction (after lockForUpdate) to defend against a
        // concurrent admin clicking the same button.
        $event->loadMissing('assignedVolunteers');
        $assigned = $event->assignedVolunteers;

        if ($assigned->isEmpty()) {
            return back()
                ->with('open_tab', 'volunteers')
                ->with('error', 'No assigned volunteers to check in.');
        }

        $checkedIn = 0;

        DB::transaction(function () use ($event, $assigned, &$checkedIn) {
            $alreadyIn = VolunteerCheckIn::where('event_id', $event->id)
                ->whereNull('checked_out_at')
                ->lockForUpdate()
                ->pluck('volunteer_id')
                ->flip();

            $now = now();
            $rows = [];
            foreach ($assigned as $volunteer) {
                if ($alreadyIn->has($volunteer->id)) {
                    continue;
                }
                $rows[] = [
                    'event_id'       => $event->id,
                    'volunteer_id'   => $volunteer->id,
                    'role'           => $volunteer->role,
                    'source'         => 'pre_assigned',
                    'is_first_timer' => false,
                    'checked_in_at'  => $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            if (! empty($rows)) {
                VolunteerCheckIn::insert($rows);
                $checkedIn = count($rows);
            }
        });

        $msg = $checkedIn === 0
            ? 'All assigned volunteers were already checked in.'
            : "Checked in {$checkedIn} " . ($checkedIn === 1 ? 'volunteer' : 'volunteers') . '.';

        return redirect()
            ->route('events.show', $event)
            ->with('open_tab', 'volunteers')
            ->with('success', $msg);
    }

    // ─── Bulk check-out (close every open check-in for this event) ─────────

    public function bulkCheckout(Event $event): RedirectResponse
    {
        $now = now();
        $closed = 0;

        DB::transaction(function () use ($event, $now, &$closed) {
            // Pull every open row for this event, lockForUpdate so a
            // concurrent admin click can't race the same rows. We loop in
            // PHP rather than issuing one UPDATE because hours_served is
            // computed per-row from each volunteer's individual check_in
            // time — a single UPDATE would need a DB-specific time-diff
            // expression and lose portability.
            $open = VolunteerCheckIn::where('event_id', $event->id)
                ->whereNull('checked_out_at')
                ->lockForUpdate()
                ->get();

            foreach ($open as $ci) {
                if (! $ci->checked_in_at) continue; // defensive — bad data
                $hours = round($ci->checked_in_at->diffInMinutes($now) / 60, 2);
                $ci->update([
                    'checked_out_at' => $now,
                    'hours_served'   => $hours,
                ]);
                $closed++;
            }
        });

        $msg = $closed === 0
            ? 'No active volunteer check-ins to close.'
            : "Checked out {$closed} " . ($closed === 1 ? 'volunteer' : 'volunteers') . '.';

        return redirect()
            ->route('events.show', $event)
            ->with('open_tab', 'volunteers')
            ->with('success', $msg);
    }

    // ─── Check out (close an existing check-in) ─────────────────────────────

    public function checkout(Request $request, Event $event, VolunteerCheckIn $checkIn): RedirectResponse
    {
        // Defensive: the route binds VolunteerCheckIn by id; refuse any cross-
        // event tampering by confirming the check-in actually belongs here.
        abort_if($checkIn->event_id !== $event->id, 404);
        abort_if($checkIn->checked_out_at !== null, 422, 'Already checked out.');

        $request->validate([
            'checked_out_at' => ['nullable', 'date', 'after_or_equal:' . $checkIn->checked_in_at?->toDateTimeString()],
        ]);

        $checkedOutAt = $request->input('checked_out_at')
            ? Carbon::parse($request->input('checked_out_at'))
            : now();

        // hours_served stored as DECIMAL(5,2). diffInMinutes / 60 keeps it
        // accurate to ~1-second granularity without floating-point surprises.
        $hours = round($checkIn->checked_in_at->diffInMinutes($checkedOutAt) / 60, 2);

        $checkIn->update([
            'checked_out_at' => $checkedOutAt,
            'hours_served'   => $hours,
        ]);

        return redirect()
            ->route('events.show', $event)
            ->with('open_tab', 'volunteers')
            ->with('success', "{$checkIn->volunteer->full_name} has been checked out (" . $hours . ' hrs).');
    }
}
