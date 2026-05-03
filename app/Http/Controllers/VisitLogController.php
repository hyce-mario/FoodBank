<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitLogController extends Controller
{
    public function __construct(
        protected EventAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        // Current first, then past (descending date); never show upcoming
        $events = Event::whereIn('status', ['current', 'past'])
            ->orderByRaw("CASE WHEN status = 'current' THEN 0 ELSE 1 END")
            ->orderBy('date', 'desc')
            ->get();

        $selectedEvent = $request->filled('event_id')
            ? Event::whereIn('status', ['current', 'past'])->findOrFail($request->event_id)
            : $events->first();

        if (! $selectedEvent) {
            return view('visit-log.index', [
                'events'        => $events,
                'selectedEvent' => null,
                'summary'       => null,
                'visits'        => collect(),
                'chartData'     => null,
            ]);
        }

        $summary   = $this->analytics->summary($selectedEvent);
        $visits    = $this->analytics->visitsDetail($selectedEvent);
        $chartData = $this->analytics->chartData($selectedEvent);

        return view('visit-log.index', compact('events', 'selectedEvent', 'summary', 'visits', 'chartData'));
    }

    public function print(Request $request): View
    {
        $event   = Event::whereIn('status', ['current', 'past'])->findOrFail($request->event_id);
        $summary = $this->analytics->summary($event);
        $visits  = $this->applyFilters($this->analytics->visitsDetail($event), $request);
        $filters = $this->activeFilters($request, $event);

        return view('visit-log.print', compact('event', 'summary', 'visits', 'filters'));
    }

    public function export(Request $request): StreamedResponse
    {
        $event  = Event::whereIn('status', ['current', 'past'])->findOrFail($request->event_id);
        $visits = $this->applyFilters($this->analytics->visitsDetail($event), $request);

        $filename = 'visit-log-' . $event->date->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($visits) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Visit ID', 'Household #', 'Name', 'Lane', 'Status',
                'Check-in Time', 'Queued At', 'Loaded At', 'Exited At',
                'Checkin→Queue (min)', 'Queue→Load (min)', 'Load→Exit (min)', 'Total (min)',
                'People', 'Bags',
            ]);

            foreach ($visits as $v) {
                fputcsv($out, [
                    $v->id,
                    $v->household_number,
                    $v->full_name,
                    $v->lane,
                    $v->status_label,
                    $v->start_time?->format('H:i:s'),
                    $v->queued_at?->format('H:i:s'),
                    $v->loading_completed_at?->format('H:i:s'),
                    $v->exited_at?->format('H:i:s'),
                    $v->checkin_to_queue,
                    $v->queue_to_loaded,
                    $v->loaded_to_exited,
                    $v->total_time,
                    $v->household_size,
                    $v->served_bags,
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * Apply the same search/lane/status narrowing the on-screen Alpine table
     * uses, so the Print sheet and CSV download mirror what the user is
     * looking at. Empty inputs are no-ops; an absent query string yields the
     * full set, preserving prior behavior.
     */
    private function applyFilters(Collection $visits, Request $request): Collection
    {
        $search = trim((string) $request->input('search', ''));
        $lane   = $request->input('lane');
        $status = $request->input('status');

        return $visits->filter(function ($v) use ($search, $lane, $status) {
            if ($lane !== null && $lane !== '' && (int) $v->lane !== (int) $lane) {
                return false;
            }
            if ($status !== null && $status !== '' && $v->visit_status !== $status) {
                return false;
            }
            if ($search !== '') {
                $hay = strtolower(($v->full_name ?? '') . ' ' . ($v->household_number ?? ''));
                if (! str_contains($hay, strtolower($search))) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    /**
     * Human-readable summary of the active filter set, for the print sheet
     * header. Returns null when nothing is filtered so the print template
     * can hide the "Filtered by" line entirely.
     */
    private function activeFilters(Request $request, Event $event): ?string
    {
        $parts = [];
        if ($lane = $request->input('lane')) {
            $parts[] = "Lane {$lane}";
        }
        if ($status = $request->input('status')) {
            $parts[] = ucwords(str_replace('_', ' ', $status));
        }
        if ($search = trim((string) $request->input('search', ''))) {
            $parts[] = 'matching "' . $search . '"';
        }
        return $parts ? implode(' · ', $parts) : null;
    }
}
