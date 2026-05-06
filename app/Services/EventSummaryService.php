<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventReview;
use App\Models\FinanceTransaction;
use App\Models\User;
use App\Models\VolunteerCheckIn;
use Illuminate\Support\Collection;

/**
 * Builds the section-keyed payload for the Event Summary report.
 *
 * Each public method named `*Section` returns the data for exactly one tab in
 * the report. The orchestrator `buildPayload()` only computes the sections the
 * caller asks for so a partial report (e.g. "Attendees + Inventory only")
 * doesn't pay for the others.
 *
 * Finance is the only section gated on a separate permission (`finance.view`).
 * Other sections inherit the caller's `events.view` gate, which the controller
 * checks before invoking this service.
 */
class EventSummaryService
{
    public const ALL_SECTIONS = [
        'event_details',
        'attendees',
        'volunteers',
        'reviews',
        'inventory',
        'finance',
        'queue',
        'evaluation',
    ];

    public function __construct(private readonly EventAnalyticsService $analytics) {}

    /**
     * Format a duration given in minutes (decimal allowed) as HH:mm.
     * Examples: 12.5 → "00:13", 65 → "01:05", 0 → "00:00".
     * Returns "—" if $minutes is null or 0.
     */
    public static function formatHm(?float $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '00:00';
        }
        $rounded = (int) round($minutes);
        $hours   = intdiv($rounded, 60);
        $mins    = $rounded % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Build the report payload for the requested sections.
     * Sections the caller can't access (e.g. Finance without `finance.view`)
     * are silently dropped — the controller is responsible for surfacing why
     * they're missing if it wants to.
     */
    public function buildPayload(Event $event, array $sections, ?User $user = null): array
    {
        $event->loadMissing(['volunteerGroup.volunteers', 'ruleset', 'assignedVolunteers']);

        $sections = array_values(array_intersect(self::ALL_SECTIONS, $sections));
        $analyticsSummary = null; // lazy — several sections need it

        $out = [
            'event'    => $event,
            'sections' => $sections,
            'data'     => [],
        ];

        foreach ($sections as $section) {
            $out['data'][$section] = match ($section) {
                'event_details' => $this->eventDetailsSection($event),
                'attendees'     => $this->attendeesSection($event, $analyticsSummary ??= $this->analytics->summary($event)),
                'volunteers'    => $this->volunteersSection($event),
                'reviews'       => $this->reviewsSection($event),
                'inventory'     => $this->inventorySection($event),
                'finance'       => $this->financeSection($event, $user),
                'queue'         => $this->queueSection($event, $analyticsSummary ??= $this->analytics->summary($event)),
                'evaluation'    => null, // computed last so it can read other sections
            };
        }

        // Evaluation runs last because it inspects the other sections' results.
        if (in_array('evaluation', $sections, true)) {
            $out['data']['evaluation'] = $this->evaluationSection($event, $out['data']);
        }

        return $out;
    }

    // ─── Section: Event Details ─────────────────────────────────────────────

    private function eventDetailsSection(Event $event): array
    {
        $ruleset = $event->ruleset;
        $rules   = $ruleset?->rules ?? [];

        return [
            'name'            => $event->name,
            'date'            => $event->date,
            'location'        => $event->location,
            'lanes'           => $event->lanes,
            'description'     => $event->notes,
            'group'           => $event->volunteerGroup ? [
                'name'             => $event->volunteerGroup->name,
                'roster_count'     => $event->volunteerGroup->volunteers->count(),
            ] : null,
            'assigned_count'  => $event->assignedVolunteers->count(),
            'ruleset'         => $ruleset ? [
                'name'               => $ruleset->name,
                'allocation_type'    => $ruleset->allocation_type,
                'max_household_size' => $ruleset->max_household_size,
                'rules'              => is_array($rules) ? $rules : (json_decode($rules, true) ?: []),
            ] : null,
        ];
    }

    // ─── Section: Attendees ─────────────────────────────────────────────────

    private function attendeesSection(Event $event, array $analyticsSummary): array
    {
        $event->loadMissing(['preRegistrations', 'visits.households']);

        $preRegCount = $event->preRegistrations->count();

        // Pre-reg vs walk-in: a visit is "pre-registered" if its primary household
        // matches a registered attendee (by household_id link), otherwise walk-in.
        $preRegHouseholdIds = $event->preRegistrations
            ->pluck('household_id')
            ->filter()
            ->unique();

        $preRegAttended = 0;
        $walkIns        = 0;
        $totalPersons   = 0;
        $totalChildren  = 0;
        $totalAdults    = 0;
        $totalSeniors   = 0;
        $totalHouseholdRows = 0;

        foreach ($event->visits as $visit) {
            $primary = $visit->households->first();
            if (! $primary) {
                continue;
            }
            if ($preRegHouseholdIds->contains($primary->id)) {
                $preRegAttended++;
            } else {
                $walkIns++;
            }
            foreach ($visit->households as $h) {
                $totalHouseholdRows++;
                $totalPersons  += (int) ($h->pivot->household_size ?? $h->household_size ?? 0);
                $totalChildren += (int) ($h->pivot->children_count ?? 0);
                $totalAdults   += (int) ($h->pivot->adults_count   ?? 0);
                $totalSeniors  += (int) ($h->pivot->seniors_count  ?? 0);
            }
        }

        $totalVisits = $event->visits->count();

        return [
            'pre_registered_total' => $preRegCount,
            'pre_reg_attended'     => $preRegAttended,
            'pre_reg_no_show'      => max(0, $preRegCount - $preRegAttended),
            'walk_ins'             => $walkIns,
            'total_visits'         => $totalVisits,
            'total_households'     => $totalHouseholdRows,
            'total_persons'        => $totalPersons,
            'children'             => $totalChildren,
            'adults'               => $totalAdults,
            'seniors'              => $totalSeniors,
            'avg_household_size'   => $totalHouseholdRows > 0
                ? round($totalPersons / $totalHouseholdRows, 1)
                : 0,
            'pre_reg_match_rate'   => $preRegCount > 0
                ? round($preRegAttended / $preRegCount, 3)
                : null,
            'visit_status_counts'  => $analyticsSummary['status_counts'],
        ];
    }

    // ─── Section: Volunteers ────────────────────────────────────────────────

    private function volunteersSection(Event $event): array
    {
        $checkIns = VolunteerCheckIn::where('event_id', $event->id)->get();

        $bySource = $checkIns->groupBy('source')->map->count();

        $hours = $checkIns->where('checked_out_at', '!=', null)
            ->sum(fn ($c) => (float) $c->hours_served);

        return [
            'scheduled'         => $event->assignedVolunteers->count(),
            'pre_assigned_in'   => (int) ($bySource['pre_assigned']  ?? 0),
            'walk_ins'          => (int) ($bySource['walk_in']       ?? 0),
            'new_volunteers'    => (int) ($bySource['new_volunteer'] ?? 0),
            'total_check_ins'   => $checkIns->count(),
            'total_hours'       => round($hours, 1),
            'avg_hours'         => $checkIns->count() > 0
                ? round($hours / $checkIns->count(), 1)
                : 0,
            'first_timers'      => $checkIns->where('is_first_timer', true)->count(),
        ];
    }

    // ─── Section: Reviews ───────────────────────────────────────────────────

    private function reviewsSection(Event $event): array
    {
        $reviews = EventReview::where('event_id', $event->id)
            ->where('is_visible', true)
            ->latest()
            ->get();

        $count = $reviews->count();

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $r) {
            $rating = max(1, min(5, (int) $r->rating));
            $distribution[$rating]++;
        }

        $avg = $count > 0
            ? round($reviews->avg('rating'), 1)
            : null;

        // 5 most recent good reviews (4-5★) and bad reviews (1-2★).
        $good = $reviews->whereIn('rating', [4, 5])->take(5)->values();
        $bad  = $reviews->whereIn('rating', [1, 2])->take(5)->values();

        return [
            'total'         => $count,
            'avg_rating'    => $avg,
            'distribution'  => $distribution,
            'good_reviews'  => $good,
            'bad_reviews'   => $bad,
            'has_neutral'   => $distribution[3] > 0,
        ];
    }

    // ─── Section: Inventory ─────────────────────────────────────────────────

    private function inventorySection(Event $event): array
    {
        $event->loadMissing('inventoryAllocations.item');

        $rows = $event->inventoryAllocations->map(function ($alloc) {
            $allocated   = (int) $alloc->allocated_quantity;
            $distributed = (int) $alloc->distributed_quantity;
            $returned    = (int) $alloc->returned_quantity;
            return [
                'name'        => $alloc->item?->name ?? '— (deleted item)',
                'unit'        => $alloc->item?->unit_of_measure ?? '',
                'allocated'   => $allocated,
                'distributed' => $distributed,
                'returned'    => $returned,
                'remaining'   => max(0, $allocated - $distributed - $returned),
                'rate'        => $allocated > 0
                    ? round($distributed / $allocated, 3)
                    : 0,
            ];
        })->sortByDesc('allocated')->values();

        $totalAllocated   = $rows->sum('allocated');
        $totalDistributed = $rows->sum('distributed');
        $totalReturned    = $rows->sum('returned');

        return [
            'rows'              => $rows,
            'total_items'       => $rows->count(),
            'total_allocated'   => $totalAllocated,
            'total_distributed' => $totalDistributed,
            'total_returned'    => $totalReturned,
            'distribution_rate' => $totalAllocated > 0
                ? round($totalDistributed / $totalAllocated, 3)
                : 0,
        ];
    }

    // ─── Section: Finance (gated on finance.view) ───────────────────────────

    private function financeSection(Event $event, ?User $user): ?array
    {
        if ($user && method_exists($user, 'hasPermission')
            && ! $user->hasPermission('finance.view')) {
            return ['gated' => true];
        }

        $tx = FinanceTransaction::with('category')
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->get();

        return [
            'gated'   => false,
            'income'  => $this->financeBreakdown($tx->where('transaction_type', 'income')),
            'expense' => $this->financeBreakdown($tx->where('transaction_type', 'expense')),
            'net'     => round(
                (float) $tx->where('transaction_type', 'income')->sum('amount')
                - (float) $tx->where('transaction_type', 'expense')->sum('amount'),
                2,
            ),
        ];
    }

    /**
     * Reduce a transaction collection to a total + the top 3 categories with
     * "Other" as a 4th bucket. Used by financeSection() for both income and
     * expense.
     */
    private function financeBreakdown(Collection $tx): array
    {
        $total = (float) $tx->sum('amount');
        if ($total <= 0) {
            return ['total' => 0.0, 'top_sources' => []];
        }

        $byCategory = $tx
            ->groupBy(fn ($t) => $t->category?->name ?? 'Uncategorized')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortDesc();

        $top    = $byCategory->take(3);
        $rest   = $byCategory->skip(3)->sum();

        $sources = $top->map(fn ($amount, $name) => [
            'name'   => $name,
            'amount' => round($amount, 2),
            'pct'    => round($amount / $total, 3),
        ])->values()->all();

        if ($rest > 0) {
            $sources[] = [
                'name'   => 'Other',
                'amount' => round($rest, 2),
                'pct'    => round($rest / $total, 3),
            ];
        }

        return [
            'total'       => round($total, 2),
            'top_sources' => $sources,
        ];
    }

    // ─── Section: Queue ─────────────────────────────────────────────────────

    private function queueSection(Event $event, array $analyticsSummary): array
    {
        return [
            'avg_checkin_to_queue' => $analyticsSummary['avg_checkin_to_queue'],
            'avg_queue_to_loaded'  => $analyticsSummary['avg_queue_to_loaded'],
            'avg_loaded_to_exited' => $analyticsSummary['avg_loaded_to_exited'],
            'avg_total_time'       => $analyticsSummary['avg_total_time'],
            'lanes'                => $event->lanes,
            'total_visits'         => $analyticsSummary['total_visits'],
            'completed_visits'     => $analyticsSummary['status_counts']['exited'],
            'bags_distributed'     => $analyticsSummary['bags_distributed'],
        ];
    }

    // ─── Section: Evaluation ────────────────────────────────────────────────
    //
    // Reads the other sections' computed payloads and emits 6–10 heuristic
    // observations. Each observation has a `kind` (positive | neutral |
    // concerning) so the UI can colour-code them. Thresholds below are tuned
    // for typical food-bank distribution events; tweak in one place if the
    // baseline shifts.

    private function evaluationSection(Event $event, array $sectionData): array
    {
        $insights = [];

        // ── Pre-registration accuracy
        if (isset($sectionData['attendees']) && $sectionData['attendees']['pre_registered_total'] > 0) {
            $rate = $sectionData['attendees']['pre_reg_match_rate'];
            $pct  = $rate !== null ? round($rate * 100) : 0;
            $insights[] = [
                'kind'     => $pct >= 80 ? 'positive' : ($pct >= 60 ? 'neutral' : 'concerning'),
                'category' => 'Pre-Registration',
                'message'  => "{$pct}% of pre-registered households checked in"
                    . ($pct >= 80 ? ' — strong match rate.' : ($pct >= 60
                        ? ' — typical for community events.'
                        : ' — many no-shows; consider reminder messaging next time.')),
            ];
        }

        // ── Walk-in pressure
        if (isset($sectionData['attendees'])) {
            $walkIns = $sectionData['attendees']['walk_ins'];
            $total   = $sectionData['attendees']['total_visits'];
            if ($total > 0) {
                $pct = round($walkIns / $total * 100);
                $insights[] = [
                    'kind'     => $pct < 30 ? 'positive' : ($pct < 60 ? 'neutral' : 'concerning'),
                    'category' => 'Walk-Ins',
                    'message'  => "{$pct}% of attendees were walk-ins ({$walkIns} of {$total})"
                        . ($pct >= 60 ? ' — capacity planning may need review.' : '.'),
                ];
            }
        }

        // ── Inventory utilisation
        if (isset($sectionData['inventory']) && $sectionData['inventory']['total_allocated'] > 0) {
            $rate = $sectionData['inventory']['distribution_rate'];
            $pct  = round($rate * 100);
            $insights[] = [
                'kind'     => $pct >= 85 ? 'positive' : ($pct >= 60 ? 'neutral' : 'concerning'),
                'category' => 'Inventory',
                'message'  => "{$pct}% of allocated inventory was distributed"
                    . ($pct >= 85
                        ? ' — efficient utilisation.'
                        : ($pct >= 60
                            ? ' — some surplus returned to stock.'
                            : ' — significant over-allocation; consider reducing for similar events.')),
            ];
        }

        // ── Volunteer ratio (households per volunteer)
        if (isset($sectionData['volunteers'], $sectionData['attendees'])) {
            $vols = max(1, $sectionData['volunteers']['total_check_ins']);
            $hh   = $sectionData['attendees']['total_households'];
            $ratio = round($hh / $vols, 1);
            $insights[] = [
                'kind'     => $ratio >= 3 && $ratio <= 8 ? 'positive' : 'neutral',
                'category' => 'Volunteer Capacity',
                'message'  => "{$ratio} households per volunteer "
                    . ($ratio < 3 ? '— well-staffed.'
                        : ($ratio <= 8 ? '— balanced staffing.' : '— possibly under-staffed.')),
            ];
        }

        // ── Review sentiment
        if (isset($sectionData['reviews']) && $sectionData['reviews']['total'] > 0) {
            $avg = $sectionData['reviews']['avg_rating'];
            $insights[] = [
                'kind'     => $avg >= 4 ? 'positive' : ($avg >= 3 ? 'neutral' : 'concerning'),
                'category' => 'Reviews',
                'message'  => "Average review rating {$avg}/5 across {$sectionData['reviews']['total']} reviews"
                    . ($avg >= 4 ? ' — excellent feedback.' : ($avg >= 3 ? ' — mixed feedback.' : ' — review the bad-feedback list for patterns.')),
            ];
        }

        // ── Queue throughput
        if (isset($sectionData['queue']) && $sectionData['queue']['avg_total_time'] > 0) {
            $minutes = $sectionData['queue']['avg_total_time'];
            $insights[] = [
                'kind'     => $minutes < 20 ? 'positive' : ($minutes < 40 ? 'neutral' : 'concerning'),
                'category' => 'Queue Throughput',
                'message'  => "Average end-to-end visit time was {$minutes} minutes"
                    . ($minutes < 20 ? ' — fast turnaround.'
                        : ($minutes < 40 ? '.' : ' — queue may have bottlenecks worth investigating.')),
            ];
        }

        // ── Finance net
        if (isset($sectionData['finance']) && empty($sectionData['finance']['gated'])) {
            $net = $sectionData['finance']['net'];
            $insights[] = [
                'kind'     => $net >= 0 ? 'positive' : 'concerning',
                'category' => 'Finance',
                'message'  => $net >= 0
                    ? 'Net result of $' . number_format(abs($net), 2) . ' surplus.'
                    : 'Net result of $' . number_format(abs($net), 2) . ' deficit — expense exceeded income.',
            ];
        }

        return $insights;
    }
}
