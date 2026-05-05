@extends('layouts.app')
@section('title', $reportTitle)

@section('content')

@php
    // $exportRoutes is passed from the controller (donor-analysis.* or vendor-analysis.*)
    $hasCompare = ! empty($period['compare']);

    $donutSegments = collect($data['donors'])->map(fn ($d) => [
        'label' => $d['name'], 'value' => $d['total'], 'color' => $d['color'],
    ])->values()->all();
@endphp

@include('finance.reports._shell', compact('reportTitle', 'period', 'exportRoutes'))

{{-- ── Filter row ────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ url()->current() }}"
      class="flex flex-wrap items-center gap-2 px-4 py-3 mb-5 border border-gray-200 rounded-xl bg-white shadow-sm">
    @foreach (['period', 'from', 'to', 'compare'] as $k)
        @if ($v = request($k))
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach

    <input type="text" name="source" value="{{ $filters['source'] ?? '' }}"
           placeholder="Search {{ $entityLabel }} name..."
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

{{-- ── KPI strip — 4-up: Total / Unique Donors / Avg Gift / Retention ── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">{{ $totalLabel }}</p>
        <p class="text-2xl font-bold text-navy-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['total']) }}</p>
        @if ($hasCompare && $data['prior_total'] !== null && $data['prior_total'] > 0)
            @php $delta = ($data['total'] - $data['prior_total']) / $data['prior_total']; @endphp
            <p class="text-xs mt-1.5 {{ $delta >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. {{ $period['compare']['label'] }}
            </p>
        @endif
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Unique {{ ucfirst($entityLabelPlural) }}</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $data['donor_total_count'] }}</p>
        <p class="text-xs text-gray-500 mt-1.5">{{ $data['gift_count'] }} {{ $entityLabel === 'donor' ? 'gifts' : 'payments' }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Avg {{ $entityLabel === 'donor' ? 'Gift' : 'Payment' }}</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['avg_gift']) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Retention</p>
        @if ($data['retention_rate'] !== null)
            <p class="text-2xl font-bold tabular-nums {{ $data['retention_rate'] >= 0.5 ? 'text-green-700' : 'text-amber-700' }}">
                {{ number_format($data['retention_rate'] * 100, 0) }}%
            </p>
            <p class="text-xs text-gray-500 mt-1.5">
                of last period's {{ $entityLabelPlural }} {{ $entityLabel === 'donor' ? 'gave again' : 'were paid again' }}
            </p>
        @else
            <p class="text-2xl font-bold text-gray-300 tabular-nums">—</p>
            <p class="text-xs text-gray-500 mt-1.5">no prior period data</p>
        @endif
    </div>
</div>

{{-- ── Top donors table + donut share chart ──────────────────────────── --}}
<div class="grid md:grid-cols-3 gap-5 mb-5">

    {{-- Donut: share-of-total by top donor ────────────────────────── --}}
    <div class="md:col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Share of Total</h3>
        <p class="text-xs text-gray-400 mb-4">Top {{ count($data['donors']) }} {{ $entityLabelPlural }}, {{ $period['label'] }}</p>
        <div class="flex flex-col items-center">
            {!! \App\Support\SvgChart::donut($donutSegments, [
                'width'  => 240,
                'height' => 240,
                'center_label' => \App\Services\FinanceReportService::usd($data['total']),
                'center_sub'   => $totalLabel,
            ]) !!}
        </div>
    </div>

    {{-- Top-10 table ──────────────────────────────────────────────── --}}
    <div class="md:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden"
         x-data="{ showAll: false }">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800">
                Top {{ ucfirst($entityLabelPlural) }}
                @if ($data['donor_total_count'] > 10)
                    <span class="text-xs text-gray-500 font-normal">(of {{ $data['donor_total_count'] }})</span>
                @endif
            </h3>
            @if ($data['donor_total_count'] > 10)
                <button type="button" x-on:click="showAll = !showAll"
                        class="text-xs font-semibold text-navy-700 hover:text-navy-800">
                    <span x-show="!showAll">Show all {{ $data['donor_total_count'] }}</span>
                    <span x-show="showAll" x-cloak>Show top 10</span>
                </button>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/40 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="text-left px-4 py-3">#</th>
                        <th class="text-left px-4 py-3">{{ ucfirst($entityLabel) }}</th>
                        <th class="text-right px-4 py-3">Total</th>
                        <th class="text-center px-4 py-3 hidden md:table-cell">Gifts</th>
                        <th class="text-right px-4 py-3 hidden md:table-cell">Avg</th>
                        <th class="text-center px-4 py-3 hidden lg:table-cell">12-mo Trend</th>
                        @if ($hasCompare)
                            <th class="text-right px-4 py-3 hidden md:table-cell">vs. Prior</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse (($data['donors'] ?? []) as $i => $donor)
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-4 py-2.5 text-gray-400 tabular-nums text-sm">{{ $i + 1 }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background: {{ $donor['color'] }};"></span>
                                    <span class="text-gray-900 font-medium truncate" title="{{ $donor['name'] }}">{{ $donor['name'] }}</span>
                                    @if ($donor['is_new'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700">NEW</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5 truncate">
                                    {{ $donor['count'] }} {{ $donor['count'] === 1 ? ($entityLabel === 'donor' ? 'gift' : 'payment') : ($entityLabel === 'donor' ? 'gifts' : 'payments') }} ·
                                    last: {{ $donor['last_gift'] ?: '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-semibold text-gray-900">
                                {{ \App\Services\FinanceReportService::usd($donor['total']) }}
                                <div class="text-xs text-gray-400 font-normal">{{ number_format($donor['share'] * 100, 0) }}%</div>
                            </td>
                            <td class="px-4 py-2.5 text-center text-gray-600 tabular-nums hidden md:table-cell">{{ $donor['count'] }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums hidden md:table-cell">{{ \App\Services\FinanceReportService::usd($donor['avg_gift']) }}</td>
                            <td class="px-4 py-2.5 text-center hidden lg:table-cell">
                                {!! \App\Support\SvgChart::sparkline($donor['sparkline'], ['color' => $donor['color']]) !!}
                            </td>
                            @if ($hasCompare)
                                <td class="px-4 py-2.5 text-right tabular-nums text-xs hidden md:table-cell">
                                    @if ($donor['delta'] !== null)
                                        <span class="{{ $donor['delta'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $donor['delta'] >= 0 ? '+' : '' }}{{ number_format($donor['delta'] * 100, 0) }}%
                                        </span>
                                    @elseif ($donor['is_new'])
                                        <span class="text-amber-600 font-semibold">new</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $hasCompare ? 7 : 6 }}" class="px-5 py-10 text-center text-gray-400">No {{ $entityLabel }} activity in this period.</td></tr>
                    @endforelse

                    {{-- Hidden rows 11..N — revealed by Show all ────────────── --}}
                    @if ($data['donor_total_count'] > 10)
                        @foreach (array_slice($data['all_donors'], 10) as $i => $donor)
                            <tr x-show="showAll" x-cloak class="hover:bg-gray-50/60">
                                <td class="px-4 py-2.5 text-gray-400 tabular-nums text-sm">{{ $i + 11 }}</td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background: {{ $donor['color'] }};"></span>
                                        <span class="text-gray-700 truncate" title="{{ $donor['name'] }}">{{ $donor['name'] }}</span>
                                        @if ($donor['is_new'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700">NEW</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-gray-700">{{ \App\Services\FinanceReportService::usd($donor['total']) }}</td>
                                <td class="px-4 py-2.5 text-center text-gray-600 tabular-nums hidden md:table-cell">{{ $donor['count'] }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums hidden md:table-cell">{{ \App\Services\FinanceReportService::usd($donor['avg_gift']) }}</td>
                                <td class="px-4 py-2.5 hidden lg:table-cell"></td>
                                @if ($hasCompare)
                                    <td class="px-4 py-2.5 text-right tabular-nums text-xs hidden md:table-cell">
                                        @if ($donor['delta'] !== null)
                                            <span class="{{ $donor['delta'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                                {{ $donor['delta'] >= 0 ? '+' : '' }}{{ number_format($donor['delta'] * 100, 0) }}%
                                            </span>
                                        @elseif ($donor['is_new'])
                                            <span class="text-amber-600 font-semibold">new</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Lapsed donors callout (compare only) ─────────────────────────── --}}
@if ($hasCompare && ! empty($data['lapsed']))
    <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden mb-5">
        <div class="px-5 py-3 border-b border-amber-100 bg-amber-50/60 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                <h3 class="text-sm font-bold text-amber-900">
                    {{ count($data['lapsed']) }} Lapsed {{ count($data['lapsed']) === 1 ? ucfirst($entityLabel) : ucfirst($entityLabelPlural) }}
                </h3>
            </div>
            <p class="text-xs text-amber-700">{{ $entityLabelPlural === 'donors' ? 'Gave' : 'Were paid' }} in {{ $period['compare']['label'] }} but not {{ $period['label'] }}</p>
        </div>
        <div class="px-5 py-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                @foreach ($data['lapsed'] as $l)
                    <div class="flex items-center justify-between py-1 border-b border-gray-50 last:border-b-0">
                        <span class="text-gray-700">{{ $l['name'] }}</span>
                        <span class="text-gray-500 tabular-nums text-xs">{{ \App\Services\FinanceReportService::usd($l['prior_total']) }} prior</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

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
