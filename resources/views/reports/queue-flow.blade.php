@extends('layouts.app')
@section('title', 'Reports — Queue Flow')

@section('content')

<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Queue flow &amp; bottleneck analysis</p>
    </div>
</div>

@include('reports._nav')

@php
$extraFilters = '<label class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Event</label>
<form method="GET" action="' . route('reports.queue-flow') . '" id="qf-event-form" class="flex items-center gap-2">
    <input type="hidden" name="preset" value="' . ($filter['preset']) . '">
    <input type="hidden" name="date_from" value="' . ($filter['date_from']) . '">
    <input type="hidden" name="date_to" value="' . ($filter['date_to']) . '">
    <select name="event_id" onchange="document.getElementById(\'qf-event-form\').submit()"
            class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-navy-600">
        <option value="">All Events</option>
        ' . $events->map(fn ($e) => '<option value="' . $e->id . '"' . ($eventId == $e->id ? ' selected' : '') . '>' . e($e->name) . ' — ' . $e->date->format('M j, Y') . '</option>')->implode('') . '
    </select>
</form>';
@endphp
@include('reports._filter', ['formAction' => route('reports.queue-flow'), 'extraFilters' => $extraFilters])

@if(!$times || $times->total_visits == 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No queue flow data for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Adjust your date range or event filter.</p>
</div>
@else

{{-- ═══ KPI Cards ══════════════════════════════════════════════════ --}}
@php
$t = $times;
$completionRate = $t->total_visits > 0 ? round($t->completed / $t->total_visits * 100) : 0;
@endphp

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-5">
    <div class="bg-navy-700 text-white rounded-2xl px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Total Visits</p>
        <p class="text-2xl font-bold">{{ number_format($t->total_visits) }}</p>
    </div>
    <div class="bg-green-50 border border-green-100 rounded-2xl px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-green-600 mb-1">Completed</p>
        <p class="text-2xl font-bold text-green-900">{{ number_format($t->completed) }}</p>
        <p class="text-xs text-green-600 mt-0.5">{{ $completionRate }}%</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-4 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Avg Total</p>
        <p class="text-xl font-bold text-gray-900 tabular-nums">{{ round($t->avg_total ?? 0, 1) }}<span class="text-sm font-normal"> min</span></p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-4 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Checkin→Queue</p>
        <p class="text-xl font-bold text-gray-900 tabular-nums">{{ round($t->avg_checkin_queue ?? 0, 1) }}<span class="text-sm font-normal"> min</span></p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-4 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Queue→Load</p>
        <p class="text-xl font-bold text-gray-900 tabular-nums">{{ round($t->avg_queue_loaded ?? 0, 1) }}<span class="text-sm font-normal"> min</span></p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-4 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Load→Exit</p>
        <p class="text-xl font-bold text-gray-900 tabular-nums">{{ round($t->avg_loaded_exited ?? 0, 1) }}<span class="text-sm font-normal"> min</span></p>
    </div>
</div>

{{-- ═══ Charts ══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    {{-- Process stage timing --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Avg Time per Stage</h3>
        <p class="text-xs text-gray-400 mb-4">Where time is being spent in the service flow</p>
        <div class="h-48">
            <canvas id="stageChart"></canvas>
        </div>
    </div>

    {{-- Hourly throughput --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Hourly Throughput</h3>
        <p class="text-xs text-gray-400 mb-4">Completed visits by hour of check-in</p>
        <div class="h-48">
            @if(count($hourly_labels) > 0)
                <canvas id="hourlyChart"></canvas>
            @else
                <p class="text-sm text-gray-400 flex items-center justify-center h-full">No hourly data available.</p>
            @endif
        </div>
    </div>

</div>

{{-- Status Distribution --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <h3 class="text-sm font-bold text-gray-800 mb-4">Visit Status Distribution</h3>
    @php
    $statusLabels = ['checked_in' => 'Checked In', 'queued' => 'Queued', 'loading' => 'Loading', 'loaded' => 'Loaded', 'exited' => 'Exited'];
    $statusColors = ['checked_in' => '#DBEAFE', 'queued' => '#EDE9FE', 'loading' => '#FED7AA', 'loaded' => '#FEF3C7', 'exited' => '#D1FAE5'];
    $statusText   = ['checked_in' => '#1D4ED8', 'queued' => '#6D28D9', 'loading' => '#C2410C', 'loaded' => '#B45309', 'exited' => '#15803D'];
    @endphp
    <div class="flex flex-wrap gap-3">
        @foreach($statusLabels as $key => $label)
        @php $count = $status_dist[$key] ?? 0; @endphp
        <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border"
             style="background: {{ $statusColors[$key] }}22; border-color: {{ $statusColors[$key] }}88;">
            <span class="text-xs font-semibold" style="color: {{ $statusText[$key] }}">{{ $label }}</span>
            <span class="text-lg font-bold tabular-nums" style="color: {{ $statusText[$key] }}">{{ number_format($count) }}</span>
        </div>
        @endforeach
    </div>
</div>

{{-- Min/Max indicators --}}
@if($t->min_total !== null && $t->max_total !== null)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
    <h3 class="text-sm font-bold text-gray-800 mb-4">Service Time Range (Completed Visits)</h3>
    <div class="flex items-center gap-6 flex-wrap">
        <div>
            <p class="text-xs text-gray-400 mb-1">Fastest</p>
            <p class="text-2xl font-bold text-green-700 tabular-nums">{{ round($t->min_total, 1) }} <span class="text-sm font-normal text-green-600">min</span></p>
        </div>
        <div class="flex-1 h-3 bg-gray-100 rounded-full overflow-hidden relative min-w-32">
            @php
            $range = max(1, $t->max_total - $t->min_total);
            $avgPct = round(($t->avg_total - $t->min_total) / $range * 100);
            @endphp
            <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-green-400 to-red-400 rounded-full" style="width: 100%"></div>
            <div class="absolute inset-y-0 w-0.5 bg-white shadow" style="left: {{ $avgPct }}%"></div>
        </div>
        <div>
            <p class="text-xs text-gray-400 mb-1">Slowest</p>
            <p class="text-2xl font-bold text-red-600 tabular-nums">{{ round($t->max_total, 1) }} <span class="text-sm font-normal text-red-500">min</span></p>
        </div>
        <div class="text-center">
            <p class="text-xs text-gray-400 mb-1">Average</p>
            <p class="text-2xl font-bold text-navy-700 tabular-nums">{{ round($t->avg_total, 1) }} <span class="text-sm font-normal text-gray-500">min</span></p>
        </div>
    </div>
</div>
@endif

@endif
@endsection

@push('scripts')
@if($times && $times->total_visits > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const T      = @json($times);
    const HOURLY = { labels: @json($hourly_labels), counts: @json($hourly_counts) };
    const navy   = '#1e3a5f';
    const amber  = '#F59E0B';
    const orange = '#f97316';
    const purple = '#8B5CF6';
    const grid   = '#F3F4F6';
    const textC  = '#6B7280';

    const tooltip = { backgroundColor: '#1F2937', padding: 8, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } };

    // Stage chart
    const sEl = document.getElementById('stageChart');
    if (sEl) {
        new Chart(sEl, {
            type: 'bar',
            data: {
                labels: ['Check-in → Queue', 'Queue → Loading', 'Loading → Exit', 'Full Cycle'],
                datasets: [{
                    data: [
                        Math.round((T.avg_checkin_queue ?? 0) * 10) / 10,
                        Math.round((T.avg_queue_loaded ?? 0) * 10) / 10,
                        Math.round((T.avg_loaded_exited ?? 0) * 10) / 10,
                        Math.round((T.avg_total ?? 0) * 10) / 10,
                    ],
                    backgroundColor: [amber, orange, purple, navy],
                    borderRadius: 6, borderSkipped: false,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { ...tooltip, callbacks: { label: ctx => ' ' + ctx.raw + ' min avg' } },
                },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: textC, font: { size: 10 } } },
                    y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, callback: v => v + 'm' }, beginAtZero: true },
                },
            },
        });
    }

    // Hourly chart
    const hEl = document.getElementById('hourlyChart');
    if (hEl && HOURLY.labels.length) {
        new Chart(hEl, {
            type: 'bar',
            data: {
                labels: HOURLY.labels,
                datasets: [{ data: HOURLY.counts, backgroundColor: navy, borderRadius: 5, borderSkipped: false }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { ...tooltip, callbacks: { label: ctx => ' ' + ctx.raw + ' completed' } },
                },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 } } },
                    y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, precision: 0 }, beginAtZero: true },
                },
            },
        });
    }
});
</script>
@endif
@endpush
