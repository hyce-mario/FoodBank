@extends('layouts.app')
@section('title', 'Reports — Reviews & Satisfaction')

@section('content')

<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Event reviews &amp; satisfaction analysis</p>
    </div>
    <a href="{{ route('reports.download', array_merge(request()->only(['preset','date_from','date_to']), ['type' => 'reviews'])) }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Export CSV
    </a>
</div>

@include('reports._nav')
@include('reports._filter', ['formAction' => route('reports.reviews')])

@if(!$overall || $overall->total == 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No reviews found for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Reviews are submitted for past events via the public review form.</p>
</div>
@else

{{-- ═══ KPI Cards ══════════════════════════════════════════════════ --}}
@php
$avgRating = round($overall->avg_rating ?? 0, 1);
$totalRevs = (int) $overall->total;
$topEvent  = $byEvent->first();
$botEvent  = $byEvent->last();
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="bg-navy-700 text-white rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Avg Rating</p>
        <p class="text-2xl font-bold">{{ $avgRating }} ★</p>
        <p class="text-xs text-white/60 mt-0.5">{{ $totalRevs }} reviews</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Total Reviews</p>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalRevs) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">in period</p>
    </div>
    @if($topEvent)
    <div class="bg-amber-50 border border-amber-100 rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 mb-1">Top Rated</p>
        <p class="text-sm font-bold text-amber-900 leading-snug">{{ Str::limit($topEvent->name, 25) }}</p>
        <p class="text-xs text-amber-600 mt-0.5">{{ round($topEvent->avg_rating, 1) }} ★ ({{ $topEvent->review_count }})</p>
    </div>
    @endif
    @if($botEvent && $byEvent->count() > 1)
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Needs Attention</p>
        <p class="text-sm font-bold text-gray-700 leading-snug">{{ Str::limit($botEvent->name, 25) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ round($botEvent->avg_rating, 1) }} ★ ({{ $botEvent->review_count }})</p>
    </div>
    @else
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Events Reviewed</p>
        <p class="text-2xl font-bold text-gray-900">{{ $byEvent->count() }}</p>
    </div>
    @endif
</div>

{{-- ═══ Charts ══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    {{-- Rating Distribution --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Rating Distribution</h3>
        <p class="text-xs text-gray-400 mb-4">How ratings are spread across 1–5 stars</p>
        <div class="space-y-2.5">
            @php
            $maxCount = collect($ratingDist)->max() ?: 1;
            @endphp
            @for($r = 5; $r >= 1; $r--)
            @php $count = $ratingDist[(string)$r] ?? 0; @endphp
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-amber-500 w-8 flex-shrink-0">{{ $r }} ★</span>
                <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                    <div class="h-4 rounded-full bg-amber-400 transition-all"
                         style="width: {{ round($count / $maxCount * 100) }}%"></div>
                </div>
                <span class="text-sm font-bold text-gray-700 tabular-nums w-10 text-right flex-shrink-0">{{ $count }}</span>
            </div>
            @endfor
        </div>
    </div>

    {{-- Avg Rating by Event --}}
    @if($byEvent->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Avg Rating by Event</h3>
        <p class="text-xs text-gray-400 mb-4">Highest to lowest rated events</p>
        <div class="h-52">
            <canvas id="ratingByEventChart"></canvas>
        </div>
    </div>
    @endif

</div>

{{-- ═══ Review Trend ════════════════════════════════════════════════ --}}
@if(count($trend) > 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <h3 class="text-sm font-bold text-gray-800 mb-1">Review &amp; Rating Trend</h3>
    <p class="text-xs text-gray-400 mb-4">Review count and average rating over time</p>
    <div class="h-48">
        <canvas id="reviewTrendChart"></canvas>
    </div>
</div>
@endif

{{-- ═══ Reviews by Event ═══════════════════════════════════════════ --}}
@if($byEvent->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Reviews by Event</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">Event</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden sm:table-cell">Date</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Reviews</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Avg Rating</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Range</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byEvent as $ev)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-3 font-semibold text-gray-800">{{ $ev->name }}</td>
                    <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">
                        {{ \Illuminate\Support\Carbon::parse($ev->date)->format('M j, Y') }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ $ev->review_count }}</td>
                    <td class="px-4 py-3 text-right">
                        @php $ar = round($ev->avg_rating, 1); @endphp
                        <span class="font-bold tabular-nums
                            {{ $ar >= 4.5 ? 'text-green-700' : ($ar >= 3.5 ? 'text-amber-600' : 'text-red-600') }}">
                            {{ $ar }} ★
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-gray-400 text-xs hidden md:table-cell">
                        {{ $ev->min_r }} – {{ $ev->max_r }} ★
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ═══ All Reviews Table ═══════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm"
     x-data="{ search: '' }">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <h3 class="text-sm font-bold text-gray-800 flex-1">All Reviews</h3>
        <input x-model="search" placeholder="Search reviews…"
               class="text-sm border border-gray-200 rounded-xl px-3 py-2 w-48 focus:outline-none focus:ring-2 focus:ring-navy-600">
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($allReviews as $rev)
        <div class="px-5 py-4 hover:bg-gray-50 transition-colors"
             x-show="search === '' || '{{ strtolower(($rev->event?->name ?? '') . ' ' . ($rev->reviewer_name ?? '') . ' ' . $rev->review_text) }}'.includes(search.toLowerCase())">
            <div class="flex items-start justify-between gap-3 mb-1.5">
                <div>
                    <span class="text-sm font-semibold text-gray-800">
                        {{ $rev->reviewer_name ?: 'Anonymous' }}
                    </span>
                    @if($rev->event)
                        <span class="text-xs text-gray-400 ml-2">
                            re: {{ $rev->event->name }}
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-amber-500 font-bold text-sm tabular-nums">{{ $rev->rating }} ★</span>
                    <span class="text-xs text-gray-400">
                        {{ $rev->created_at?->format('M j, Y') }}
                    </span>
                </div>
            </div>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $rev->review_text }}</p>
            @if($rev->email)
                <p class="text-xs text-gray-400 mt-1">{{ $rev->email }}</p>
            @endif
        </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-400 px-5 py-3">{{ $allReviews->count() }} reviews in period</p>
</div>

@endif
@endsection

@push('scripts')
@if($overall && $overall->total > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const BY_EVENT = @json($byEvent);
    const TREND    = @json($trend);
    const navy     = '#1e3a5f';
    const amber    = '#F59E0B';
    const green    = '#10B981';
    const grid     = '#F3F4F6';
    const textC    = '#6B7280';

    const tooltip = { backgroundColor: '#1F2937', padding: 8, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } };

    // Avg rating by event
    const rEl = document.getElementById('ratingByEventChart');
    if (rEl && BY_EVENT.length) {
        const sorted = [...BY_EVENT].sort((a, b) => b.avg_rating - a.avg_rating);
        new Chart(rEl, {
            type: 'bar',
            data: {
                labels: sorted.map(e => e.name.length > 18 ? e.name.substring(0, 18) + '…' : e.name),
                datasets: [{
                    data: sorted.map(e => Math.round(e.avg_rating * 10) / 10),
                    backgroundColor: sorted.map(e => e.avg_rating >= 4.5 ? green : (e.avg_rating >= 3.5 ? amber : '#EF4444')),
                    borderRadius: 5, borderSkipped: false,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { ...tooltip, callbacks: { label: ctx => ' ' + ctx.raw + ' ★ avg' } },
                },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: textC, font: { size: 10 }, maxRotation: 35 } },
                    y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 } }, beginAtZero: true, max: 5 },
                },
            },
        });
    }

    // Review trend
    const tEl = document.getElementById('reviewTrendChart');
    if (tEl && TREND.length) {
        new Chart(tEl, {
            type: 'bar',
            data: {
                labels: TREND.map(r => r.label),
                datasets: [
                    {
                        label: 'Review Count',
                        data: TREND.map(r => r.count),
                        backgroundColor: navy + 'cc',
                        borderRadius: 4, borderSkipped: false,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Avg Rating',
                        data: TREND.map(r => Math.round(r.avg_rating * 10) / 10),
                        type: 'line',
                        borderColor: amber,
                        backgroundColor: 'transparent',
                        pointBackgroundColor: amber,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 4,
                        yAxisID: 'y2',
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: textC } },
                    tooltip: { ...tooltip, mode: 'index', intersect: false },
                },
                scales: {
                    x:  { grid: { color: grid }, ticks: { color: textC, font: { size: 11 } } },
                    y:  { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, precision: 0 }, beginAtZero: true, position: 'left' },
                    y2: { grid: { display: false }, ticks: { color: amber, font: { size: 11 }, callback: v => v + '★' }, min: 0, max: 5, position: 'right' },
                },
            },
        });
    }
});
</script>
@endif
@endpush
