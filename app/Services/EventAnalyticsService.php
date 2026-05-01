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

        $peopleSumFn = fn ($v) => $v->households->sum('household_size');

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
                $household = $v->households->first();

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
                    'household_number'  => $household?->household_number ?? '—',
                    'full_name'         => $household?->full_name ?? '—',
                    'household_size'    => $household?->household_size ?? 0,
                    'children_count'    => $household?->children_count ?? 0,
                    'adults_count'      => $household?->adults_count ?? 0,
                    'seniors_count'     => $household?->seniors_count ?? 0,
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
            'processTime'    => $this->processTimeChart($visits),
            'hourlyCheckins' => $this->hourlyCheckinsChart($visits, $event),
            'lanePerformance'=> $this->lanePerformanceChart($visits, $event),
        ];
    }

    // ─── Private chart builders ────────────────────────────────────────────────

    private function processTimeChart(Collection $visits): array
    {
        $checkinToQueue = $visits
            ->filter(fn ($v) => $v->queued_at && $v->start_time)
            ->avg(fn ($v) => $v->start_time->diffInSeconds($v->queued_at) / 60);

        $queueToLoaded = $visits
            ->filter(fn ($v) => $v->loading_completed_at && $v->queued_at)
            ->avg(fn ($v) => $v->queued_at->diffInSeconds($v->loading_completed_at) / 60);

        $loadedToExited = $visits
            ->filter(fn ($v) => $v->exited_at && $v->loading_completed_at)
            ->avg(fn ($v) => $v->loading_completed_at->diffInSeconds($v->exited_at) / 60);

        return [
            'labels' => ['Check-in → Queue', 'Queue → Loading', 'Loading → Exit'],
            'values' => [
                round($checkinToQueue ?? 0, 1),
                round($queueToLoaded ?? 0, 1),
                round($loadedToExited ?? 0, 1),
            ],
        ];
    }

    private function hourlyCheckinsChart(Collection $visits, Event $event): array
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
    // Projects how many households will actually walk through the door for
    // an event by combining its current pre-registration count with the
    // historical walk-in rate from the last three past events.
    //
    // Math:
    //   N            = up to 3 past events (date < $event->date), most recent
    //   attended_n   = distinct households at exited visits for past event n
    //   pre_reg_n    = pre-registration count for past event n
    //   walk_in_n    = max(0, attended_n - pre_reg_n)
    //
    //   walk_in_rate = sum(walk_in_n) / sum(attended_n)            (0 if no att.)
    //   avg_attended = sum(attended_n) / N
    //
    //   if walk_in_rate < 1:
    //       projected_total = current_pre_reg / (1 - walk_in_rate)
    //   else:
    //       projected_total = avg_attended      // pathological 100%-walk-in
    //
    //   forecast            = max(projected_total, avg_attended)
    //   projected_walk_ins  = max(0, forecast - current_pre_reg)
    //
    // When N === 0, returns enabled=false; the view shows a "not enough
    // history yet" placeholder card instead of a fake forecast.
    public function attendeeForecast(Event $event): array
    {
        $pastEvents = Event::query()
            ->where('id', '!=', $event->id)
            ->where('date', '<', $event->date)
            ->orderByDesc('date')
            ->limit(3)
            ->get();

        $pastEventCount = $pastEvents->count();
        $currentPreReg  = $event->preRegistrations()->count();

        if ($pastEventCount === 0) {
            return [
                'enabled'            => false,
                'past_events_used'   => 0,
                'projected_total'    => 0,
                'projected_walk_ins' => 0,
                'avg_attended'       => 0,
                'walk_in_rate'       => 0.0,
                'current_pre_reg'    => $currentPreReg,
            ];
        }

        $totalAttended = 0;
        $totalWalkIns  = 0;

        foreach ($pastEvents as $past) {
            $attended = (int) DB::table('visit_households')
                ->join('visits', 'visit_households.visit_id', '=', 'visits.id')
                ->where('visits.event_id', $past->id)
                ->where('visits.visit_status', 'exited')
                ->distinct()
                ->count('visit_households.household_id');

            $preReg  = $past->preRegistrations()->count();
            $walkIns = max(0, $attended - $preReg);

            $totalAttended += $attended;
            $totalWalkIns  += $walkIns;
        }

        $avgAttended = (int) round($totalAttended / $pastEventCount);
        $walkInRate  = $totalAttended > 0 ? $totalWalkIns / $totalAttended : 0.0;

        if ($walkInRate < 1) {
            $projectedTotal = (int) round($currentPreReg / (1 - $walkInRate));
        } else {
            $projectedTotal = $avgAttended;
        }

        // Floor at the historical baseline so a low-pre-reg week does not
        // under-staff a venue that always pulls 100+ regardless of sign-ups.
        $projectedTotal   = max($projectedTotal, $avgAttended);
        $projectedWalkIns = max(0, $projectedTotal - $currentPreReg);

        return [
            'enabled'            => true,
            'past_events_used'   => $pastEventCount,
            'projected_total'    => $projectedTotal,
            'projected_walk_ins' => $projectedWalkIns,
            'avg_attended'       => $avgAttended,
            'walk_in_rate'       => round($walkInRate, 3),
            'current_pre_reg'    => $currentPreReg,
        ];
    }
}
