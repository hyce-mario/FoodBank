<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VolunteerCheckInService
{
    /**
     * Search volunteers by name, phone, or email for a check-in lookup.
     * Returns a collection enriched with whether the volunteer is pre-assigned
     * to this event and whether they have already checked in.
     */
    public function search(Event $event, string $term): Collection
    {
        if (trim($term) === '') {
            return collect();
        }

        $preAssigned = $event->assignedVolunteers->pluck('id')->flip();
        $checkedIn   = $event->volunteerCheckIns->pluck('volunteer_id')->flip();

        $checkInsByVolunteer = $event->volunteerCheckIns->keyBy('volunteer_id');

        return Volunteer::search($term)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(15)
            ->get()
            ->map(function (Volunteer $v) use ($preAssigned, $checkedIn, $checkInsByVolunteer) {
                $ci = $checkInsByVolunteer->get($v->id);
                return [
                    'id'            => $v->id,
                    'full_name'     => $v->full_name,
                    'phone'         => $v->phone,
                    'email'         => $v->email,
                    'is_assigned'   => $preAssigned->has($v->id),
                    'checked_in'    => $checkedIn->has($v->id),
                    'checkin_time'  => $ci?->checked_in_at?->format('g:i A'),
                    'is_first_timer'=> $ci?->is_first_timer ?? false,
                    'checked_out'   => $ci?->checked_out_at !== null,
                    'checkout_time' => $ci?->checked_out_at?->format('g:i A'),
                    'hours_served'  => $ci?->hours_served !== null
                        ? number_format((float) $ci->hours_served, 1)
                        : null,
                ];
            });
    }

    /**
     * Check in an existing volunteer to an event.
     * Determines source (pre_assigned vs walk_in) automatically.
     * Detects first-timer status from actual service history.
     *
     * A volunteer who already has an OPEN check-in for this event will
     * have that existing row returned (idempotent re-check-in). A volunteer
     * who has only CLOSED rows (i.e. checked out earlier) gets a brand-new
     * row — the prior session's hours_served is preserved.
     *
     * Wrapped in a transaction with lockForUpdate so two concurrent admins
     * clicking "Check In" for the same volunteer can't both insert a fresh
     * row and break the at-most-one-open invariant.
     */
    public function checkIn(Event $event, Volunteer $volunteer, ?string $role = null): VolunteerCheckIn
    {
        return DB::transaction(function () use ($event, $volunteer, $role) {
            // Lock any existing rows for this (event, volunteer) so a
            // concurrent admin click can't observe "no open row" while we
            // are about to insert one. The lock is released on commit.
            $existingOpen = VolunteerCheckIn::where('event_id', $event->id)
                ->where('volunteer_id', $volunteer->id)
                ->whereNull('checked_out_at')
                ->lockForUpdate()
                ->first();

            if ($existingOpen) {
                return $existingOpen;
            }

            $isAssigned = $event->assignedVolunteers()
                ->where('volunteer_id', $volunteer->id)
                ->exists();
            $source = $isAssigned ? 'pre_assigned' : 'walk_in';

            // First-timer is computed once, at the moment of FIRST check-in,
            // and snapshotted on the row. Recomputing later would make the
            // flag drift as the volunteer accrues service history.
            $isFirstTimer = $volunteer->checkIns()->count() === 0;

            return VolunteerCheckIn::create([
                'event_id'       => $event->id,
                'volunteer_id'   => $volunteer->id,
                'role'           => $role ?? $volunteer->role,
                'source'         => $source,
                'is_first_timer' => $isFirstTimer,
                'checked_in_at'  => Carbon::now(),
            ]);
        });
    }

    /**
     * Public-facing signup: dedup by phone, then check in.
     *
     * If the submitted phone matches an existing volunteer, that volunteer
     * is checked in (no new row created — preserves their accumulated
     * service history + identity). Otherwise a brand-new volunteer is
     * created and checked in as 'new_volunteer'.
     *
     * Phone is the dedup key per Phase 5.6.h. The submitted name and
     * email are IGNORED on phone-match — public form input cannot be
     * trusted to update an existing record without admin auth (audit
     * concern). Admin can still edit the volunteer via the admin UI.
     *
     * Returns:
     *   [
     *     'volunteer'   => Volunteer,
     *     'checkIn'     => VolunteerCheckIn,
     *     'is_existing' => bool,   // true when phone matched an existing row
     *   ]
     */
    public function createAndCheckIn(Event $event, array $data): array
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $existing = $phone !== ''
            ? Volunteer::where('phone', $phone)->first()
            : null;

        if ($existing) {
            // checkIn() is idempotent: if the volunteer already has an
            // open row for this event, it's returned as-is (no double
            // check-in). Source flips from 'new_volunteer' (the public
            // signup default) to 'walk_in' or 'pre_assigned' depending
            // on whether they were on the assignment list.
            $checkIn = $this->checkIn($event, $existing);

            return [
                'volunteer'   => $existing,
                'checkIn'     => $checkIn,
                'is_existing' => true,
            ];
        }

        $volunteer = Volunteer::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'phone'      => $phone !== '' ? $phone : null,
            'email'      => ! empty($data['email']) ? $data['email'] : null,
            'role'       => 'Other',
        ]);

        $checkIn = VolunteerCheckIn::create([
            'event_id'      => $event->id,
            'volunteer_id'  => $volunteer->id,
            'role'          => 'Other',
            'source'        => 'new_volunteer',
            'is_first_timer'=> true,
            'checked_in_at' => Carbon::now(),
        ]);

        return [
            'volunteer'   => $volunteer,
            'checkIn'     => $checkIn,
            'is_existing' => false,
        ];
    }

    /**
     * Check out a volunteer from an event, storing hours_served.
     * Throws ModelNotFoundException if no open check-in exists.
     */
    public function checkOut(Event $event, Volunteer $volunteer): VolunteerCheckIn
    {
        $checkIn = VolunteerCheckIn::where('event_id', $event->id)
            ->where('volunteer_id', $volunteer->id)
            ->whereNull('checked_out_at')
            ->firstOrFail();

        $now         = Carbon::now();
        $hoursServed = round($checkIn->checked_in_at->diffInMinutes($now) / 60, 2);

        $checkIn->update([
            'checked_out_at' => $now,
            'hours_served'   => $hoursServed,
        ]);

        return $checkIn->fresh();
    }

    /**
     * Return service statistics for a volunteer.
     *
     * totalEvents counts DISTINCT events, not check-in rows — a volunteer
     * with two sessions on a single event day (check in, lunch, check
     * back in) has served one event, not two. The check-ins collection
     * is still returned per-row so the Show page's Service History table
     * can render each session.
     */
    public function stats(Volunteer $volunteer): array
    {
        $checkIns = $volunteer->checkIns()
            ->with('event')
            ->orderBy('checked_in_at', 'desc')
            ->get();

        $distinctEventIds = $checkIns->pluck('event_id')->unique();
        $totalEvents      = $distinctEventIds->count();

        $eventsByDate = $checkIns
            ->filter(fn ($ci) => $ci->event !== null)
            ->sortBy(fn ($ci) => $ci->event->date)
            ->values();

        $firstService = $eventsByDate->first()?->event?->date;
        $lastService  = $eventsByDate->last()?->event?->date;
        $isFirstTimer = $totalEvents <= 1;
        $totalHours   = (float) $checkIns->sum(fn ($ci) => (float) ($ci->hours_served ?? 0));

        return compact(
            'checkIns',
            'totalEvents',
            'firstService',
            'lastService',
            'isFirstTimer',
            'totalHours',
        );
    }
}
