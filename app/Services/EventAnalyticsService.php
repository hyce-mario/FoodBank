<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventAnalyticsService
{
    // ─── Summary KPIs ─────────────────────────────────────────────────────────

    public function summary(Event $event): array
    {
        $visits = Visit::with('households')
            ->where('event_id', $event->id)
            ->get();

        $exited = $visits->where('visit_status', 'exited');

        // Timing averages (minutes)
        $checkinToQueue = $visits
            ->filter(fn ($v) => $v->queued_at && $v->start_time)
            ->avg(fn ($v) => $v->start_time->diffInSeconds($v->queued_at) / 60);

        $queueToLoaded = $visits
            ->filter(fn ($v) => $v->loading_completed_at && $v->queued_at)
            ->avg(fn ($v) => $v->queued_at->diffInSeconds($v->loading_completed_at) / 60);

        $loadedToExited = $exited
            ->filter(fn ($v) => $v->exited_at && $v->loading_completed_at)
            ->avg(fn ($v) => $v->loading_completed_at->diffInSeconds($v->exited_at) / 60);

        $totalTime = $exited
            ->filter(fn ($v) => $v->exited_at && $v->start_time)
            ->avg(fn ($v) => $v->start_time->diffInSeconds($v->exited_at) / 60);

        // Phase 1.2.c: people-served sums the pivot snapshot (vh.household_size),
        // not the live households.household_size. Without this, editing a
        // household after their visit silently rewrites historical reports.
        // ReportAnalyticsService::eventReport() already does this; this service
        // was missed in the original 1.2.c sweep.
        $peopleSumFn = fn ($v) => $v->households->sum(fn ($h) => (int) ($h->pivot->household_size ?? 0));

        return [
            'total_visits'          => $visits->count(),
            'households_served'     => $exited->count(),
            'people_served'         => $exited->sum($peopleSumFn),
            'bags_distributed'      => $exited->sum('served_bags'),
            'avg_checkin_to_queue'  => round($checkinToQueue ?? 0, 1),
            'avg_queue_to_loaded'   => round($queueToLoaded ?? 0, 1),
            'avg_loaded_to_exited'  => round($loadedToExited ?? 0, 1),
            'avg_total_time'        => round($totalTime ?? 0, 1),
            // Status breakdown counts
            'status_counts'         => [
                'checked_in' => $visits->where('visit_status', 'checked_in')->count(),
                'queued'     => $visits->where('visit_status', 'queued')->count(),
                'loading'    => $visits->where('visit_status', 'loading')->count(),
                'loaded'     => $visits->where('visit_status', 'loaded')->count(),
                'exited'     => $exited->count(),
            ],
        ];
    }

    // ─── Detail rows for the visits table ─────────────────────────────────────

    public function visitsDetail(Event $event): Collection
    {
        return Visit::with('households')
            ->where('event_id', $event->id)
            ->orderBy('start_time')
            ->get()
            ->map(function (Visit $v) {
                $primary         = $v->households->first();
                $householdCount  = $v->households->count();

                // Phase 1.2.c: read demographics from the pivot snapshot, sum
                // across ALL households on the visit so a representative
                // pickup (one visit, multiple families) reconciles with the
                // people-served KPI in summary().
                $householdSize  = (int) $v->households->sum(fn ($h) => (int) ($h->pivot->household_size ?? 0));
                $childrenCount  = (int) $v->households->sum(fn ($h) => (int) ($h->pivot->children_count ?? 0));
                $adultsCount    = (int) $v->households->sum(fn ($h) => (int) ($h->pivot->adults_count   ?? 0));
                $seniorsCount   = (int) $v->households->sum(fn ($h) => (int) ($h->pivot->seniors_count  ?? 0));

                $checkinToQueue = ($v->queued_at && $v->start_time)
                    ? (int) round($v->start_time->diffInSeconds($v->queued_at) / 60)
                    : null;

                $queueToLoaded = ($v->loading_completed_at && $v->queued_at)
                    ? (int) round($v->queued_at->diffInSeconds($v->loading_completed_at) / 60)
                    : null;

                $loadedToExited = ($v->exited_at && $v->loading_completed_at)
                    ? (int) round($v->loading_completed_at->diffInSeconds($v->exited_at) / 60)
                    : null;

                $totalTime = ($v->exited_at && $v->start_time)
                    ? (int) round($v->start_time->diffInSeconds($v->exited_at) / 60)
                    : null;

                return (object) [
                    'id'                => $v->id,
                    'lane'              => $v->lane,
                    'visit_status'      => $v->visit_status,
                    'status_label'      => $v->statusLabel(),
                    'served_bags'       => $v->served_bags,
                    'start_time'        => $v->start_time,
                    'queued_at'         => $v->queued_at,
                    'loading_completed_at' => $v->loading_completed_at,
                    'exited_at'         => $v->exited_at,
                    'checkin_to_queue'  => $checkinToQueue,
                    'queue_to_loaded'   => $queueToLoaded,
                    'loaded_to_exited'  => $loadedToExited,
                    'total_time'        => $totalTime,
                    'household_number'  => $primary?->household_number ?? '—',
                    'full_name'         => $primary?->full_name ?? '—',
                    'household_count'   => $householdCount,
                    'additional_count'  => max(0, $householdCount - 1),
                    'household_size'    => $householdSize,
                    'children_count'    => $childrenCount,
                    'adults_count'      => $adultsCount,
                    'seniors_count'     => $seniorsCount,
                ];
            });
    }

    // ─── Chart data ───────────────────────────────────────────────────────────

    public function chartData(Event $event): array
    {
        $visits = Visit::with('households')
            ->where('event_id', $event->id)
            ->get();

        return [
            'hourlyCheckins'  => $this->hourlyCheckinsChart($visits),
            'lanePerformance' => $this->lanePerformanceChart($visits, $event),
        ];
    }

    // ─── Private chart builders ────────────────────────────────────────────────

    private function hourlyCheckinsChart(Collection $visits): array
    {
        $withTime = $visits->filter(fn ($v) => $v->start_time !== null);

        if ($withTime->isEmpty()) {
            return ['labels' => [], 'values' => []];
        }

        // Dynamic range: earliest to latest check-in hour, padded by 1 hour each side
        $minHour = max(0,  $withTime->min(fn ($v) => $v->start_time->hour) - 1);
        $maxHour = min(23, $withTime->max(fn ($v) => $v->start_time->hour) + 1);

        $labels = [];
        $counts = [];

        for ($h = $minHour; $h <= $maxHour; $h++) {
            $hDisplay = $h % 12 === 0 ? 12 : $h % 12;
            $labels[] = $hDisplay . ($h < 12 ? 'am' : 'pm');
            $counts[] = $withTime->filter(fn ($v) => $v->start_time->hour === $h)->count();
        }

        return ['labels' => $labels, 'values' => $counts];
    }

    private function lanePerformanceChart(Collection $visits, Event $event): array
    {
        $lanes  = range(1, max($event->lanes, 1));
        $labels = array_map(fn ($l) => "Lane $l", $lanes);
        $values = array_map(function ($lane) use ($visits) {
            return $visits->where('lane', $lane)->count();
        }, $lanes);

        return ['labels' => $labels, 'values' => $values];
    }

    // ─── Attendee forecast (Phase C.2) ────────────────────────────────────────
    //
    // Projects how many people will actually walk through the door for an
    // event by combining its current pre-registration count with the
    // walk-in rate from the most recent past event.
    //
    // Math:
    //   last_visits  = total visits at the most recent past event
    //   last_prereg  = pre-registration count for that event
    //   walk_in_rate = max(0, last_visits - last_prereg) / last_visits
    //                  (0.0 when last_visits == 0 — pathological)
    //
    //   if walk_in_rate < 1:
    //       projected_total = current_pre_reg / (1 - walk_in_rate)
    //   else:
    //       projected_total = last_visits      // 100% walk-in: historical
    //
    //   forecast            = max(projected_total, last_visits)
    //   projected_walk_ins  = max(0, forecast - current_pre_reg)
    //
    // The forecast floors at last_visits so a quiet pre-reg week does not
    // under-staff a venue that always pulls a comparable crowd. When no
    // past event exists, returns enabled=false and the view shows a "not
    // enough history yet" placeholder.
    public function attendeeForecast(Event $event): array
    {
        $lastEvent = Event::query()
            ->where('id', '!=', $event->id)
            ->where('date', '<', $event->date)
            ->orderByDesc('date')
            ->first();

        $currentPreReg = $event->preRegistrations()->count();

        if (! $lastEvent) {
            return [
                'enabled'            => false,
                'last_event_visits'  => 0,
                'projected_total'    => 0,
                'projected_walk_ins' => 0,
                'walk_in_rate'       => 0.0,
                'current_pre_reg'    => $currentPreReg,
            ];
        }

        $lastVisits = (int) DB::table('visits')
            ->where('event_id', $lastEvent->id)
            ->count();

        $lastPreReg = $lastEvent->preRegistrations()->count();
        $walkIns    = max(0, $lastVisits - $lastPreReg);
        $walkInRate = $lastVisits > 0 ? $walkIns / $lastVisits : 0.0;

        if ($walkInRate < 1) {
            $projectedTotal = (int) round($currentPreReg / (1 - $walkInRate));
        } else {
            $projectedTotal = $lastVisits;
        }

        // Floor at last event's visit count so the forecast never undershoots
        // the venue's known capacity from one event ago.
        $projectedTotal   = max($projectedTotal, $lastVisits);
        $projectedWalkIns = max(0, $projectedTotal - $currentPreReg);

        return [
            'enabled'            => true,
            'last_event_visits'  => $lastVisits,
            'projected_total'    => $projectedTotal,
            'projected_walk_ins' => $projectedWalkIns,
            'walk_in_rate'       => round($walkInRate, 3),
            'current_pre_reg'    => $currentPreReg,
        ];
    }
}
