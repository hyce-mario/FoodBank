<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventReview;
use App\Models\Volunteer;
use App\Models\VolunteerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
    // ─── Date Range ───────────────────────────────────────────────────────────

    public function resolveDateRange(Request $request): array
    {
        $preset = $request->get('preset', 'last_30');

        return match ($preset) {
            'today'      => [today(), today()],
            'last_7'     => [today()->subDays(6), today()],
            'last_30'    => [today()->subDays(29), today()],
            'this_month' => [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay()],
            'this_year'  => [now()->startOfYear()->startOfDay(), now()->endOfYear()->endOfDay()],
            'custom'     => [
                $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : today()->subDays(29),
                $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : today()->endOfDay(),
            ],
            default => [today()->subDays(29), today()->endOfDay()],
        };
    }

    // ─── Overview KPIs ────────────────────────────────────────────────────────

    public function overview(Carbon $from, Carbon $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        // All exited visit IDs in range
        $exitedIds = DB::table('visits')
            ->where('visit_status', 'exited')
            ->whereDate('start_time', '>=', $fromStr)
            ->whereDate('start_time', '<=', $toStr)
            ->pluck('id');

        $householdsServed = $exitedIds->isNotEmpty()
            ? DB::table('visit_households')
                ->whereIn('visit_id', $exitedIds)
                ->distinct('household_id')
                ->count('household_id')
            : 0;

        // Phase 1.2.c: read the snapshot from `visit_households` instead of
        // joining live `households`. With 1.2.b's NOT NULL constraint on
        // `vh.household_size`, the previous JOIN + h.household_size — which
        // would silently change historical totals when an admin edited a
        // household after a visit — is no longer needed.
        $peopleServed = $exitedIds->isNotEmpty()
            ? (int) DB::table('visit_households')
                ->whereIn('visit_id', $exitedIds)
                ->sum('household_size')
            : 0;

        $bagsDistributed = $exitedIds->isNotEmpty()
            ? (int) DB::table('visits')->whereIn('id', $exitedIds)->sum('served_bags')
            : 0;

        $totalEvents = Event::whereIn('status', ['current', 'past'])
            ->whereDate('date', '>=', $fromStr)
            ->whereDate('date', '<=', $toStr)
            ->count();

        $avgServiceTime = $exitedIds->isNotEmpty()
            ? DB::table('visits')
                ->whereIn('id', $exitedIds)
                ->whereNotNull('exited_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, start_time, exited_at)) / 60 as avg_mins')
                ->value('avg_mins')
            : 0;

        $totalVisitsInRange = DB::table('visits')
            ->whereDate('start_time', '>=', $fromStr)
            ->whereDate('start_time', '<=', $toStr)
            ->count();

        $completedVisits  = $exitedIds->count();
        $incompleteVisits = max(0, $totalVisitsInRange - $completedVisits);

        $avgRating = DB::table('event_reviews as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->avg('er.rating');

        $totalReviews = DB::table('event_reviews as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->count();

        // "Volunteers Served" = unique volunteers who actually checked in to an
        // event in the period, NOT just those assigned. Matches the definition
        // used by ReportAnalyticsService::volunteers() and the Volunteers
        // report page so the Overview KPI agrees with the dedicated page.
        $totalVolunteers = DB::table('volunteer_check_ins as vci')
            ->join('events as e', 'e.id', '=', 'vci.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->distinct('vci.volunteer_id')
            ->count('vci.volunteer_id');

        return [
            'households_served'  => $householdsServed,
            'people_served'      => $peopleServed,
            'bags_distributed'   => $bagsDistributed,
            'total_events'       => $totalEvents,
            'avg_service_time'   => round((float) ($avgServiceTime ?? 0), 1),
            'completed_visits'   => $completedVisits,
            'incomplete_visits'  => $incompleteVisits,
            'total_visits'       => $totalVisitsInRange,
            'avg_rating'         => round((float) ($avgRating ?? 0), 1),
            'total_reviews'      => $totalReviews,
            'total_volunteers'   => $totalVolunteers,
        ];
    }

    // ─── Overview Trend Chart ─────────────────────────────────────────────────

    public function overviewTrend(Carbon $from, Carbon $to): array
    {
        [$groupExpr, $labelExpr] = $this->groupingExpressions($from, $to, 'v.start_time');

        // Phase 1.2.c: SUM the pivot snapshot, not the live household.
        $rows = DB::select("
            SELECT
                {$labelExpr} AS label,
                {$groupExpr} AS period,
                COUNT(DISTINCT vh.household_id)    AS households,
                COALESCE(SUM(vh.household_size), 0) AS people,
                COALESCE(SUM(v.served_bags), 0)    AS bags
            FROM visits v
            JOIN visit_households vh ON vh.visit_id = v.id
            WHERE v.visit_status = 'exited'
              AND DATE(v.start_time) BETWEEN ? AND ?
            GROUP BY period, label
            ORDER BY period ASC
        ", [$from->format('Y-m-d'), $to->format('Y-m-d')]);

        return [
            'labels'     => array_column($rows, 'label'),
            'households' => array_map('intval', array_column($rows, 'households')),
            'people'     => array_map('intval', array_column($rows, 'people')),
            'bags'       => array_map('intval', array_column($rows, 'bags')),
        ];
    }

    // ─── Event Performance ────────────────────────────────────────────────────

    public function eventPerformance(Carbon $from, Carbon $to): array
    {
        $events = Event::whereIn('status', ['past', 'current'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->with(['visits.households', 'reviews'])
            ->orderBy('date', 'desc')
            ->get();

        return $events->map(function (Event $event) {
            $visits  = $event->visits;
            $exited  = $visits->where('visit_status', 'exited');

            $householdsServed = $exited->flatMap->households->unique('id')->count();
            // Phase 1.2.c: sum the pivot snapshot, not the live household_size.
            // The Eloquent collection includes pivot data via withPivot() on
            // Visit::households() (added in 1.2.a).
            $peopleServed     = (int) $exited->flatMap->households->sum(fn ($h) => $h->pivot->household_size);
            $bagsDistributed  = (int) $exited->sum('served_bags');
            $completionRate   = $visits->count() > 0
                ? round($exited->count() / $visits->count() * 100)
                : 0;

            $avgServiceTime = $exited
                ->filter(fn ($v) => $v->exited_at && $v->start_time)
                ->avg(fn ($v) => $v->start_time->diffInSeconds($v->exited_at) / 60);

            $avgRating   = $event->reviews->avg('rating');
            $reviewCount = $event->reviews->count();

            return [
                'id'               => $event->id,
                'name'             => $event->name,
                'date'             => $event->date->format('M j, Y'),
                'date_sort'        => $event->date->format('Y-m-d'),
                'location'         => $event->location ?? '—',
                'lanes'            => $event->lanes,
                'total_visits'     => $visits->count(),
                'households_served'=> $householdsServed,
                'people_served'    => $peopleServed,
                'bags_distributed' => $bagsDistributed,
                'completion_rate'  => $completionRate,
                'avg_service_time' => round((float) ($avgServiceTime ?? 0), 1),
                'avg_rating'       => $avgRating ? round($avgRating, 1) : null,
                'review_count'     => $reviewCount,
            ];
        })->values()->all();
    }

    // ─── Trends ───────────────────────────────────────────────────────────────

    public function trends(Carbon $from, Carbon $to): array
    {
        [$groupExpr, $labelExpr] = $this->groupingExpressions($from, $to, 'v.start_time');

        // Phase 1.2.c: SUM the pivot snapshot, not the live household.
        $rows = DB::select("
            SELECT
                {$labelExpr} AS label,
                {$groupExpr} AS period,
                COUNT(DISTINCT vh.household_id)       AS households,
                COALESCE(SUM(vh.household_size), 0)   AS people,
                COALESCE(SUM(v.served_bags), 0)       AS bags
            FROM visits v
            JOIN visit_households vh ON vh.visit_id = v.id
            WHERE v.visit_status = 'exited'
              AND DATE(v.start_time) BETWEEN ? AND ?
            GROUP BY period, label
            ORDER BY period ASC
        ", [$from->format('Y-m-d'), $to->format('Y-m-d')]);

        // New vs returning households (aggregated)
        $newReturning = $this->newReturningCount($from->format('Y-m-d'), $to->format('Y-m-d'));

        return [
            'labels'          => array_column($rows, 'label'),
            'households'      => array_map('intval', array_column($rows, 'households')),
            'people'          => array_map('intval', array_column($rows, 'people')),
            'bags'            => array_map('intval', array_column($rows, 'bags')),
            'new_households'  => $newReturning['new'],
            'ret_households'  => $newReturning['returning'],
        ];
    }

    // ─── Demographics ─────────────────────────────────────────────────────────

    public function demographics(Carbon $from, Carbon $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        // Phase 1.2.c: the `households as h` join is dead weight for the
        // demographics-only queries below (sizeDist, vehicleDist read from
        // `vh.*`), but is retained because zipDist + cityDist need
        // `h.zip` / `h.city` which are not snapshotted on the pivot. At
        // typical period row counts (<1k) the extra join is negligible.
        $base = fn () => DB::table('visit_households as vh')
            ->join('visits as v',     'v.id', '=', 'vh.visit_id')
            ->join('households as h', 'h.id', '=', 'vh.household_id')
            ->where('v.visit_status', 'exited')
            ->whereDate('v.start_time', '>=', $fromStr)
            ->whereDate('v.start_time', '<=', $toStr);

        // Phase 1.2.c: group by the snapshotted size so historical breakdowns
        // don't shift when an admin edits a household's size after a visit.
        $sizeDist = $base()
            ->selectRaw('vh.household_size AS size, COUNT(DISTINCT vh.household_id) AS count')
            ->whereNotNull('vh.household_size')
            ->groupBy('vh.household_size')
            ->orderBy('vh.household_size')
            ->get();

        // Family composition — totals, ratios, and per-household averages drawn
        // from the Phase 1.2.a snapshot columns (children_count / adults_count /
        // seniors_count on visit_households). DISTINCT household so a household
        // served twice in the period only counts once toward composition.
        $compRow = $base()
            ->selectRaw('
                COUNT(DISTINCT vh.household_id)             AS households,
                COALESCE(SUM(vh.children_count), 0)          AS children,
                COALESCE(SUM(vh.adults_count),   0)          AS adults,
                COALESCE(SUM(vh.seniors_count),  0)          AS seniors
            ')
            ->first();

        $children = (int) ($compRow->children ?? 0);
        $adults   = (int) ($compRow->adults   ?? 0);
        $seniors  = (int) ($compRow->seniors  ?? 0);
        $hh       = (int) ($compRow->households ?? 0);
        $total    = $children + $adults + $seniors;

        // Households-with-children: a separate distinct count so the percentage
        // reflects households where at least one child was served, not a sum.
        $householdsWithChildren = $hh > 0
            ? (int) $base()
                ->where('vh.children_count', '>', 0)
                ->distinct('vh.household_id')
                ->count('vh.household_id')
            : 0;

        $composition = [
            'households'              => $hh,
            'children'                => $children,
            'adults'                  => $adults,
            'seniors'                 => $seniors,
            'total_people'            => $total,
            'pct_children'            => $total > 0 ? round($children / $total * 100, 1) : 0.0,
            'pct_adults'              => $total > 0 ? round($adults   / $total * 100, 1) : 0.0,
            'pct_seniors'             => $total > 0 ? round($seniors  / $total * 100, 1) : 0.0,
            'avg_children'            => $hh > 0 ? round($children / $hh, 2) : 0.0,
            'avg_adults'              => $hh > 0 ? round($adults   / $hh, 2) : 0.0,
            'avg_seniors'             => $hh > 0 ? round($seniors  / $hh, 2) : 0.0,
            'avg_household_size'      => $hh > 0 ? round($total / $hh, 2) : 0.0,
            // Ratio is "per adult". 0 when adults = 0 (no division-by-zero); the
            // panel hides the ratio in that case so 0.0 doesn't read as "no kids."
            'child_to_adult_ratio'    => $adults > 0 ? round($children / $adults, 2) : 0.0,
            'senior_to_adult_ratio'   => $adults > 0 ? round($seniors  / $adults, 2) : 0.0,
            'households_with_children'      => $householdsWithChildren,
            'households_with_children_pct'  => $hh > 0 ? round($householdsWithChildren / $hh * 100, 1) : 0.0,
        ];

        $zipDist = $base()
            ->selectRaw('h.zip, COUNT(DISTINCT vh.household_id) AS count')
            ->whereNotNull('h.zip')
            ->where('h.zip', '!=', '')
            ->groupBy('h.zip')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        $cityDist = $base()
            ->selectRaw('h.city, COUNT(DISTINCT vh.household_id) AS count')
            ->whereNotNull('h.city')
            ->where('h.city', '!=', '')
            ->groupBy('h.city')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        // Phase 1.2.c: vehicle make on the snapshot too. This stays nullable
        // (source is nullable), so the IS NOT NULL filter remains.
        $vehicleDist = $base()
            ->selectRaw('vh.vehicle_make, COUNT(DISTINCT vh.household_id) AS count')
            ->whereNotNull('vh.vehicle_make')
            ->where('vh.vehicle_make', '!=', '')
            ->groupBy('vh.vehicle_make')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Household-type breakdown — four mutually exclusive categories drawn
        // from the children/seniors snapshot. Used for the donut + headline
        // insights ("X% of households are families with children", etc.).
        // Computed in PHP from a single one-row-per-household query so SQLite
        // tests don't trip on a CASE inside GROUP BY (portable here).
        $hhRows = $base()
            ->selectRaw('vh.household_id,
                         MAX(vh.children_count) as children_count,
                         MAX(vh.adults_count)   as adults_count,
                         MAX(vh.seniors_count)  as seniors_count,
                         MAX(vh.household_size) as household_size')
            ->groupBy('vh.household_id')
            ->get();

        $typeCounts = ['multi_gen' => 0, 'family_children' => 0, 'senior_household' => 0, 'adults_only' => 0];
        $singleSeniorHouseholds = 0;
        $largeFamilies          = 0;

        foreach ($hhRows as $r) {
            $kids    = (int) $r->children_count;
            $sen     = (int) $r->seniors_count;
            $size    = (int) $r->household_size;

            if ($kids > 0 && $sen > 0)        { $typeCounts['multi_gen']++; }
            elseif ($kids > 0)                { $typeCounts['family_children']++; }
            elseif ($sen > 0)                 { $typeCounts['senior_household']++; }
            else                              { $typeCounts['adults_only']++; }

            if ($size === 1 && $sen === 1)    { $singleSeniorHouseholds++; }
            if ($size >= 5)                   { $largeFamilies++; }
        }

        $totalTyped = max(1, array_sum($typeCounts));
        $householdTypes = [
            ['key' => 'multi_gen',        'label' => 'Multi-generational',  'count' => $typeCounts['multi_gen'],        'pct' => round($typeCounts['multi_gen']        / $totalTyped * 100, 1)],
            ['key' => 'family_children',  'label' => 'Family with children','count' => $typeCounts['family_children'],  'pct' => round($typeCounts['family_children']  / $totalTyped * 100, 1)],
            ['key' => 'senior_household', 'label' => 'Senior household',    'count' => $typeCounts['senior_household'], 'pct' => round($typeCounts['senior_household'] / $totalTyped * 100, 1)],
            ['key' => 'adults_only',      'label' => 'Working-age adults',  'count' => $typeCounts['adults_only'],      'pct' => round($typeCounts['adults_only']      / $totalTyped * 100, 1)],
        ];

        // Visit-frequency distribution — how often each household visited in
        // the period. Buckets: 1 visit, 2, 3-4, 5+. Surfaces "regulars" vs
        // one-time visitors as a service-pattern signal.
        $visitsPerHh = DB::table('visits as v')
            ->join('visit_households as vh', 'vh.visit_id', '=', 'v.id')
            ->where('v.visit_status', 'exited')
            ->whereDate('v.start_time', '>=', $fromStr)
            ->whereDate('v.start_time', '<=', $toStr)
            ->select('vh.household_id', DB::raw('COUNT(DISTINCT v.id) as visit_count'))
            ->groupBy('vh.household_id')
            ->get();

        $freqBuckets = ['1' => 0, '2' => 0, '3-4' => 0, '5+' => 0];
        foreach ($visitsPerHh as $row) {
            $n = (int) $row->visit_count;
            if ($n === 1)        { $freqBuckets['1']++; }
            elseif ($n === 2)    { $freqBuckets['2']++; }
            elseif ($n <= 4)     { $freqBuckets['3-4']++; }
            else                 { $freqBuckets['5+']++; }
        }
        $totalFreqHh = max(1, array_sum($freqBuckets));
        $visitFrequency = [
            ['label' => '1 visit',    'count' => $freqBuckets['1'],   'pct' => round($freqBuckets['1']   / $totalFreqHh * 100, 1)],
            ['label' => '2 visits',   'count' => $freqBuckets['2'],   'pct' => round($freqBuckets['2']   / $totalFreqHh * 100, 1)],
            ['label' => '3-4 visits', 'count' => $freqBuckets['3-4'], 'pct' => round($freqBuckets['3-4'] / $totalFreqHh * 100, 1)],
            ['label' => '5+ visits',  'count' => $freqBuckets['5+'],  'pct' => round($freqBuckets['5+']  / $totalFreqHh * 100, 1)],
        ];

        $totalHh = (int) $composition['households'];
        $vulnerable = [
            'single_seniors'        => $singleSeniorHouseholds,
            'single_seniors_pct'    => $totalHh > 0 ? round($singleSeniorHouseholds / $totalHh * 100, 1) : 0.0,
            'large_families'        => $largeFamilies,
            'large_families_pct'    => $totalHh > 0 ? round($largeFamilies / $totalHh * 100, 1) : 0.0,
        ];

        // Auto-generated insights — punchy plain-English bullets summarising
        // what the demographic profile reveals about who was served.
        $insights = $this->demographicsInsights(
            $composition, $householdTypes, $visitFrequency, $vulnerable, $zipDist, $cityDist
        );

        return compact(
            'sizeDist', 'composition', 'zipDist', 'cityDist', 'vehicleDist',
            'householdTypes', 'visitFrequency', 'vulnerable', 'insights'
        );
    }

    /**
     * Plain-English bullets summarising the demographics profile. Returned as
     * an array of strings so the view can render them as a list. Only emits
     * a bullet when the underlying signal is meaningful (avoids "0% of …"
     * filler in low-data periods).
     */
    private function demographicsInsights(
        array $composition,
        array $householdTypes,
        array $visitFrequency,
        array $vulnerable,
        Collection $zipDist,
        Collection $cityDist,
    ): array {
        $insights = [];

        if ($composition['households'] === 0) {
            return $insights;
        }

        // Largest household-type segment
        $largestType = collect($householdTypes)->sortByDesc('count')->first();
        if ($largestType && $largestType['count'] > 0) {
            $insights[] = sprintf(
                '%s%% of served households are %s (%s of %s).',
                $largestType['pct'],
                strtolower($largestType['label']),
                number_format($largestType['count']),
                number_format($composition['households']),
            );
        }

        // Children prevalence
        if ($composition['households_with_children_pct'] > 0) {
            $insights[] = sprintf(
                '%s%% of households include at least one child (%s households).',
                $composition['households_with_children_pct'],
                number_format($composition['households_with_children']),
            );
        }

        // Vulnerable: single seniors
        if ($vulnerable['single_seniors'] > 0) {
            $insights[] = sprintf(
                '%s households are seniors living alone (%s%% — flag for outreach).',
                number_format($vulnerable['single_seniors']),
                $vulnerable['single_seniors_pct'],
            );
        }

        // Vulnerable: large families
        if ($vulnerable['large_families'] > 0) {
            $insights[] = sprintf(
                '%s households have 5+ members (%s%% — higher per-family bag needs).',
                number_format($vulnerable['large_families']),
                $vulnerable['large_families_pct'],
            );
        }

        // Repeat-visit pattern
        $regulars = collect($visitFrequency)->whereIn('label', ['3-4 visits', '5+ visits'])->sum('count');
        $regularsPct = round($regulars / max(1, $composition['households']) * 100, 1);
        if ($regulars > 0) {
            $insights[] = sprintf(
                '%s%% of households (%s) visited 3+ times this period — a regular cohort.',
                $regularsPct,
                number_format($regulars),
            );
        }

        // Top geo
        if ($zipDist->isNotEmpty()) {
            $top = $zipDist->first();
            $insights[] = sprintf(
                'ZIP %s is the most-served area (%s households).',
                $top->zip,
                number_format($top->count),
            );
        } elseif ($cityDist->isNotEmpty()) {
            $top = $cityDist->first();
            $insights[] = sprintf(
                '%s is the most-served city (%s households).',
                $top->city,
                number_format($top->count),
            );
        }

        return $insights;
    }

    // ─── Lane Performance ─────────────────────────────────────────────────────

    public function lanePerformance(Carbon $from, Carbon $to, ?int $eventId = null): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        $query = DB::table('visits as v')
            ->join('events as e', 'e.id', '=', 'v.event_id')
            ->whereIn('e.status', ['current', 'past'])
            ->whereDate('v.start_time', '>=', $fromStr)
            ->whereDate('v.start_time', '<=', $toStr);

        if ($eventId) {
            $query->where('v.event_id', $eventId);
        }

        $lanes = (clone $query)
            ->selectRaw("
                v.lane,
                COUNT(*) AS total_visits,
                SUM(CASE WHEN v.visit_status = 'exited' THEN 1 ELSE 0 END) AS completed,
                AVG(CASE WHEN v.queued_at IS NOT NULL AND v.start_time IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, v.start_time, v.queued_at) / 60 END) AS avg_checkin_queue,
                AVG(CASE WHEN v.loading_completed_at IS NOT NULL AND v.queued_at IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, v.queued_at, v.loading_completed_at) / 60 END) AS avg_queue_loaded,
                AVG(CASE WHEN v.exited_at IS NOT NULL AND v.loading_completed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, v.loading_completed_at, v.exited_at) / 60 END) AS avg_loaded_exited,
                AVG(CASE WHEN v.exited_at IS NOT NULL AND v.start_time IS NOT NULL AND v.visit_status = 'exited'
                    THEN TIMESTAMPDIFF(SECOND, v.start_time, v.exited_at) / 60 END) AS avg_total
            ")
            ->groupBy('v.lane')
            ->orderBy('v.lane')
            ->get()
            ->map(fn ($row) => [
                'lane'                => (int) $row->lane,
                'total_visits'        => (int) $row->total_visits,
                'completed'           => (int) $row->completed,
                'completion_rate'     => $row->total_visits > 0
                    ? round($row->completed / $row->total_visits * 100)
                    : 0,
                'avg_checkin_queue'   => round((float) ($row->avg_checkin_queue ?? 0), 1),
                'avg_queue_loaded'    => round((float) ($row->avg_queue_loaded ?? 0), 1),
                'avg_loaded_exited'   => round((float) ($row->avg_loaded_exited ?? 0), 1),
                'avg_total'           => round((float) ($row->avg_total ?? 0), 1),
            ]);

        $events = Event::whereIn('status', ['current', 'past'])
            ->whereDate('date', '>=', $fromStr)
            ->whereDate('date', '<=', $toStr)
            ->orderBy('date', 'desc')
            ->get(['id', 'name', 'date']);

        return ['lanes' => $lanes->values()->all(), 'events' => $events];
    }

    // ─── Queue Flow ───────────────────────────────────────────────────────────

    public function queueFlow(Carbon $from, Carbon $to, ?int $eventId = null): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        $base = DB::table('visits as v')
            ->join('events as e', 'e.id', '=', 'v.event_id')
            ->whereIn('e.status', ['current', 'past'])
            ->whereDate('v.start_time', '>=', $fromStr)
            ->whereDate('v.start_time', '<=', $toStr);

        if ($eventId) {
            $base->where('v.event_id', $eventId);
        }

        $times = (clone $base)
            ->selectRaw("
                COUNT(*) AS total_visits,
                SUM(CASE WHEN v.visit_status = 'exited' THEN 1 ELSE 0 END) AS completed,
                AVG(CASE WHEN v.queued_at IS NOT NULL AND v.start_time IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, v.start_time, v.queued_at) / 60 END) AS avg_checkin_queue,
                AVG(CASE WHEN v.loading_completed_at IS NOT NULL AND v.queued_at IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, v.queued_at, v.loading_completed_at) / 60 END) AS avg_queue_loaded,
                AVG(CASE WHEN v.exited_at IS NOT NULL AND v.loading_completed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, v.loading_completed_at, v.exited_at) / 60 END) AS avg_loaded_exited,
                AVG(CASE WHEN v.exited_at IS NOT NULL AND v.start_time IS NOT NULL AND v.visit_status = 'exited'
                    THEN TIMESTAMPDIFF(SECOND, v.start_time, v.exited_at) / 60 END) AS avg_total,
                MIN(CASE WHEN v.exited_at IS NOT NULL AND v.start_time IS NOT NULL AND v.visit_status = 'exited'
                    THEN TIMESTAMPDIFF(SECOND, v.start_time, v.exited_at) / 60 END) AS min_total,
                MAX(CASE WHEN v.exited_at IS NOT NULL AND v.start_time IS NOT NULL AND v.visit_status = 'exited'
                    THEN TIMESTAMPDIFF(SECOND, v.start_time, v.exited_at) / 60 END) AS max_total
            ")
            ->first();

        // Hourly throughput
        $hourly = (clone $base)
            ->where('v.visit_status', 'exited')
            ->whereNotNull('v.start_time')
            ->selectRaw('HOUR(v.start_time) AS hour, COUNT(*) AS count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hourlyLabels = [];
        $hourlyCounts = [];
        if ($hourly->isNotEmpty()) {
            $minH = $hourly->min('hour');
            $maxH = $hourly->max('hour');
            $indexed = $hourly->keyBy('hour');
            for ($h = $minH; $h <= $maxH; $h++) {
                $hd = $h % 12 === 0 ? 12 : $h % 12;
                $hourlyLabels[] = $hd . ($h < 12 ? 'am' : 'pm');
                $hourlyCounts[] = $indexed->has($h) ? (int) $indexed[$h]->count : 0;
            }
        }

        // Status distribution
        $statusDist = (clone $base)
            ->selectRaw('v.visit_status, COUNT(*) AS count')
            ->groupBy('v.visit_status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->visit_status => (int) $row->count]);

        $events = Event::whereIn('status', ['current', 'past'])
            ->whereDate('date', '>=', $fromStr)
            ->whereDate('date', '<=', $toStr)
            ->orderBy('date', 'desc')
            ->get(['id', 'name', 'date']);

        return [
            'times'         => $times,
            'hourly_labels' => $hourlyLabels,
            'hourly_counts' => $hourlyCounts,
            'status_dist'   => $statusDist,
            'events'        => $events,
        ];
    }

    // ─── Volunteers ───────────────────────────────────────────────────────────

    public function volunteers(Carbon $from, Carbon $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        $totalVolunteers = Volunteer::count();

        // Volunteers who actually checked in (from volunteer_check_ins) in period
        $checkedInPeriod = DB::table('volunteer_check_ins as vci')
            ->join('events as e', 'e.id', '=', 'vci.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->distinct('vci.volunteer_id')
            ->count('vci.volunteer_id');

        $firstTimersInPeriod = DB::table('volunteer_check_ins as vci')
            ->join('events as e', 'e.id', '=', 'vci.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->where('vci.is_first_timer', true)
            ->distinct('vci.volunteer_id')
            ->count('vci.volunteer_id');

        // Kept for backward compat (assigned count still useful)
        $assignedInPeriod = DB::table('event_volunteer as ev')
            ->join('events as e', 'e.id', '=', 'ev.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->distinct('ev.volunteer_id')
            ->count('ev.volunteer_id');

        $eventParticipation = DB::table('volunteer_check_ins as vci')
            ->join('events as e', 'e.id', '=', 'vci.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->selectRaw('e.id, e.name, e.date, COUNT(vci.volunteer_id) AS volunteer_count')
            ->groupBy('e.id', 'e.name', 'e.date')
            ->orderBy('e.date', 'desc')
            ->get();

        $groups = VolunteerGroup::withCount('volunteers')
            ->orderByDesc('volunteers_count')
            ->get();

        $groupParticipation = DB::table('volunteer_group_memberships as vgm')
            ->join('volunteer_groups as vg',  'vg.id', '=', 'vgm.group_id')
            ->join('volunteer_check_ins as vci', 'vci.volunteer_id', '=', 'vgm.volunteer_id')
            ->join('events as e',             'e.id', '=', 'vci.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->selectRaw('vg.name, COUNT(DISTINCT vci.volunteer_id) AS vol_count, COUNT(DISTINCT vci.event_id) AS event_count')
            ->groupBy('vg.id', 'vg.name')
            ->orderByDesc('vol_count')
            ->get();

        // All-time check-in stats per volunteer (used for service frequency table)
        $allTimeStats = DB::table('volunteer_check_ins as vci')
            ->join('events as e', 'e.id', '=', 'vci.event_id')
            ->selectRaw('
                vci.volunteer_id,
                COUNT(vci.id)                       AS total_events,
                MIN(e.date)                         AS first_service,
                MAX(e.date)                         AS last_service,
                COALESCE(SUM(vci.hours_served), 0)  AS total_hours
            ')
            ->groupBy('vci.volunteer_id')
            ->get()
            ->keyBy('volunteer_id');

        // In-period check-in count + hours per volunteer
        $periodStats = DB::table('volunteer_check_ins as vci')
            ->join('events as e', 'e.id', '=', 'vci.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->selectRaw('vci.volunteer_id, COUNT(vci.id) AS event_count, COALESCE(SUM(vci.hours_served), 0) AS hours')
            ->groupBy('vci.volunteer_id')
            ->get()
            ->keyBy('volunteer_id');

        $periodCounts = $periodStats->pluck('event_count', 'volunteer_id');

        // Total hours served across all volunteers in the period
        $totalHoursInPeriod = round((float) $periodStats->sum('hours'), 1);

        $allVolunteers = Volunteer::with('groups')
            ->orderBy('last_name')
            ->get()
            ->map(function ($vol) use ($allTimeStats, $periodCounts, $periodStats) {
                $s = $allTimeStats->get($vol->id);
                $p = $periodStats->get($vol->id);
                return [
                    'id'               => $vol->id,
                    'name'             => $vol->full_name,
                    'role'             => $vol->role ?? '—',
                    'groups'           => $vol->groups->pluck('name')->implode(', ') ?: '—',
                    'events_in_period' => (int) ($periodCounts->get($vol->id, 0)),
                    'hours_in_period'  => $p ? round((float) $p->hours, 1) : 0,
                    'total_events'     => $s ? (int) $s->total_events : 0,
                    'total_hours'      => $s ? round((float) $s->total_hours, 1) : 0,
                    'first_service'    => $s?->first_service,
                    'last_service'     => $s?->last_service,
                    'is_first_timer'   => $s ? ((int) $s->total_events <= 1) : true,
                ];
            })
            ->sortByDesc('total_events')
            ->values();

        // Top 5 most active volunteers all-time
        $topVolunteers = $allVolunteers->take(5);

        return compact(
            'totalVolunteers',
            'checkedInPeriod',
            'firstTimersInPeriod',
            'assignedInPeriod',
            'totalHoursInPeriod',
            'eventParticipation',
            'groups',
            'groupParticipation',
            'allVolunteers',
            'topVolunteers'
        );
    }

    // ─── Reviews ─────────────────────────────────────────────────────────────

    public function reviews(Carbon $from, Carbon $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        $base = DB::table('event_reviews as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr);

        $overall = (clone $base)
            ->selectRaw('COUNT(*) AS total, AVG(er.rating) AS avg_rating')
            ->first();

        $ratingDist = (clone $base)
            ->selectRaw('er.rating, COUNT(*) AS count')
            ->groupBy('er.rating')
            ->orderBy('er.rating')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->rating => (int) $r->count]);

        $byEvent = (clone $base)
            ->selectRaw('e.id, e.name, e.date, COUNT(er.id) AS review_count,
                         AVG(er.rating) AS avg_rating, MIN(er.rating) AS min_r, MAX(er.rating) AS max_r')
            ->groupBy('e.id', 'e.name', 'e.date')
            ->orderByDesc('avg_rating')
            ->get();

        [$groupExpr, $labelExpr] = $this->groupingExpressions($from, $to, 'e.date');

        $trend = DB::select("
            SELECT {$labelExpr} AS label, {$groupExpr} AS period,
                   COUNT(er.id) AS count, AVG(er.rating) AS avg_rating
            FROM event_reviews er
            JOIN events e ON e.id = er.event_id
            WHERE DATE(e.date) BETWEEN ? AND ?
            GROUP BY period, label
            ORDER BY period ASC
        ", [$fromStr, $toStr]);

        $allReviews = EventReview::with('event')
            ->whereHas('event', fn ($q) => $q
                ->whereDate('date', '>=', $fromStr)
                ->whereDate('date', '<=', $toStr))
            ->latest()
            ->get();

        return compact('overall', 'ratingDist', 'byEvent', 'trend', 'allReviews');
    }

    // ─── Insights ────────────────────────────────────────────────────────────

    public function insights(Carbon $from, Carbon $to, array $overview): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');
        $insights = [];

        // 1. Compare households vs previous period
        $days    = max(1, (int) $from->diffInDays($to));
        $prevFrom = $from->copy()->subDays($days)->format('Y-m-d');
        $prevTo   = $from->copy()->subDay()->format('Y-m-d');

        $prevHouseholds = DB::table('visit_households as vh')
            ->join('visits as v', 'v.id', '=', 'vh.visit_id')
            ->where('v.visit_status', 'exited')
            ->whereDate('v.start_time', '>=', $prevFrom)
            ->whereDate('v.start_time', '<=', $prevTo)
            ->distinct('vh.household_id')
            ->count('vh.household_id');

        if ($prevHouseholds > 0 && $overview['households_served'] > 0) {
            $pct = (($overview['households_served'] - $prevHouseholds) / $prevHouseholds) * 100;
            $dir = $pct >= 0 ? 'increased' : 'decreased';
            $color = $pct >= 0 ? 'green' : 'red';
            $insights[] = [
                'icon'  => $pct >= 0 ? 'trending-up' : 'trending-down',
                'color' => $color,
                'text'  => sprintf(
                    'Households served %s %.0f%% compared to the previous period (%d vs %d).',
                    $dir, abs($pct), $overview['households_served'], $prevHouseholds
                ),
            ];
        } elseif ($overview['households_served'] > 0) {
            $insights[] = [
                'icon'  => 'trending-up',
                'color' => 'blue',
                'text'  => sprintf('%d households served — first period with recorded data.', $overview['households_served']),
            ];
        }

        // 2. Peak hour
        $peakHour = DB::table('visits')
            ->where('visit_status', 'exited')
            ->whereDate('start_time', '>=', $fromStr)
            ->whereDate('start_time', '<=', $toStr)
            ->whereNotNull('start_time')
            ->selectRaw('HOUR(start_time) AS hour, COUNT(*) AS cnt')
            ->groupBy('hour')
            ->orderByDesc('cnt')
            ->first();

        if ($peakHour) {
            $h   = (int) $peakHour->hour;
            $hd  = ($h === 0 ? 12 : ($h > 12 ? $h - 12 : $h));
            $fmt = $hd . ($h < 12 ? ' AM' : ' PM');
            $insights[] = [
                'icon'  => 'clock',
                'color' => 'blue',
                'text'  => "Peak service hour was {$fmt} with {$peakHour->cnt} completed visits.",
            ];
        }

        // 3. Top ZIP code
        $topZip = DB::table('visit_households as vh')
            ->join('visits as v',     'v.id', '=', 'vh.visit_id')
            ->join('households as h', 'h.id', '=', 'vh.household_id')
            ->where('v.visit_status', 'exited')
            ->whereDate('v.start_time', '>=', $fromStr)
            ->whereDate('v.start_time', '<=', $toStr)
            ->whereNotNull('h.zip')
            ->where('h.zip', '!=', '')
            ->selectRaw('h.zip, COUNT(DISTINCT vh.household_id) AS cnt')
            ->groupBy('h.zip')
            ->orderByDesc('cnt')
            ->first();

        if ($topZip) {
            $insights[] = [
                'icon'  => 'map-pin',
                'color' => 'purple',
                'text'  => "ZIP code {$topZip->zip} had the highest households served ({$topZip->cnt}).",
            ];
        }

        // 4. Fastest lane
        $bestLane = DB::table('visits')
            ->where('visit_status', 'exited')
            ->whereDate('start_time', '>=', $fromStr)
            ->whereDate('start_time', '<=', $toStr)
            ->whereNotNull('exited_at')
            ->whereNotNull('start_time')
            ->selectRaw('lane, COUNT(*) AS cnt, AVG(TIMESTAMPDIFF(SECOND, start_time, exited_at)) / 60 AS avg_mins')
            ->groupBy('lane')
            ->havingRaw('cnt >= 3')
            ->orderBy('avg_mins')
            ->first();

        if ($bestLane) {
            $insights[] = [
                'icon'  => 'bolt',
                'color' => 'orange',
                'text'  => sprintf(
                    'Lane %d had the fastest average service time (%.1f min, %d visits).',
                    $bestLane->lane, $bestLane->avg_mins, $bestLane->cnt
                ),
            ];
        }

        // 5. Top-rated event
        $topRated = DB::table('event_reviews as er')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->whereDate('e.date', '>=', $fromStr)
            ->whereDate('e.date', '<=', $toStr)
            ->selectRaw('e.name, AVG(er.rating) AS avg_rating, COUNT(er.id) AS cnt')
            ->groupBy('e.id', 'e.name')
            ->havingRaw('cnt >= 2')
            ->orderByDesc('avg_rating')
            ->first();

        if ($topRated) {
            $insights[] = [
                'icon'  => 'star',
                'color' => 'yellow',
                'text'  => sprintf(
                    '"%s" received the highest average rating (%.1f ★ from %d reviews).',
                    $topRated->name, $topRated->avg_rating, $topRated->cnt
                ),
            ];
        }

        return array_slice($insights, 0, 5);
    }

    // ─── Export Data ──────────────────────────────────────────────────────────

    public function exportEvents(Carbon $from, Carbon $to): array
    {
        $events = $this->eventPerformance($from, $to);
        return [
            'headers' => ['Event', 'Date', 'Location', 'Lanes', 'Total Visits', 'Households Served',
                          'People Served', 'Bags Distributed', 'Completion %', 'Avg Service Time (min)',
                          'Avg Rating', 'Reviews'],
            'rows' => array_map(fn ($e) => [
                $e['name'], $e['date'], $e['location'], $e['lanes'], $e['total_visits'],
                $e['households_served'], $e['people_served'], $e['bags_distributed'],
                $e['completion_rate'], $e['avg_service_time'], $e['avg_rating'] ?? '', $e['review_count'],
            ], $events),
        ];
    }

    public function exportVisits(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('visits as v')
            ->join('events as e', 'e.id', '=', 'v.event_id')
            ->leftJoin('visit_households as vh', 'vh.visit_id', '=', 'v.id')
            ->leftJoin('households as h', 'h.id', '=', 'vh.household_id')
            ->whereDate('v.start_time', '>=', $from)
            ->whereDate('v.start_time', '<=', $to)
            // Phase 1.2.c: people-count comes from the pivot snapshot
            // (`vh.household_size`); zip/city/name remain live on `h` since
            // they're not snapshotted (and represent current contact info).
            ->selectRaw("
                v.id, e.name AS event_name, e.date AS event_date, v.lane, v.visit_status,
                h.household_number, CONCAT(h.first_name, ' ', h.last_name) AS household_name,
                vh.household_size, h.zip, h.city,
                v.start_time, v.queued_at, v.loading_completed_at, v.exited_at, v.served_bags,
                TIMESTAMPDIFF(SECOND, v.start_time, v.queued_at) / 60 AS checkin_queue_min,
                TIMESTAMPDIFF(SECOND, v.queued_at, v.loading_completed_at) / 60 AS queue_loaded_min,
                TIMESTAMPDIFF(SECOND, v.loading_completed_at, v.exited_at) / 60 AS loaded_exited_min,
                CASE WHEN v.exited_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, v.start_time, v.exited_at) / 60 END AS total_min
            ")
            ->orderBy('v.start_time')
            ->get();

        return [
            'headers' => ['Visit ID', 'Event', 'Event Date', 'Lane', 'Status',
                          'Household #', 'Name', 'People', 'ZIP', 'City',
                          'Check-in', 'Queued At', 'Loaded At', 'Exited At', 'Bags',
                          'Checkin→Queue (min)', 'Queue→Load (min)', 'Load→Exit (min)', 'Total (min)'],
            'rows' => $rows->map(fn ($r) => [
                $r->id, $r->event_name, $r->event_date, $r->lane, $r->visit_status,
                $r->household_number, $r->household_name, $r->household_size, $r->zip, $r->city,
                $r->start_time, $r->queued_at, $r->loading_completed_at, $r->exited_at, $r->served_bags,
                round($r->checkin_queue_min ?? 0, 1),
                round($r->queue_loaded_min ?? 0, 1),
                round($r->loaded_exited_min ?? 0, 1),
                round($r->total_min ?? 0, 1),
            ])->all(),
        ];
    }

    /**
     * Current-roster export — DELIBERATELY reads live `h.*` rather than the
     * `vh.*` snapshot. Each row represents one household's CURRENT state
     * (size, demographics, contact info) plus their visit/bag totals over
     * the period. Switching to the pivot snapshot would fragment rows by
     * snapshot variations (a household whose size changed mid-period would
     * appear as multiple rows) and lose the "who they are now" semantic
     * this export is built for. The analytical reports (overview, trends,
     * eventPerformance, demographics) are the ones that read from the
     * snapshot — see Phase 1.2.c notes in AUDIT_REPORT.md Part 13 §1.2.
     */
    public function exportHouseholds(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('households as h')
            ->join('visit_households as vh', 'vh.household_id', '=', 'h.id')
            ->join('visits as v', 'v.id', '=', 'vh.visit_id')
            ->where('v.visit_status', 'exited')
            ->whereDate('v.start_time', '>=', $from)
            ->whereDate('v.start_time', '<=', $to)
            ->selectRaw("
                h.household_number, CONCAT(h.first_name, ' ', h.last_name) AS name,
                h.email, h.phone, h.city, h.state, h.zip,
                h.household_size, h.children_count, h.adults_count, h.seniors_count,
                COUNT(v.id) AS visits_in_period,
                SUM(v.served_bags) AS total_bags,
                MIN(v.start_time) AS first_visit,
                MAX(v.start_time) AS last_visit
            ")
            ->groupBy('h.id', 'h.household_number', 'h.first_name', 'h.last_name',
                      'h.email', 'h.phone', 'h.city', 'h.state', 'h.zip',
                      'h.household_size', 'h.children_count', 'h.adults_count', 'h.seniors_count')
            ->orderBy('h.last_name')
            ->get();

        return [
            'headers' => ['Household #', 'Name', 'Email', 'Phone', 'City', 'State', 'ZIP',
                          'People', 'Children', 'Adults', 'Seniors',
                          'Visits in Period', 'Bags Received', 'First Visit', 'Last Visit'],
            'rows' => $rows->map(fn ($r) => [
                $r->household_number, $r->name, $r->email, $r->phone,
                $r->city, $r->state, $r->zip,
                $r->household_size, $r->children_count, $r->adults_count, $r->seniors_count,
                $r->visits_in_period, $r->total_bags,
                $r->first_visit, $r->last_visit,
            ])->all(),
        ];
    }

    public function exportReviews(Carbon $from, Carbon $to): array
    {
        $rows = EventReview::with('event')
            ->whereHas('event', fn ($q) => $q
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to))
            ->orderByDesc('created_at')
            ->get();

        return [
            'headers' => ['Event', 'Event Date', 'Rating', 'Review', 'Reviewer', 'Email', 'Submitted'],
            'rows' => $rows->map(fn ($r) => [
                $r->event?->name, $r->event?->date?->format('Y-m-d'),
                $r->rating, $r->review_text, $r->reviewer_name, $r->email,
                $r->created_at?->format('Y-m-d H:i'),
            ])->all(),
        ];
    }

    public function exportDemographics(Carbon $from, Carbon $to): array
    {
        $data = $this->demographics($from, $to);

        return [
            'headers' => ['ZIP Code', 'Households Served'],
            'rows' => $data['zipDist']->map(fn ($r) => [$r->zip, $r->count])->all(),
        ];
    }

    public function exportVolunteers(Carbon $from, Carbon $to): array
    {
        $data = $this->volunteers($from, $to);

        return [
            'headers' => ['Name', 'Role', 'Groups', 'Events in Period', 'Hours in Period', 'Total Events', 'Total Hours', 'First Service', 'Last Service', 'Status'],
            'rows' => $data['allVolunteers']->map(fn ($v) => [
                $v['name'],
                $v['role'],
                $v['groups'],
                $v['events_in_period'],
                $v['hours_in_period'],
                $v['total_events'],
                $v['total_hours'],
                $v['first_service'] ?? '',
                $v['last_service']  ?? '',
                $v['is_first_timer'] ? 'First Timer' : 'Returning',
            ])->all(),
        ];
    }

    /**
     * Households whose FIRST event falls within the date range. Mirrors the
     * three-subquery pattern in ReportsController::firstTimers so the CSV
     * agrees row-for-row with the on-screen list.
     */
    public function exportFirstTimers(Carbon $from, Carbon $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        $firstDateSql = '(SELECT MIN(e2.date) FROM visit_households vh2
                           JOIN visits v2 ON vh2.visit_id = v2.id
                           JOIN events e2 ON v2.event_id = e2.id
                           WHERE vh2.household_id = households.id)';

        $rows = DB::table('households')
            ->select('households.household_number',
                     'households.first_name',
                     'households.last_name',
                     'households.phone',
                     'households.email',
                     'households.city',
                     'households.zip',
                     'households.representative_household_id')
            ->selectRaw("{$firstDateSql} AS first_event_date")
            ->selectRaw('(SELECT e2.name FROM events e2
                          JOIN visits v2 ON v2.event_id = e2.id
                          JOIN visit_households vh2 ON vh2.visit_id = v2.id
                          WHERE vh2.household_id = households.id
                          ORDER BY e2.date ASC LIMIT 1) AS first_event_name')
            ->selectRaw('(SELECT COUNT(DISTINCT v2.event_id) FROM visit_households vh2
                          JOIN visits v2 ON vh2.visit_id = v2.id
                          WHERE vh2.household_id = households.id) AS total_events_attended')
            ->whereRaw("{$firstDateSql} BETWEEN ? AND ?", [$fromStr, $toStr])
            ->orderByRaw("{$firstDateSql} ASC")
            ->get();

        return [
            'headers' => [
                'Household #', 'First Name', 'Last Name', 'Phone', 'Email',
                'City', 'ZIP', 'First Event Date', 'First Event', 'Events Attended', 'Represented',
            ],
            'rows' => $rows->map(fn ($r) => [
                $r->household_number,
                $r->first_name,
                $r->last_name,
                $r->phone,
                $r->email,
                $r->city,
                $r->zip,
                $r->first_event_date,
                $r->first_event_name,
                (int) $r->total_events_attended,
                $r->representative_household_id ? 'Yes' : 'No',
            ])->all(),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function groupingExpressions(Carbon $from, Carbon $to, string $col): array
    {
        $days = max(1, (int) $from->diffInDays($to));

        if ($days <= 14) {
            return [
                "DATE({$col})",
                "DATE_FORMAT({$col}, '%b %d')",
            ];
        }

        if ($days <= 90) {
            return [
                "YEARWEEK({$col}, 1)",
                "CONCAT(DATE_FORMAT(DATE_SUB({$col}, INTERVAL (DAYOFWEEK({$col}) - 2 + 7) % 7 DAY), '%b %d'), '–', DATE_FORMAT(DATE_ADD(DATE_SUB({$col}, INTERVAL (DAYOFWEEK({$col}) - 2 + 7) % 7 DAY), INTERVAL 6 DAY), '%b %d'))",
            ];
        }

        return [
            "DATE_FORMAT({$col}, '%Y-%m')",
            "DATE_FORMAT({$col}, '%b %Y')",
        ];
    }

    private function newReturningCount(string $fromStr, string $toStr): array
    {
        // Households that exited in the current period
        $inPeriod = DB::table('visit_households as vh')
            ->join('visits as v', 'v.id', '=', 'vh.visit_id')
            ->where('v.visit_status', 'exited')
            ->whereDate('v.start_time', '>=', $fromStr)
            ->whereDate('v.start_time', '<=', $toStr)
            ->distinct()
            ->pluck('vh.household_id');

        if ($inPeriod->isEmpty()) {
            return ['new' => 0, 'returning' => 0];
        }

        $returning = DB::table('visit_households as vh')
            ->join('visits as v', 'v.id', '=', 'vh.visit_id')
            ->where('v.visit_status', 'exited')
            ->whereDate('v.start_time', '<', $fromStr)
            ->whereIn('vh.household_id', $inPeriod)
            ->distinct()
            ->count('vh.household_id');

        return [
            'new'       => max(0, $inPeriod->count() - $returning),
            'returning' => $returning,
        ];
    }
}
