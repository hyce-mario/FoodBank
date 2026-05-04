@extends('layouts.app')
@section('title', 'Reports — Event Performance')

@section('content')

{{-- Page Header --}}
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Event performance comparison</p>
    </div>
    <a href="{{ route('reports.download', array_merge(request()->only(['preset','date_from','date_to']), ['type' => 'events'])) }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Export CSV
    </a>
</div>

@include('reports._nav')
@include('reports._filter', ['formAction' => route('reports.events')])

@if(count($events) === 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No events found for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Adjust your date range to find past events.</p>
</div>
@else

{{-- ═══ Summary Row ═══════════════════════════════════════════════════════ --}}
@php
$totalHH   = array_sum(array_column($events, 'households_served'));
$totalPpl  = array_sum(array_column($events, 'people_served'));
$totalBags = array_sum(array_column($events, 'bags_distributed'));
$avgRate   = count(array_filter($events, fn($e) => $e['avg_rating'])) > 0
    ? round(array_sum(array_column(array_filter($events, fn($e) => $e['avg_rating']), 'avg_rating')) / count(array_filter($events, fn($e) => $e['avg_rating'])), 1)
    : null;
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="bg-navy-700 text-white rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Events</p>
        <p class="text-2xl font-bold">{{ count($events) }}</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Total Households</p>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalHH) }}</p>
    </div>
    <div class="bg-brand-500 text-white rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Total Bags</p>
        <p class="text-2xl font-bold">{{ number_format($totalBags) }}</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Avg Rating</p>
        <p class="text-2xl font-bold text-gray-900">{{ $avgRate ? $avgRate . ' ★' : '—' }}</p>
    </div>
</div>

{{-- ═══ Charts ═══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    {{-- Households by Event --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Households Served by Event</h3>
        <p class="text-xs text-gray-400 mb-4">Completed (exited) households per event</p>
        <div class="h-52">
            <canvas id="hhChart"></canvas>
        </div>
    </div>

    {{-- Avg Service Time by Event --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Avg Service Time by Event</h3>
        <p class="text-xs text-gray-400 mb-4">Average full-cycle minutes (check-in → exit)</p>
        <div class="h-52">
            <canvas id="timeChart"></canvas>
        </div>
    </div>

</div>

{{-- ═══ Detailed Table ══════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm" x-data="{ search: '' }">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <h3 class="text-sm font-bold text-gray-800 flex-1">Event Details</h3>
        <input x-model="search"
               placeholder="Search events…"
               class="text-sm border border-gray-200 rounded-xl px-3 py-2 w-48 focus:outline-none focus:ring-2 focus:ring-navy-600">
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">Event</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Date</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Location</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Households</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden sm:table-cell">People</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden sm:table-cell">Bags</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Complete %</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Avg Time</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden lg:table-cell">Rating</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $ev)
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
                    x-show="search === '' || '{{ strtolower($ev['name'] . ' ' . $ev['date'] . ' ' . $ev['location']) }}'.includes(search.toLowerCase())">
                    <td class="px-5 py-3">
                        <a href="{{ route('events.show', $ev['id']) }}"
                           class="font-semibold text-gray-900 hover:text-navy-700 transition-colors">
                            {{ $ev['name'] }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $ev['date'] }}</td>
                    <td class="px-4 py-3 text-gray-500 hidden md:table-cell">{{ $ev['location'] }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums">{{ number_format($ev['households_served']) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden sm:table-cell">{{ number_format($ev['people_served']) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden sm:table-cell">{{ number_format($ev['bags_distributed']) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $ev['completion_rate'] >= 90 ? 'bg-green-100 text-green-700' : ($ev['completion_rate'] >= 70 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                            {{ $ev['completion_rate'] }}%
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden md:table-cell">
                        {{ $ev['avg_service_time'] > 0 ? $ev['avg_service_time'] . ' min' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right hidden lg:table-cell">
                        @if($ev['avg_rating'])
                            <span class="font-semibold text-amber-600">{{ $ev['avg_rating'] }} ★</span>
                            <span class="text-xs text-gray-400">({{ $ev['review_count'] }})</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 px-5 py-3">{{ count($events) }} events in period</p>
</div>

@endif
@endsection

@push('scripts')
@if(count($events) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const EVENTS = @json($events);
    const navy   = '#1e3a5f';
    const orange = '#f97316';
    const grid   = '#F3F4F6';
    const text   = '#6B7280';

    // Reverse for chart (oldest first visually)
    const sorted  = [...EVENTS].reverse();
    const labels  = sorted.map(e => e.name.length > 18 ? e.name.substring(0, 18) + '…' : e.name);
    const hh      = sorted.map(e => e.households_served);
    const times   = sorted.map(e => e.avg_service_time);

    const baseOpts = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: '#1F2937', padding: 8, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } },
        },
        scales: {
            x: { grid: { color: grid }, ticks: { color: text, font: { size: 10 }, maxRotation: 35 } },
            y: { grid: { color: grid }, ticks: { color: text, font: { size: 11 }, precision: 0 }, beginAtZero: true },
        },
    };

    const hhEl = document.getElementById('hhChart');
    if (hhEl) {
        new Chart(hhEl, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ data: hh, backgroundColor: navy, borderRadius: 5, borderSkipped: false }],
            },
            options: { ...baseOpts },
        });
    }

    const timeEl = document.getElementById('timeChart');
    if (timeEl) {
        new Chart(timeEl, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ data: times, backgroundColor: orange, borderRadius: 5, borderSkipped: false }],
            },
            options: {
                ...baseOpts,
                plugins: {
                    ...baseOpts.plugins,
                    tooltip: {
                        ...baseOpts.plugins.tooltip,
                        callbacks: { label: ctx => ' ' + ctx.raw + ' min' },
                    },
                },
            },
        });
    }
});
</script>
@endif
@endpush
