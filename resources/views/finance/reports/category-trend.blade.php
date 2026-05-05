@extends('layouts.app')
@section('title', 'Category Trend Report')

@section('content')

@php
    $reportTitle = 'Category Trend Report';
    $exportRoutes = [
        'print' => 'finance.reports.category-trend.print',
        'pdf'   => 'finance.reports.category-trend.pdf',
        'csv'   => 'finance.reports.category-trend.csv',
    ];

    $direction = $data['direction'] ?? 'income';
    $directionLabel = match ($direction) {
        'income'  => 'Income',
        'expense' => 'Expense',
        'both'    => 'Income + Expense',
    };

    // Build series payload for SvgChart::line — keyed by category name → array of monthly values
    $seriesForChart = [];
    $colorMap       = [];
    foreach ($data['series'] as $s) {
        $seriesForChart[$s['name']] = $s['monthly'];
        $colorMap[$s['name']]       = $s['color'];
    }
@endphp

@include('finance.reports._shell', compact('reportTitle', 'period', 'exportRoutes'))

{{-- ── Direction toggle ─────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center gap-2 px-4 py-3 mb-5 border border-gray-200 rounded-xl bg-white shadow-sm">
    <span class="text-sm font-semibold text-gray-700 mr-1">Show</span>
    @php
        $linkBase = array_filter([
            'period'  => request('period'),
            'from'    => request('from'),
            'to'      => request('to'),
            'compare' => request('compare'),
        ]);
    @endphp
    @foreach (['income' => 'Income', 'expense' => 'Expense', 'both' => 'Both'] as $key => $label)
        <a href="{{ url()->current() . '?' . http_build_query(array_merge($linkBase, ['direction' => $key])) }}"
           class="px-3 py-1.5 text-xs font-semibold border rounded-lg transition-colors
                  {{ $direction === $key ? 'bg-navy-700 text-white border-navy-700' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:text-gray-800' }}">
            {{ $label }}
        </a>
    @endforeach

    <span class="ml-auto text-sm text-gray-500">
        {{ $directionLabel }}, monthly buckets
    </span>
</div>

{{-- ── KPI strip ────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Period Total</p>
        <p class="text-2xl font-bold text-navy-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['totals']['period']) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">{{ count($data['series']) }} {{ count($data['series']) === 1 ? 'category' : 'categories' }} · {{ count($data['months']) }} months</p>
    </div>
    <div class="bg-white rounded-2xl border border-green-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-green-600 mb-1">Top Grower</p>
        @if ($data['leaders']['top_grower'] && $data['leaders']['top_grower']['delta'] !== null && $data['leaders']['top_grower']['delta'] > 0)
            <p class="text-base font-bold text-green-700 truncate" title="{{ $data['leaders']['top_grower']['name'] }}">{{ $data['leaders']['top_grower']['name'] }}</p>
            <p class="text-sm text-green-600 tabular-nums mt-0.5">▲ {{ number_format($data['leaders']['top_grower']['delta'] * 100, 0) }}% first → last month</p>
        @else
            <p class="text-base font-bold text-gray-300">—</p>
            <p class="text-xs text-gray-500 mt-1.5">no growth detected</p>
        @endif
    </div>
    <div class="bg-white rounded-2xl border border-red-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-red-600 mb-1">Top Shrinker</p>
        @if ($data['leaders']['top_shrinker'] && $data['leaders']['top_shrinker']['delta'] !== null && $data['leaders']['top_shrinker']['delta'] < 0)
            <p class="text-base font-bold text-red-700 truncate" title="{{ $data['leaders']['top_shrinker']['name'] }}">{{ $data['leaders']['top_shrinker']['name'] }}</p>
            <p class="text-sm text-red-600 tabular-nums mt-0.5">▼ {{ number_format(abs($data['leaders']['top_shrinker']['delta']) * 100, 0) }}% first → last month</p>
        @else
            <p class="text-base font-bold text-gray-300">—</p>
            <p class="text-xs text-gray-500 mt-1.5">no decline detected</p>
        @endif
    </div>
</div>

{{-- ── Multi-line trend chart ──────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-bold text-gray-800">Monthly Trend</h3>
        <span class="text-xs text-gray-500">{{ $directionLabel }} · {{ $period['label'] }}</span>
    </div>

    @if (empty($data['series']) || $data['totals']['period'] == 0)
        <div class="h-48 flex items-center justify-center text-gray-400 text-sm">No data for this period.</div>
    @else
        <div class="overflow-x-auto">
            {!! \App\Support\SvgChart::line($seriesForChart, $data['month_labels'], [
                'width'  => 1100,
                'height' => 280,
                'colors' => $colorMap,
            ]) !!}
        </div>

        {{-- Legend below chart ─────────────────────────────────── --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-1.5 mt-4 text-xs">
            @foreach ($data['series'] as $s)
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-sm flex-shrink-0" style="background: {{ $s['color'] }};"></span>
                    <span class="text-gray-700 flex-1 truncate" title="{{ $s['name'] }}">{{ $s['name'] }}</span>
                    <span class="text-gray-900 font-semibold tabular-nums">{{ \App\Services\FinanceReportService::usd($s['total']) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ── Detail table — months × categories ──────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="text-sm font-bold text-gray-800">Monthly Breakdown</h3>
        <span class="text-xs text-gray-500">Largest first</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/40 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="text-left px-4 py-3 sticky left-0 bg-gray-50/40">Category</th>
                    @foreach ($data['month_labels'] as $label)
                        <th class="text-right px-3 py-3 whitespace-nowrap">{{ $label }}</th>
                    @endforeach
                    <th class="text-right px-4 py-3 bg-gray-100">Total</th>
                    <th class="text-right px-4 py-3">Δ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($data['series'] as $s)
                    <tr class="hover:bg-gray-50/60">
                        <td class="px-4 py-2.5 sticky left-0 bg-white">
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background: {{ $s['color'] }};"></span>
                                <span class="text-gray-900 font-medium truncate" title="{{ $s['name'] }}">{{ $s['name'] }}</span>
                            </div>
                        </td>
                        @foreach ($s['monthly'] as $v)
                            <td class="px-3 py-2.5 text-right tabular-nums {{ $v == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                {{ $v == 0 ? '—' : \App\Services\FinanceReportService::usd($v) }}
                            </td>
                        @endforeach
                        <td class="px-4 py-2.5 text-right tabular-nums font-semibold text-gray-900 bg-gray-50/80">
                            {{ \App\Services\FinanceReportService::usd($s['total']) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            @if ($s['delta'] !== null)
                                <span class="text-xs {{ $s['delta'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $s['delta'] >= 0 ? '+' : '' }}{{ number_format($s['delta'] * 100, 0) }}%
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($data['month_labels']) + 3 }}" class="px-5 py-10 text-center text-gray-400">No completed transactions for this period.</td></tr>
                @endforelse

                @if (! empty($data['series']))
                    <tr class="border-t-2 border-gray-300 bg-gray-50">
                        <td class="px-4 py-3 font-extrabold uppercase tracking-wide text-sm text-gray-900 sticky left-0 bg-gray-50">Total</td>
                        @foreach ($data['totals']['months'] as $v)
                            <td class="px-3 py-3 text-right tabular-nums font-bold text-gray-800">
                                {{ \App\Services\FinanceReportService::usd($v) }}
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right tabular-nums font-extrabold text-base text-navy-700 bg-gray-100">
                            {{ \App\Services\FinanceReportService::usd($data['totals']['period']) }}
                        </td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- ── Insights panel ─────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center gap-2 mb-3">
        <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm.75 13.5h-1.5v-6h1.5v6Zm0-7.5h-1.5v-1.5h1.5v1.5Z"/></svg>
        <h3 class="text-sm font-bold text-gray-800">Insights</h3>
    </div>
    <ul class="space-y-2 text-sm text-gray-700">
        @foreach ($data['insights'] as $bullet)
            <li class="flex items-start gap-2">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-navy-700 flex-shrink-0 mt-2"></span>
                <span class="leading-relaxed">{{ $bullet }}</span>
            </li>
        @endforeach
    </ul>
</div>

@endsection
