@extends('layouts.app')
@section('title', 'Reports — Lane Performance')

@section('content')

<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Lane throughput &amp; timing performance</p>
    </div>
</div>

@include('reports._nav')

{{-- Filter with optional event selector --}}
@php
$extraFilters = '<label class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Event</label>
<form method="GET" action="' . route('reports.lanes') . '" id="lane-event-form" class="flex items-center gap-2">
    <input type="hidden" name="preset" value="' . ($filter['preset']) . '">
    <input type="hidden" name="date_from" value="' . ($filter['date_from']) . '">
    <input type="hidden" name="date_to" value="' . ($filter['date_to']) . '">
    <select name="event_id" onchange="document.getElementById(\'lane-event-form\').submit()"
            class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-navy-600">
        <option value="">All Events</option>
        ' . $events->map(fn ($e) => '<option value="' . $e->id . '"' . ($eventId == $e->id ? ' selected' : '') . '>' . e($e->name) . ' — ' . $e->date->format('M j, Y') . '</option>')->implode('') . '
    </select>
</form>';
@endphp
@include('reports._filter', ['formAction' => route('reports.lanes'), 'extraFilters' => $extraFilters])

@if(count($lanes) === 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No lane data for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Select a different date range or event.</p>
</div>
@else

{{-- ═══ Summary KPI Cards ═══════════════════════════════════════════ --}}
@php
$totalVisitsAll = array_sum(array_column($lanes, 'total_visits'));
$totalComplete  = array_sum(array_column($lanes, 'completed'));
$overallRate    = $totalVisitsAll > 0 ? round($totalComplete / $totalVisitsAll * 100) : 0;
$bestLane       = collect($lanes)->where('completed', '>', 0)->sortBy('avg_total')->first();
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="bg-navy-700 text-white rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Total Lanes</p>
        <p class="text-2xl font-bold">{{ count($lanes) }}</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Total Visits</p>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalVisitsAll) }}</p>
    </div>
    <div class="bg-green-50 border border-green-100 rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-green-600 mb-1">Completion Rate</p>
        <p class="text-2xl font-bold text-green-900">{{ $overallRate }}%</p>
    </div>
    @if($bestLane)
    <div class="bg-brand-500 text-white rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Fastest Lane</p>
        <p class="text-2xl font-bold">Lane {{ $bestLane['lane'] }}</p>
        <p class="text-xs text-white/60 mt-0.5">{{ $bestLane['avg_total'] }} min avg</p>
    </div>
    @else
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Completed</p>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalComplete) }}</p>
    </div>
    @endif
</div>

{{-- ═══ Charts ══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    {{-- Visits per lane --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Visits per Lane</h3>
        <p class="text-xs text-gray-400 mb-4">Total vs completed visits by lane</p>
        <div class="h-52">
            <canvas id="laneVisitsChart"></canvas>
        </div>
    </div>

    {{-- Avg total time per lane --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Avg Service Time per Lane</h3>
        <p class="text-xs text-gray-400 mb-4">Average full-cycle time (min) by lane</p>
        <div class="h-52">
            <canvas id="laneTimeChart"></canvas>
        </div>
    </div>

</div>

{{-- ═══ Stage Timing Breakdown ══════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <h3 class="text-sm font-bold text-gray-800 mb-1">Stage Timing by Lane</h3>
    <p class="text-xs text-gray-400 mb-4">Average minutes per stage for each lane</p>
    <div class="h-52">
        <canvas id="laneStageChart"></canvas>
    </div>
</div>

{{-- ═══ Detailed Table ══════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Lane Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">Lane</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Total</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Completed</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Complete %</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden sm:table-cell">Checkin→Queue</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Queue→Load</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Load→Exit</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Total Avg</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lanes as $lane)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-navy-700 text-white text-sm font-bold">
                            {{ $lane['lane'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900 tabular-nums">{{ $lane['total_visits'] }}</td>
                    <td class="px-4 py-3 text-right text-green-700 font-semibold tabular-nums">{{ $lane['completed'] }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $lane['completion_rate'] >= 90 ? 'bg-green-100 text-green-700' : ($lane['completion_rate'] >= 70 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                            {{ $lane['completion_rate'] }}%
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden sm:table-cell">
                        {{ $lane['avg_checkin_queue'] > 0 ? $lane['avg_checkin_queue'] . ' min' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden md:table-cell">
                        {{ $lane['avg_queue_loaded'] > 0 ? $lane['avg_queue_loaded'] . ' min' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden md:table-cell">
                        {{ $lane['avg_loaded_exited'] > 0 ? $lane['avg_loaded_exited'] . ' min' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        <span class="font-bold {{ $lane['avg_total'] > 0 ? 'text-navy-700' : 'text-gray-300' }}">
                            {{ $lane['avg_total'] > 0 ? $lane['avg_total'] . ' min' : '—' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endif
@endsection

@push('scripts')
@if(count($lanes) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const LANES = @json($lanes);
    const navy  = '#1e3a5f';
    const green = '#10B981';
    const orange= '#f97316';
    const amber = '#F59E0B';
    const purple= '#8B5CF6';
    const grid  = '#F3F4F6';
    const textC = '#6B7280';

    const labels   = LANES.map(l => 'Lane ' + l.lane);
    const totals   = LANES.map(l => l.total_visits);
    const complete = LANES.map(l => l.completed);
    const times    = LANES.map(l => l.avg_total);
    const c2q      = LANES.map(l => l.avg_checkin_queue);
    const q2l      = LANES.map(l => l.avg_queue_loaded);
    const l2e      = LANES.map(l => l.avg_loaded_exited);

    const baseOpts = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            tooltip: { backgroundColor: '#1F2937', padding: 8, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } },
        },
        scales: {
            x: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 } } },
            y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, precision: 0 }, beginAtZero: true },
        },
    };

    // Visits per lane
    const vEl = document.getElementById('laneVisitsChart');
    if (vEl) {
        new Chart(vEl, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Total', data: totals, backgroundColor: navy + 'aa', borderRadius: 4, borderSkipped: false },
                    { label: 'Completed', data: complete, backgroundColor: green, borderRadius: 4, borderSkipped: false },
                ],
            },
            options: {
                ...baseOpts,
                plugins: { ...baseOpts.plugins, legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: textC } } },
            },
        });
    }

    // Avg time per lane
    const tEl = document.getElementById('laneTimeChart');
    if (tEl) {
        new Chart(tEl, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label: 'Avg Total (min)', data: times, backgroundColor: orange, borderRadius: 4, borderSkipped: false }],
            },
            options: {
                ...baseOpts,
                plugins: {
                    ...baseOpts.plugins,
                    legend: { display: false },
                    tooltip: { ...baseOpts.plugins.tooltip, callbacks: { label: ctx => ' ' + ctx.raw + ' min' } },
                },
            },
        });
    }

    // Stage breakdown
    const sEl = document.getElementById('laneStageChart');
    if (sEl) {
        new Chart(sEl, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Checkin → Queue', data: c2q, backgroundColor: amber,  borderRadius: 3, borderSkipped: false },
                    { label: 'Queue → Load',    data: q2l, backgroundColor: orange, borderRadius: 3, borderSkipped: false },
                    { label: 'Load → Exit',     data: l2e, backgroundColor: purple, borderRadius: 3, borderSkipped: false },
                ],
            },
            options: {
                ...baseOpts,
                plugins: {
                    ...baseOpts.plugins,
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: textC } },
                    tooltip: { ...baseOpts.plugins.tooltip, mode: 'index', intersect: false,
                        callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + ctx.raw + ' min' } },
                },
                scales: {
                    ...baseOpts.scales,
                    x: { ...baseOpts.scales.x, stacked: true },
                    y: { ...baseOpts.scales.y, stacked: true,
                         ticks: { ...baseOpts.scales.y.ticks, callback: v => v + 'm' } },
                },
            },
        });
    }
});
</script>
@endif
@endpush
