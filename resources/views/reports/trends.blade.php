@extends('layouts.app')
@section('title', 'Reports — Service Trends')

@section('content')

<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Household &amp; service trends over time</p>
    </div>
</div>

@include('reports._nav')
@include('reports._filter', ['formAction' => route('reports.trends')])

@if(count($trends['labels']) === 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.519l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No trend data for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Try a wider date range to see trends.</p>
</div>
@else

{{-- ═══ New vs Returning Summary ═══════════════════════════════════════ --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    @php
    $totalHH  = array_sum($trends['households']);
    $totalPpl = array_sum($trends['people']);
    $totalBags= array_sum($trends['bags']);
    @endphp
    <div class="bg-navy-700 text-white rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Total Households</p>
        <p class="text-2xl font-bold tabular-nums">{{ number_format($totalHH) }}</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Total People</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($totalPpl) }}</p>
    </div>
    <div class="bg-green-50 border border-green-100 rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-green-600 mb-1">New Households</p>
        <p class="text-2xl font-bold text-green-900 tabular-nums">{{ number_format($trends['new_households']) }}</p>
        <p class="text-xs text-green-600 mt-0.5">first visit ever</p>
    </div>
    <div class="bg-blue-50 border border-blue-100 rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 mb-1">Returning</p>
        <p class="text-2xl font-bold text-blue-900 tabular-nums">{{ number_format($trends['ret_households']) }}</p>
        <p class="text-xs text-blue-600 mt-0.5">visited before</p>
    </div>
</div>

{{-- ═══ Charts ══════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 gap-5 mb-5">

    {{-- Households served trend --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Households &amp; People Served Over Time</h3>
        <p class="text-xs text-gray-400 mb-4">Completed service by period</p>
        <div class="h-56">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    {{-- Bags trend --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Bags Distributed Over Time</h3>
        <p class="text-xs text-gray-400 mb-4">Food bags given out per period</p>
        <div class="h-48">
            <canvas id="bagsChart"></canvas>
        </div>
    </div>

</div>

{{-- ═══ Trend Table ══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Period Breakdown</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">Period</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Households</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden sm:table-cell">People</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Bags</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trends['labels'] as $i => $label)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-700">{{ $label }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900 tabular-nums">{{ number_format($trends['households'][$i]) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden sm:table-cell">{{ number_format($trends['people'][$i]) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ number_format($trends['bags'][$i]) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td class="px-5 py-3 font-bold text-gray-800">Total</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums">{{ number_format($totalHH) }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums hidden sm:table-cell">{{ number_format($totalPpl) }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums">{{ number_format($totalBags) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endif
@endsection

@push('scripts')
@if(count($trends['labels']) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const T    = @json($trends);
    const navy = '#1e3a5f';
    const orange = '#f97316';
    const grid = '#F3F4F6';
    const textC = '#6B7280';

    const baseScales = {
        x: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 } } },
        y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, precision: 0 }, beginAtZero: true },
    };

    const tooltip = { backgroundColor: '#1F2937', padding: 8, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } };

    const tEl = document.getElementById('trendChart');
    if (tEl) {
        new Chart(tEl, {
            type: 'line',
            data: {
                labels: T.labels,
                datasets: [
                    {
                        label: 'Households',
                        data: T.households,
                        borderColor: navy, backgroundColor: navy + '18',
                        fill: true, tension: 0.35,
                        pointRadius: T.labels.length < 25 ? 4 : 2,
                        pointBackgroundColor: navy, borderWidth: 2,
                    },
                    {
                        label: 'People',
                        data: T.people,
                        borderColor: orange, backgroundColor: orange + '12',
                        fill: true, tension: 0.35,
                        pointRadius: T.labels.length < 25 ? 4 : 2,
                        pointBackgroundColor: orange, borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: textC } },
                    tooltip: { ...tooltip, mode: 'index', intersect: false },
                },
                scales: baseScales,
            },
        });
    }

    const bEl = document.getElementById('bagsChart');
    if (bEl) {
        new Chart(bEl, {
            type: 'bar',
            data: {
                labels: T.labels,
                datasets: [{ label: 'Bags', data: T.bags, backgroundColor: orange, borderRadius: 5, borderSkipped: false }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip },
                scales: baseScales,
            },
        });
    }
});
</script>
@endif
@endpush
