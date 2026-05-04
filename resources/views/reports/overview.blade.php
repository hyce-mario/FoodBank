@extends('layouts.app')
@section('title', 'Reports — Overview')

@section('content')
{{-- ═══════════════════════════════════════════════════════
     PAGE HEADER
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Analytics &amp; reporting center</p>
    </div>
    <a href="{{ route('reports.export', request()->only(['preset','date_from','date_to'])) }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Export Data
    </a>
</div>

{{-- Sub-nav --}}
@include('reports._nav')

{{-- Filter --}}
@include('reports._filter', ['formAction' => route('reports.overview')])

{{-- ═══════════════════════════════════════════════════════
     INSIGHTS PANEL
═══════════════════════════════════════════════════════ --}}
@if(count($insights) > 0)
<div class="mb-5">
    <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-400 px-1 mb-3">Insights</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        @foreach(array_slice($insights, 0, 3) as $insight)
            <div class="flex items-start gap-3 px-4 py-3 rounded-xl border bg-gray-50 border-gray-200">
                <span class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 bg-gray-400"></span>
                <p class="text-sm text-gray-600 leading-snug">{{ $insight['text'] }}</p>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════
     KPI CARDS
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">

    @php
    $cards = [
        ['label' => 'Households Served',   'value' => number_format($overview['households_served']), 'sub' => 'exited visits',    'color' => 'navy'],
        ['label' => 'People Served',        'value' => number_format($overview['people_served']),     'sub' => 'total individuals','color' => 'navy'],
        ['label' => 'Bags Distributed',     'value' => number_format($overview['bags_distributed']),  'sub' => 'food bags',        'color' => 'brand'],
        ['label' => 'Events Held',          'value' => $overview['total_events'],                     'sub' => 'in period',        'color' => 'gray'],
        ['label' => 'Avg Service Time',     'value' => $overview['avg_service_time'] . ' min',        'sub' => 'full cycle',       'color' => 'gray'],
        ['label' => 'Completed Visits',     'value' => number_format($overview['completed_visits']),  'sub' => 'exited',           'color' => 'green'],
        ['label' => 'Incomplete Visits',    'value' => number_format($overview['incomplete_visits']), 'sub' => 'not yet exited',   'color' => $overview['incomplete_visits'] > 0 ? 'amber' : 'gray'],
        ['label' => 'Avg Rating',           'value' => $overview['avg_rating'] > 0 ? $overview['avg_rating'] . ' ★' : '—',
                                             'sub' => $overview['total_reviews'] . ' reviews',        'color' => 'purple'],
        ['label' => 'Volunteers Served',    'value' => $overview['total_volunteers'],                 'sub' => 'assigned in period','color' => 'gray'],
    ];
    @endphp

    @foreach($cards as $card)
        @php
            $bg = match($card['color']) {
                'navy'  => 'bg-navy-700 text-white',
                'brand' => 'bg-brand-500 text-white',
                'green' => 'bg-green-50 text-green-900',
                'amber' => 'bg-amber-50 text-amber-900',
                'purple'=> 'bg-purple-50 text-purple-900',
                default => 'bg-white text-gray-900',
            };
            $subColor = in_array($card['color'], ['navy','brand']) ? 'text-white/60' : 'text-gray-400';
        @endphp
        <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 {{ $bg }}">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $subColor }} mb-1">{{ $card['label'] }}</p>
            <p class="text-2xl font-bold tabular-nums">{{ $card['value'] }}</p>
            <p class="text-xs {{ $subColor }} mt-0.5">{{ $card['sub'] }}</p>
        </div>
    @endforeach

</div>

{{-- ═══════════════════════════════════════════════════════
     CHARTS
═══════════════════════════════════════════════════════ --}}
@if(count($trend['labels']) > 0)
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

    {{-- Service Trend --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Service Trend</h3>
        <p class="text-xs text-gray-400 mb-4">Households &amp; people served over time</p>
        <div class="h-52">
            <canvas id="overviewTrendChart"></canvas>
        </div>
    </div>

    {{-- Bags Distributed --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Bags Distributed</h3>
        <p class="text-xs text-gray-400 mb-4">Food bags given out over time</p>
        <div class="h-52">
            <canvas id="bagsChart"></canvas>
        </div>
    </div>

</div>
@else
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-16 text-center mb-6">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No service data for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Try expanding your date range.</p>
</div>
@endif

@endsection

@push('scripts')
@if(count($trend['labels']) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const TREND = @json($trend);
    const navy  = '#1e3a5f';
    const orange = '#f97316';
    const grid  = '#F3F4F6';
    const text  = '#6B7280';

    const baseScales = {
        x: { grid: { color: grid }, ticks: { color: text, font: { size: 11 } } },
        y: { grid: { color: grid }, ticks: { color: text, font: { size: 11 }, precision: 0 }, beginAtZero: true },
    };

    const baseTooltip = {
        backgroundColor: '#1F2937', padding: 8, cornerRadius: 8,
        titleFont: { size: 12 }, bodyFont: { size: 12 },
    };

    // ── Service Trend (line) ──────────────────────────────────────────────────
    const tEl = document.getElementById('overviewTrendChart');
    if (tEl) {
        new Chart(tEl, {
            type: 'line',
            data: {
                labels: TREND.labels,
                datasets: [
                    {
                        label: 'Households',
                        data: TREND.households,
                        borderColor: navy,
                        backgroundColor: navy + '20',
                        fill: true,
                        tension: 0.35,
                        pointRadius: TREND.labels.length < 20 ? 4 : 2,
                        pointBackgroundColor: navy,
                        borderWidth: 2,
                    },
                    {
                        label: 'People',
                        data: TREND.people,
                        borderColor: orange,
                        backgroundColor: orange + '15',
                        fill: true,
                        tension: 0.35,
                        pointRadius: TREND.labels.length < 20 ? 4 : 2,
                        pointBackgroundColor: orange,
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: text } },
                    tooltip: { ...baseTooltip, mode: 'index', intersect: false },
                },
                scales: baseScales,
            },
        });
    }

    // ── Bags Chart (bar) ─────────────────────────────────────────────────────
    const bEl = document.getElementById('bagsChart');
    if (bEl) {
        new Chart(bEl, {
            type: 'bar',
            data: {
                labels: TREND.labels,
                datasets: [{
                    label: 'Bags',
                    data: TREND.bags,
                    backgroundColor: orange,
                    borderRadius: 5,
                    borderSkipped: false,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { ...baseTooltip } },
                scales: baseScales,
            },
        });
    }
});
</script>
@endif
@endpush
