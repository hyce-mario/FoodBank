@extends('layouts.app')
@section('title', 'Income Detail Report')

@section('content')

@php
    $reportTitle  = 'Income Detail Report';
    $exportRoutes = [
        'print' => 'finance.reports.income-detail.print',
        'pdf'   => 'finance.reports.income-detail.pdf',
        'csv'   => 'finance.reports.income-detail.csv',
    ];

    $hasCompare = ! empty($period['compare']);

    $stackedSegments = collect($data['by_category'])->map(fn ($c) => [
        'label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color'],
    ])->values()->all();
@endphp

@include('finance.reports._shell', compact('reportTitle', 'period', 'exportRoutes'))

{{-- ── Filter row ────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ url()->current() }}"
      class="flex flex-wrap items-center gap-2 px-4 py-3 mb-5 border border-gray-200 rounded-xl bg-white shadow-sm">
    {{-- Persist active period through filter submits --}}
    @foreach (['period', 'from', 'to', 'compare'] as $k)
        @if ($v = request($k))
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach

    <input type="text" name="source" value="{{ $filters['source'] ?? '' }}"
           placeholder="Search donor / source..."
           class="flex-1 min-w-[180px] px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                  placeholder:text-gray-400">

    <select name="category_id"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">All categories</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected((int)($filters['category_id'] ?? 0) === $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>

    <select name="event_id"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">All events</option>
        @foreach ($events as $e)
            <option value="{{ $e->id }}" @selected((int)($filters['event_id'] ?? 0) === $e->id)>
                {{ $e->name }} ({{ $e->date->format('M Y') }})
            </option>
        @endforeach
    </select>

    <button type="submit"
            class="px-4 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
        Apply
    </button>
    @if (! empty($filters))
        <a href="{{ url()->current() . '?' . http_build_query(array_filter(['period' => request('period'), 'from' => request('from'), 'to' => request('to'), 'compare' => request('compare')])) }}"
           class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Clear
        </a>
    @endif
</form>

{{-- ── KPI strip — always 3-up ───────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Total Income</p>
        <p class="text-2xl font-bold text-navy-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['total']) }}</p>
        @if ($hasCompare && $data['prior_total'] !== null && $data['prior_total'] > 0)
            @php $delta = ($data['total'] - $data['prior_total']) / $data['prior_total']; @endphp
            <p class="text-xs mt-1.5 {{ $delta >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. {{ $period['compare']['label'] }}
            </p>
        @endif
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Transactions</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $data['count'] }}</p>
        <p class="text-xs text-gray-500 mt-1.5">across {{ count($data['by_category']) }} {{ count($data['by_category']) === 1 ? 'category' : 'categories' }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Top Donor</p>
        @if ($data['top_source'])
            <p class="text-base font-bold text-gray-900 leading-tight truncate" title="{{ $data['top_source']['name'] }}">{{ $data['top_source']['name'] }}</p>
            <p class="text-sm text-gray-500 tabular-nums mt-0.5">{{ \App\Services\FinanceReportService::usd($data['top_source']['amount']) }}</p>
        @else
            <p class="text-sm text-gray-400">—</p>
        @endif
    </div>
</div>

{{-- ── Charts: stacked bar (proportions) ─────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-bold text-gray-800">Composition</h3>
        <span class="text-xs text-gray-500">{{ $period['label'] }}</span>
    </div>
    <div>
        {!! \App\Support\SvgChart::horizontalStackedBar($stackedSegments, ['width' => 1100, 'height' => 30]) !!}
    </div>
    @if (! empty($data['by_category']))
        <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-1.5 mt-4 text-xs">
            @foreach ($data['by_category'] as $cat)
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-sm flex-shrink-0" style="background: {{ $cat['color'] }};"></span>
                    <span class="text-gray-700 flex-1 truncate">{{ $cat['name'] }}</span>
                    <span class="text-gray-500 tabular-nums">{{ number_format($cat['share'] * 100, 0) }}%</span>
                    <span class="text-gray-900 font-semibold tabular-nums">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ── Detail table ──────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="text-sm font-bold text-gray-800">Transactions</h3>
        <span class="text-xs text-gray-500">{{ $data['count'] }} {{ $data['count'] === 1 ? 'row' : 'rows' }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/40 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="text-left px-5 py-3" style="width:90px;">Date</th>
                    <th class="text-left px-5 py-3">Title / Source</th>
                    <th class="text-left px-5 py-3">Category</th>
                    <th class="text-left px-5 py-3">Event</th>
                    <th class="text-right px-5 py-3" style="width:120px;">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($data['by_category'] as $cat)
                    <tr class="bg-gray-50/40">
                        <td colspan="5" class="px-5 py-2 text-xs font-bold uppercase tracking-widest text-navy-700 flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background: {{ $cat['color'] }};"></span>
                            {{ $cat['name'] }}
                            <span class="ml-auto text-gray-500 normal-case font-normal tracking-normal">
                                {{ $cat['count'] }} {{ $cat['count'] === 1 ? 'txn' : 'txns' }} · {{ \App\Services\FinanceReportService::usd($cat['amount']) }}
                                @if ($hasCompare && $cat['delta'] !== null)
                                    <span class="ml-2 {{ $cat['delta'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $cat['delta'] >= 0 ? '+' : '' }}{{ number_format($cat['delta'] * 100, 1) }}% vs prior
                                    </span>
                                @endif
                            </span>
                        </td>
                    </tr>
                    @foreach (collect($data['rows'])->where('category', $cat['name']) as $row)
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-5 py-2.5 text-gray-600 tabular-nums">{{ $row['date'] }}</td>
                            <td class="px-5 py-2.5">
                                <span class="text-gray-900 font-medium">{{ $row['title'] }}</span>
                                @if ($row['source'])
                                    <span class="text-gray-400 text-xs">— {{ $row['source'] }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-gray-600">{{ $row['category'] }}</td>
                            <td class="px-5 py-2.5 text-gray-500 text-xs">{{ $row['event'] ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-900 font-semibold">{{ \App\Services\FinanceReportService::usd($row['amount']) }}</td>
                        </tr>
                    @endforeach
                @endforeach

                @if (empty($data['rows']))
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">No income transactions match the applied filters.</td></tr>
                @endif

                <tr class="border-t-2 border-gray-300 bg-gray-50">
                    <td colspan="4" class="px-5 py-3 font-extrabold uppercase tracking-wide text-sm text-gray-900">Total Income</td>
                    <td class="px-5 py-3 text-right tabular-nums font-extrabold text-base text-navy-700">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
                </tr>
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
