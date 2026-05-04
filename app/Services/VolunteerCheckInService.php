<?php

namespace App\Services;

use App\Exceptions\VolunteerCheckedInRecentlyException;
use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VolunteerCheckInService
{
    /**
     * Phase 5.11+ — fuzzy phone match. Strips every non-digit from the
     * input AND from the stored value before comparing, so users can
     * type "(555) 555-1234", "555-555-1234", "+1 555 555 1234", or
     * "5555551234" and hit the same volunteer regardless of how the
     * record was stored.
     *
     * Implementation choice: chained REPLACE() instead of REGEXP_REPLACE.
     * REGEXP_REPLACE is MySQL 8+ only and absent from SQLite (test DB),
     * which would silently break the test suite. The chain covers every
     * separator a human would type into a phone field — space, dash,
     * paren, plus, dot, slash. Performance is a full table scan; the
     * volunteer table is bounded (well under 10k rows in any realistic
     * deployment) so the unindexed scan is acceptable. If the table
     * grows, switch to a generated `phone_digits` column with an index.
     *
     * `whereNotNull('phone')` guards against an empty-stored-phone
     * volunteer matching a typed empty-after-strip query (which would
     * otherwise return every NULL-phone row).
     */
    public function findByPhoneDigits(string $phone): ?Volunteer
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        return Volunteer::whereNotNull('phone')
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), '/', '') = ?",
                [$digits],
            )
            ->first();
    }

    /**
     * Phase 5.6.e+ — phone-only lookup for the public check-in page.
     *
     * Pre-fix, this method searched volunteers by name, phone, or email
     * and returned the matched rows including phone + email — leaking
     * volunteer PII to anyone hitting the unauthenticated public
     * `/volunteer-checkin/search` endpoint. After 5.6.g (phone is
     * UNIQUE) phone is the unambiguous lookup key, and the response
     * shape drops phone + email entirely.
     *
     * Phase 5.11: the lookup is now FUZZY — punctuation in the typed
     * input or the stored value no longer prevents a match. See
     * `findByPhoneDigits()` for the comparison logic.
     *
     * Returns a Collection (still, for backwards compat with the
     * existing JsonResponse->json('results') wrapper) of at most one
     * entry — phone digits remain effectively unique even though the
     * Phase 5.6.g UNIQUE constraint is on the literal `phone` column.
     */
    public function search(Event $event, string $phone): Collection
    {
        $phone = trim($phone);
        if ($phone === '') {
            return collect();
        }

        $preAssigned = $event->assignedVolunteers->pluck('id')->flip();
        $checkedIn   = $event->volunteerCheckIns->pluck('volunteer_id')->flip();

        // Phase 5.6.b made multiple rows-per-(event, volunteer) legal, so
        // keyBy('volunteer_id') would silently drop earlier rows when
        // there are two. The new UI cares about the *latest* row (open
        // session if one exists, otherwise the last closed one) so the
        // status banner reflects "where they are right now".
        $checkInsByVolunteer = $event->volunteerCheckIns
            ->sortByDesc('checked_in_at')
            ->keyBy('volunteer_id');

        // Phase 5.11: fuzzy phone match — typed punctuation no longer
        // blocks a hit on stored bare digits, and vice versa.
        $match = $this->findByPhoneDigits($phone);
        if (! $match) {
            return collect();
        }

        return Volunteer::where('id', $match->id)
            ->with('groups:id,name')
            ->limit(1)
            ->get()
            ->map(function (Volunteer $v) use ($preAssigned, $checkedIn, $checkInsByVolunteer) {
                $ci = $checkInsByVolunteer->get($v->id);
                return [
                    'id'            => $v->id,
                    'full_name'     => $v->full_name,
                    // Phase 5.6.e: phone + email intentionally NOT
                    // returned. Public endpoint must not echo PII even
                    // back to the caller who already typed the phone.
                    'is_assigned'   => $preAssigned->has($v->id),
                    'checked_in'    => $checkedIn->has($v->id),
                    'checkin_time'  => $ci?->checked_in_at?->format('g:i A'),
                    // Phase 5.11: ISO timestamp powers the live elapsed
                    // clock on the View My Status flow. Safe to expose —
                    // it's the same moment the formatted time above is
                    // already showing, just machine-readable.
                    'checked_in_at_iso' => $ci?->checked_in_at?->toIso8601String(),
                    'is_first_timer'=> $ci?->is_first_timer ?? false,
                    'checked_out'   => $ci?->checked_out_at !== null,
                    'checkout_time' => $ci?->checked_out_at?->format('g:i A'),
                    'hours_served'  => $ci?->hours_served !== null
                        ? number_format((float) $ci->hours_served, 1)
                        : null,
                    // Phase 5.11: group memberships render as team
                    // badges on the Confirm card. id + name only —
                    // no membership metadata leaked.
                    'groups'        => $v->groups->map(fn ($g) => [
                        'id'   => $g->id,
                        'name' => $g->name,
                    ])->values(),
                ];
            });
    }

    /**
     * Check in an existing volunteer to an event.
     * Determines source (pre_assigned vs walk_in) automatically.
     * Detects first-timer status from actual service history.
     *
     * Behavior:
     *   - OPEN row exists for this (event, volunteer):
     *     - If checked_in_at is fresh (< stale_cap hours old): return it
     *       as-is (idempotent — same as 5.6.b's contract).
     *     - If stale (>= stale_cap hours old, default 12h): auto-close it
     *       at checked_in_at + cap (so hours_served reflects the original
     *       session bounded to the cap, not days of accumulated drift),
     *       then proceed to create a new row for the current session.
     *       Phase 5.6.j Mode A — "forgot to check out yesterday".
     *   - No OPEN row exists:
     *     - If the most-recent CLOSED row's checked_out_at is within
     *       min_gap minutes (default 5): throw
     *       VolunteerCheckedInRecentlyException so the controller
     *       returns a friendly 422. Phase 5.6.j Mode B — accidental
     *       rapid double-tap / trivial gaming.
     *     - Otherwise: insert a fresh row.
     *
     * Both rails apply to the public-facing path. Admin manual check-ins
     * via EventVolunteerCheckInController go through their own DB writes
     * and bypass these rails — admin can always override.
     *
     * Wrapped in a transaction with lockForUpdate so two concurrent
     * callers can't both insert and break the at-most-one-open invariant.
     */
    public function checkIn(Event $event, Volunteer $volunteer, ?string $role = null): VolunteerCheckIn
    {
        return DB::transaction(function () use ($event, $volunteer, $role) {
            // Resolve the rails policy. Settings are integers; clamp to
            // sane lower bounds so a misconfigured 0 / negative doesn't
            // produce nonsense behavior. min_gap of 0 disables that rail.
            $staleCapHours = max(1, (int) SettingService::get('event_queue.volunteer_stale_open_hours_cap', 12));
            $minGapMinutes = max(0, (int) SettingService::get('event_queue.volunteer_min_session_gap_minutes', 5));

            // Lock any existing rows for this (event, volunteer) so a
            // concurrent caller can't observe "no open row" while we
            // are about to insert one. The lock is released on commit.
            $existingOpen = VolunteerCheckIn::where('event_id', $event->id)
                ->where('volunteer_id', $volunteer->id)
                ->whereNull('checked_out_at')
                ->lockForUpdate()
                ->first();

            if ($existingOpen) {
                $ageHours = $existingOpen->checked_in_at
                    ? $existingOpen->checked_in_at->diffInMinutes(Carbon::now()) / 60
                    : 0.0;

                // Fresh open row — return as-is (idempotent re-call).
                if ($ageHours < $staleCapHours) {
                    return $existingOpen;
                }

                // Stale — auto-close at checked_in_at + cap. Cap applied
                // to hours_served so a multi-day-old open row contributes
                // exactly stale_cap hours, not the literal time since
                // check-in. Logged so admins can spot patterns of
                // missed checkouts in the audit trail.
                $autoCloseAt = $existingOpen->checked_in_at->copy()->addHours($staleCapHours);
                $existingOpen->update([
                    'checked_out_at' => $autoCloseAt,
                    'hours_served'   => round((float) $staleCapHours, 2),
                ]);
                Log::info('volunteer.checkin.stale_open_auto_closed', [
                    'volunteer_id'   => $volunteer->id,
                    'event_id'       => $event->id,
                    'check_in_id'    => $existingOpen->id,
                    'age_hours'      => round($ageHours, 1),
                    'capped_at_hours'=> $staleCapHours,
                ]);
                // Fall through to insert a fresh row for the current session.
            } elseif ($minGapMinutes > 0) {
                // Mode B — refuse if the previous CLOSED session ended too recently.
                $latestClosed = VolunteerCheckIn::where('event_id', $event->id)
                    ->where('volunteer_id', $volunteer->id)
                    ->whereNotNull('checked_out_at')
                    ->orderByDesc('checked_out_at')
                    ->first();

                if ($latestClosed && $latestClosed->checked_out_at) {
                    $secondsSinceCheckout = $latestClosed->checked_out_at->diffInSeconds(Carbon::now());
                    $gapSeconds = $minGapMinutes * 60;
                    if ($secondsSinceCheckout < $gapSeconds) {
                        throw new VolunteerCheckedInRecentlyException(
                            secondsRemaining: $gapSeconds - (int) $secondsSinceCheckout,
                            eventId:          $event->id,
                            volunteerId:      $volunteer->id,
                        );
                    }
                }
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
        // Phase 5.11: dedup uses fuzzy phone match. A submitted "555-0001"
        // matches an existing "5550001" so we don't create a second row
        // for the same person under different formatting.
        $existing = $phone !== ''
            ? $this->findByPhoneDigits($phone)
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
