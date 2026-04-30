<?php

namespace App\Services;

use App\Exceptions\HouseholdAlreadyServedException;
use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Household;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * @param  array|null  $representedIds   When provided (non-null), attach exactly these
     *                                       household IDs as represented members instead of
     *                                       using the DB relationship. Allows the check-in
     *                                       controller to pass a staff-curated list that may
     *                                       include households created inline during check-in.
     * @param  bool        $force            Phase 1.3 supervisor-override flag. When true and
     *                                       the configured re-check-in policy is 'override',
     *                                       proceeds past the already-served guard and writes
     *                                       a Log::warning audit record. Has no effect when
     *                                       policy is 'deny' (deny is absolute) or 'allow'
     *                                       (no guard to bypass).
     * @param  string|null $overrideReason   Free-text reason captured from the supervisor;
     *                                       only consulted when $force=true triggers the
     *                                       audit log. Phase 4 will move this into the
     *                                       formal audit_logs table.
     *
     * Throws RuntimeException for the active-duplicate case (data-integrity invariant —
     * NOT subject to the policy setting; the same household cannot occupy two queue
     * positions simultaneously).
     *
     * Throws HouseholdAlreadyServedException for the exited-only case when the configured
     * re-check-in policy refuses the new check-in.
     *
     * Precedence: the active check runs FIRST. If a household has both an active visit
     * AND a prior exited visit at this event (rare, but possible if external code
     * created an extra row), the active-block fires and the exited-side never reports.
     * That ordering is intentional — the data-integrity violation is the more urgent
     * signal — but it means the controller will not see the exited collision in this
     * edge case. Do not invert.
     */
    public function checkIn(
        Event $event,
        Household $household,
        int $lane,
        ?array $representedIds = null,
        bool $force = false,
        ?string $overrideReason = null,
    ): Visit {
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
        return DB::transaction(function () use ($event, $household, $lane, $representedIdCollection, $allIds, $force, $overrideReason) {
            // Re-check active status inside the transaction so it's consistent
            // with the position read.
            $alreadyActive = Visit::where('event_id', $event->id)
                ->whereNull('end_time')
                ->whereHas('households', fn ($q) => $q->whereIn('households.id', $allIds))
                ->exists();

            if ($alreadyActive) {
                // Hard block, NOT subject to the re_checkin_policy setting. A
                // household actively in the queue cannot occupy two positions
                // at once — that's a data-integrity invariant, not policy.
                // $force does not bypass this either; only the configured
                // exited-only policy is overrideable.
                throw new \RuntimeException(
                    "{$household->full_name} already has an active check-in for this event."
                );
            }

            // Phase 1.3: when a household has previously been served and exited
            // at this event, apply the configured event_queue.re_checkin_policy:
            //   'allow'    → proceed silently (pre-1.3 behavior)
            //   'override' → throw unless $force is true; on $force=true log + proceed
            //   'deny'     → throw regardless of $force
            //
            // Resolve the actual colliding subset of $allIds (not just whether
            // any collision exists) so the exception can tell the controller
            // exactly which households are offending — needed to render names
            // in the override modal without a follow-up query. One DB::table
            // query gives us both the household IDs (for the exception payload)
            // and the visit IDs (for the override audit log).
            $priorExitedRows = DB::table('visit_households as vh')
                ->join('visits as v', 'v.id', '=', 'vh.visit_id')
                ->where('v.event_id', $event->id)
                ->whereNotNull('v.end_time')
                ->whereIn('vh.household_id', $allIds->all())
                ->select('v.id as visit_id', 'vh.household_id')
                ->get();

            if ($priorExitedRows->isNotEmpty()) {
                $offendingHouseholdIds = $priorExitedRows->pluck('household_id')
                    ->unique()
                    ->values()
                    ->all();

                $policy = SettingService::get('event_queue.re_checkin_policy', 'override');

                if ($policy === 'deny') {
                    throw new HouseholdAlreadyServedException(
                        eventId: $event->id,
                        householdIds: $offendingHouseholdIds,
                        allowOverride: false,
                        message: 'One or more households have already been served at this event. The current policy does not permit re-check-in.',
                    );
                }

                if ($policy === 'override' && ! $force) {
                    throw new HouseholdAlreadyServedException(
                        eventId: $event->id,
                        householdIds: $offendingHouseholdIds,
                        allowOverride: true,
                        message: 'One or more households have already been served at this event. Supervisor override required.',
                    );
                }

                if ($policy === 'override' && $force) {
                    // Audit the override. Phase 4 will formalize this into
                    // audit_logs; for now Log::warning is the spec-mandated path.
                    Log::warning('checkin.override', [
                        'user_id'           => Auth::id(),
                        'event_id'          => $event->id,
                        'representative_id' => $household->id,
                        'household_ids'     => $offendingHouseholdIds,
                        'prior_visit_ids'   => $priorExitedRows->pluck('visit_id')->unique()->values()->all(),
                        'reason'            => $overrideReason,
                    ]);
                }
                // policy === 'allow' falls through silently
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

            // Attach representative first — stays as the primary/driver household.
            // Phase 1.2.b: capture the household's demographics + vehicle on the
            // pivot at attach time so reports stay temporally stable even if
            // the household's `households.*` row is edited after the visit.
            $visit->households()->attach($household->id, $household->toVisitPivotSnapshot());

            // Attach represented households — exclude the representative's own ID
            // in case it was accidentally included in the caller-supplied list
            $toAttach = $representedIdCollection->reject(fn ($id) => $id === $household->id);
            if ($toAttach->isNotEmpty()) {
                // Bulk-load so we don't issue one query per represented household.
                $represented = Household::whereIn('id', $toAttach->toArray())->get();

                // Strict: every requested id must exist. Pre-1.2.b this was
                // enforced implicitly by the FK on `visit_households.household_id`
                // when attach() was called with a missing id. With the bulk
                // pre-load, an unknown id would silently disappear from the
                // payload — the visit would be created but the represented
                // household never attached. Fail loud and let the surrounding
                // DB::transaction roll the visit back.
                if ($represented->count() !== $toAttach->count()) {
                    $missing = $toAttach->diff($represented->pluck('id'));
                    Log::warning('checkIn called with non-existent represented household IDs', [
                        'event_id'           => $event->id,
                        'representative_id'  => $household->id,
                        'requested_ids'      => $toAttach->all(),
                        'missing_ids'        => $missing->all(),
                    ]);
                    throw new \RuntimeException(
                        'Represented household ID(s) do not exist: ' . $missing->implode(', ')
                    );
                }

                $bulk = [];
                foreach ($represented as $r) {
                    $bulk[$r->id] = $r->toVisitPivotSnapshot();
                }
                $visit->households()->attach($bulk);
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
