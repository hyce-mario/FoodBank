@extends('layouts.app')
@section('title', 'Reports — Demographics')

@section('content')

<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Household demographics &amp; geographic distribution</p>
    </div>
    <a href="{{ route('reports.download', array_merge(request()->only(['preset','date_from','date_to']), ['type' => 'demographics'])) }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Export ZIP Data
    </a>
</div>

@include('reports._nav')
@include('reports._filter', ['formAction' => route('reports.demographics')])

@php
$hasData = $demo['sizeDist']->isNotEmpty() || $demo['zipDist']->isNotEmpty() || $demo['cityDist']->isNotEmpty();
@endphp

@if(!$hasData)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No demographic data for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Expand your date range to see household demographics.</p>
</div>
@else

{{-- ═══ Row 1: Household Size + Family Distribution ══════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    {{-- Household Size Distribution --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Household Size Distribution</h3>
        <p class="text-xs text-gray-400 mb-4">Number of households by size (people per household)</p>
        @if($demo['sizeDist']->isNotEmpty())
            <div class="h-48">
                <canvas id="sizeChart"></canvas>
            </div>
        @else
            <p class="text-sm text-gray-400 py-10 text-center">No data available.</p>
        @endif
    </div>

    {{-- Number of Families Distribution --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Families per Household</h3>
        <p class="text-xs text-gray-400 mb-4">How many family units each household contains</p>
        @if($demo['familiesDist']->isNotEmpty())
            <div class="h-48">
                <canvas id="familiesChart"></canvas>
            </div>
        @else
            <p class="text-sm text-gray-400 py-10 text-center">No data available.</p>
        @endif
    </div>

</div>

{{-- ═══ Row 2: ZIP + City ═══════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    {{-- Top ZIP Codes --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Top ZIP Codes Served</h3>
            <p class="text-xs text-gray-400 mt-0.5">Households served by ZIP code</p>
        </div>
        @if($demo['zipDist']->isNotEmpty())
            @php $maxZip = $demo['zipDist']->max('count'); @endphp
            <div class="px-5 py-4 space-y-2.5">
                @foreach($demo['zipDist'] as $i => $row)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-400 w-4 text-right flex-shrink-0">{{ $i+1 }}</span>
                    <span class="text-sm font-semibold text-gray-700 w-14 flex-shrink-0">{{ $row->zip }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full bg-navy-700"
                             style="width: {{ round($row->count / max($maxZip, 1) * 100) }}%"></div>
                    </div>
                    <span class="text-sm font-bold text-gray-900 tabular-nums w-10 text-right flex-shrink-0">{{ $row->count }}</span>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400 py-10 text-center px-5">No ZIP code data available.</p>
        @endif
    </div>

    {{-- Top Cities --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Top Cities Served</h3>
            <p class="text-xs text-gray-400 mt-0.5">Households served by city</p>
        </div>
        @if($demo['cityDist']->isNotEmpty())
            @php $maxCity = $demo['cityDist']->max('count'); @endphp
            <div class="px-5 py-4 space-y-2.5">
                @foreach($demo['cityDist'] as $i => $row)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-400 w-4 text-right flex-shrink-0">{{ $i+1 }}</span>
                    <span class="text-sm font-semibold text-gray-700 flex-1 truncate">{{ $row->city }}</span>
                    <div class="w-24 bg-gray-100 rounded-full h-2 overflow-hidden flex-shrink-0">
                        <div class="h-2 rounded-full bg-brand-500"
                             style="width: {{ round($row->count / max($maxCity, 1) * 100) }}%"></div>
                    </div>
                    <span class="text-sm font-bold text-gray-900 tabular-nums w-10 text-right flex-shrink-0">{{ $row->count }}</span>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400 py-10 text-center px-5">No city data available.</p>
        @endif
    </div>

</div>

{{-- ═══ Vehicle Make (optional) ══════════════════════════════════════ --}}
@if($demo['vehicleDist']->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Vehicle Makes on File</h3>
        <p class="text-xs text-gray-400 mt-0.5">Most common vehicle makes for served households</p>
    </div>
    @php $maxVeh = $demo['vehicleDist']->max('count'); @endphp
    <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        @foreach($demo['vehicleDist'] as $row)
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-700 w-24 flex-shrink-0 truncate capitalize">{{ $row->vehicle_make }}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                <div class="h-2 rounded-full bg-purple-500"
                     style="width: {{ round($row->count / max($maxVeh, 1) * 100) }}%"></div>
            </div>
            <span class="text-sm font-bold text-gray-900 tabular-nums w-8 text-right flex-shrink-0">{{ $row->count }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

@endif
@endsection

@push('scripts')
@if(isset($demo) && ($demo['sizeDist']->isNotEmpty() || $demo['familiesDist']->isNotEmpty()))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const DEMO   = @json($demo);
    const navy   = '#1e3a5f';
    const orange = '#f97316';
    const grid   = '#F3F4F6';
    const textC  = '#6B7280';

    const baseOpts = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: '#1F2937', padding: 8, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } },
        },
        scales: {
            x: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 } } },
            y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, precision: 0 }, beginAtZero: true },
        },
    };

    // Household size chart
    const sEl = document.getElementById('sizeChart');
    if (sEl && DEMO.sizeDist.length) {
        new Chart(sEl, {
            type: 'bar',
            data: {
                labels: DEMO.sizeDist.map(r => r.size + ' person' + (r.size > 1 ? 's' : '')),
                datasets: [{
                    data: DEMO.sizeDist.map(r => r.count),
                    backgroundColor: navy, borderRadius: 5, borderSkipped: false,
                }],
            },
            options: { ...baseOpts },
        });
    }

    // Families distribution
    const fEl = document.getElementById('familiesChart');
    if (fEl && DEMO.familiesDist.length) {
        new Chart(fEl, {
            type: 'bar',
            data: {
                labels: DEMO.familiesDist.map(r => r.families + ' famil' + (r.families > 1 ? 'ies' : 'y')),
                datasets: [{
                    data: DEMO.familiesDist.map(r => r.count),
                    backgroundColor: orange, borderRadius: 5, borderSkipped: false,
                }],
            },
            options: { ...baseOpts },
        });
    }
});
</script>
@endif
@endpush
