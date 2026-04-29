<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Household;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventCheckInService
{
    /**
     * Search households by household_number, first_name, last_name, phone (LIKE),
     * or exact qr_token match. Returns up to 10 results.
     */
    public function search(string $query, int $eventId = null): Collection
    {
        $term = trim($query);

        $households = Household::where(function ($q) use ($term) {
            $q->where('household_number', 'like', "%{$term}%")
              ->orWhere('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('vehicle_make', 'like', "%{$term}%")
              ->orWhere('qr_token', $term);  // exact match for QR token
        })
        ->with('representedHouseholds')
        ->limit(10)
        ->get();

        // Flag households that are pre-registered for the given event
        if ($eventId && $households->isNotEmpty()) {
            $preRegHouseholdIds = EventPreRegistration::where('event_id', $eventId)
                ->whereNotNull('household_id')
                ->pluck('household_id')
                ->flip();

            foreach ($households as $h) {
                $h->setAttribute('is_pre_registered', $preRegHouseholdIds->has($h->id));
            }
        }

        return $households;
    }

    /**
     * Check in a household to an event on a given lane.
     *
     * @param  array|null  $representedIds  When provided (non-null), attach exactly these
     *                                      household IDs as represented members instead of
     *                                      using the DB relationship. Allows the check-in
     *                                      controller to pass a staff-curated list that may
     *                                      include households created inline during check-in.
     *
     * Throws RuntimeException if already active.
     */
    public function checkIn(Event $event, Household $household, int $lane, ?array $representedIds = null): Visit
    {
        // Determine the set of represented IDs to attach (no DB needed yet).
        if ($representedIds !== null) {
            // Explicit list from controller (may include newly-created households)
            $representedIdCollection = collect($representedIds)->map('intval')->filter()->values();
        } else {
            // Fallback: use DB relationship
            $household->loadMissing('representedHouseholds');
            $representedIdCollection = $household->representedHouseholds->pluck('id');
        }

        // Build allIds without mutating $representedIdCollection (prepend() mutates in place)
        $allIds = collect([$household->id])->concat($representedIdCollection);

        // Wrap the read-then-insert in a transaction with lockForUpdate so two
        // concurrent check-ins on the same lane cannot both compute MAX+1 = N
        // and insert duplicate positions. The unique index added in Phase 1.1.a
        // would catch the violation; this lock prevents it from ever firing
        // under normal load, avoiding spurious 500s for users.
        return DB::transaction(function () use ($event, $household, $lane, $representedIdCollection, $allIds) {
            // Re-check active status inside the transaction so it's consistent
            // with the position read.
            $alreadyActive = Visit::where('event_id', $event->id)
                ->whereNull('end_time')
                ->whereHas('households', fn ($q) => $q->whereIn('households.id', $allIds))
                ->exists();

            if ($alreadyActive) {
                throw new \RuntimeException(
                    "{$household->full_name} already has an active check-in for this event."
                );
            }

            // SELECT ... FOR UPDATE serializes concurrent check-ins per (event, lane).
            $nextPosition = (Visit::where('event_id', $event->id)
                ->where('lane', $lane)
                ->lockForUpdate()
                ->max('queue_position') ?? 0) + 1;

            $visit = Visit::create([
                'event_id'       => $event->id,
                'lane'           => $lane,
                'queue_position' => $nextPosition,
                'visit_status'   => 'checked_in',
                'start_time'     => now(),
                'end_time'       => null,
                'served_bags'    => 0,
            ]);

            // Attach representative first — stays as the primary/driver household
            $visit->households()->attach($household->id);

            // Attach represented households — exclude the representative's own ID
            // in case it was accidentally included in the caller-supplied list
            $toAttach = $representedIdCollection->reject(fn ($id) => $id === $household->id);
            if ($toAttach->isNotEmpty()) {
                $visit->households()->attach($toAttach->toArray());
            }

            return $visit->load('households');
        });
    }

    /**
     * Mark a visit as served & exited directly from the monitor,
     * skipping any intermediate queue/loading steps.
     */
    public function markDone(Visit $visit): Visit
    {
        $now = now();

        $visit->update([
            'visit_status'         => 'exited',
            'queued_at'            => $visit->queued_at            ?? $now,
            'loading_completed_at' => $visit->loading_completed_at ?? $now,
            'exited_at'            => $now,
            'end_time'             => $now,
            // Phase 1.1.c.1: position is meaningful only for active visits.
            'queue_position'       => null,
        ]);

        return $visit;
    }

    /**
     * Return active visits (end_time null) for the event,
     * with households eager-loaded, ordered by start_time desc.
     */
    public function activeQueue(Event $event): Collection
    {
        return Visit::where('event_id', $event->id)
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhere('visit_status', '!=', 'exited'))
            ->with('households')
            ->orderBy('start_time', 'desc')
            ->get();
    }

    /**
     * Return the last 20 visits (active + completed) for the event,
     * newest first. Used to populate the check-in log.
     */
    public function recentLog(Event $event): Collection
    {
        return Visit::where('event_id', $event->id)
            ->with('households')
            ->orderBy('start_time', 'desc')
            ->limit(20)
            ->get();
    }
}
