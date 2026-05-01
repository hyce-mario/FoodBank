<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     */
    public function checkIn(Event $event, Volunteer $volunteer, ?string $role = null): VolunteerCheckIn
    {
        // Determine source
        $isAssigned = $event->assignedVolunteers()->where('volunteer_id', $volunteer->id)->exists();
        $source     = $isAssigned ? 'pre_assigned' : 'walk_in';

        // First-timer = no prior check-ins on any event (before this one is created)
        $isFirstTimer = $volunteer->checkIns()->count() === 0;

        return VolunteerCheckIn::updateOrCreate(
            ['event_id' => $event->id, 'volunteer_id' => $volunteer->id],
            [
                'role'          => $role ?? $volunteer->role,
                'source'        => $source,
                'is_first_timer'=> $isFirstTimer,
                'checked_in_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Create a brand-new volunteer record and immediately check them in.
     * Always marked as 'new_volunteer' source and 'first_timer' since they
     * have no prior service history.
     */
    public function createAndCheckIn(Event $event, array $data): array
    {
        $volunteer = Volunteer::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'phone'      => $data['phone'] ?? null,
            'email'      => $data['email'] ?? null,
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

        return compact('volunteer', 'checkIn');
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
     */
    public function stats(Volunteer $volunteer): array
    {
        $checkIns = $volunteer->checkIns()
            ->with('event')
            ->orderBy('checked_in_at', 'desc')
            ->get();

        $totalEvents  = $checkIns->count();
        $firstService = $checkIns->last()?->event?->date;
        $lastService  = $checkIns->first()?->event?->date;
        $isFirstTimer = $totalEvents <= 1;

        return compact('checkIns', 'totalEvents', 'firstService', 'lastService', 'isFirstTimer');
    }
}
