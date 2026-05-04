@extends('layouts.app')
@section('title', 'Statement of Activities')

@section('content')

@php
    $reportTitle  = 'Statement of Activities';
    $exportRoutes = [
        'print' => 'finance.reports.statement-of-activities.print',
        'pdf'   => 'finance.reports.statement-of-activities.pdf',
        'csv'   => 'finance.reports.statement-of-activities.csv',
    ];

    $hasCompare = ! empty($period['compare']);
    $netChange  = $data['net_change'];
    $netClass   = $netChange >= 0 ? 'text-green-700' : 'text-red-700';

    $incomeSegments = collect($data['income']['categories'])->map(fn ($c) => [
        'label' => $c['name'],
        'value' => $c['amount'],
        'color' => $c['color'],
    ])->values()->all();

    $expenseSegments = collect($data['expense']['categories'])->map(fn ($c) => [
        'label' => $c['name'],
        'value' => $c['amount'],
        'color' => $c['color'],
    ])->values()->all();
@endphp

@include('finance.reports._shell', compact('reportTitle', 'period', 'exportRoutes'))

{{-- ── KPI strip — always 3-up so Income / Expenses / Change in Net
     Assets stay on a single row at every screen width ──────────── --}}
<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Income</p>
        <p class="text-2xl font-bold text-green-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</p>
        @if ($hasCompare && isset($data['income']['prior_total']))
            @php $prior = $data['income']['prior_total']; @endphp
            @if ($prior > 0)
                @php $delta = ($data['income']['total'] - $prior) / $prior; @endphp
                <p class="text-xs mt-1.5 {{ $delta >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. {{ $period['compare']['label'] }}
                </p>
            @else
                <p class="text-xs mt-1.5 text-gray-500">— no prior period data</p>
            @endif
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Expenses</p>
        <p class="text-2xl font-bold text-red-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</p>
        @if ($hasCompare && isset($data['expense']['prior_total']))
            @php $prior = $data['expense']['prior_total']; @endphp
            @if ($prior > 0)
                @php $delta = ($data['expense']['total'] - $prior) / $prior; @endphp
                {{-- For expenses, ▲ is bad — flip the color semantics --}}
                <p class="text-xs mt-1.5 {{ $delta <= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. {{ $period['compare']['label'] }}
                </p>
            @else
                <p class="text-xs mt-1.5 text-gray-500">— no prior period data</p>
            @endif
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Change in Net Assets</p>
        <p class="text-2xl font-bold tabular-nums {{ $netClass }}">
            {{ $netChange >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($netChange) }}
        </p>
        @if ($hasCompare && $data['prior_net'] !== null)
            <p class="text-xs mt-1.5 text-gray-500">
                Prior: {{ $data['prior_net'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['prior_net']) }}
            </p>
        @endif
    </div>
</div>

{{-- ── Charts: dual donut — always 2-up so Income by Category and
     Expenses by Category sit side-by-side at every screen width --}}
<div class="grid grid-cols-2 gap-5 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Income by Category</h3>
        <p class="text-xs text-gray-400 mb-4">{{ $period['label'] }}</p>
        <div class="flex flex-col items-center">
            {!! \App\Support\SvgChart::donut($incomeSegments, [
                'width' => 280,
                'height' => 240,
                'center_label' => \App\Services\FinanceReportService::usd($data['income']['total']),
                'center_sub' => 'Total Income',
            ]) !!}
            @if (! empty($incomeSegments))
                <ul class="mt-3 space-y-1 text-xs w-full">
                    @foreach ($data['income']['categories'] as $cat)
                        <li class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-sm flex-shrink-0" style="background: {{ $cat['color'] }};"></span>
                            <span class="text-gray-700 flex-1 truncate">{{ $cat['name'] }}</span>
                            <span class="text-gray-500 tabular-nums">{{ number_format($cat['share'] * 100, 0) }}%</span>
                            <span class="text-gray-900 font-semibold tabular-nums">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Expenses by Category</h3>
        <p class="text-xs text-gray-400 mb-4">{{ $period['label'] }}</p>
        <div class="flex flex-col items-center">
            {!! \App\Support\SvgChart::donut($expenseSegments, [
                'width' => 280,
                'height' => 240,
                'center_label' => \App\Services\FinanceReportService::usd($data['expense']['total']),
                'center_sub' => 'Total Expenses',
            ]) !!}
            @if (! empty($expenseSegments))
                <ul class="mt-3 space-y-1 text-xs w-full">
                    @foreach ($data['expense']['categories'] as $cat)
                        <li class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-sm flex-shrink-0" style="background: {{ $cat['color'] }};"></span>
                            <span class="text-gray-700 flex-1 truncate">{{ $cat['name'] }}</span>
                            <span class="text-gray-500 tabular-nums">{{ number_format($cat['share'] * 100, 0) }}%</span>
                            <span class="text-gray-900 font-semibold tabular-nums">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

{{-- ── Detail table ──────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="text-sm font-bold text-gray-800">Detail</h3>
        @if ($hasCompare)
            <p class="text-xs text-gray-500">Side-by-side: <strong class="text-gray-700">{{ $period['label'] }}</strong> vs <strong class="text-gray-700">{{ $period['compare']['label'] }}</strong></p>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/40 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="text-left px-5 py-3">Category</th>
                    <th class="text-right px-5 py-3">Amount</th>
                    @if ($hasCompare)
                        <th class="text-right px-5 py-3">Prior Period</th>
                        <th class="text-right px-5 py-3">Δ %</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                {{-- Revenue section ─────────────────────────────────────── --}}
                <tr class="bg-green-50/40">
                    <td colspan="{{ $hasCompare ? 4 : 2 }}" class="px-5 py-2 text-xs font-bold uppercase tracking-widest text-green-700">Revenue</td>
                </tr>
                @forelse ($data['income']['categories'] as $cat)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-2.5">
                            <a href="{{ route('finance.transactions.index', ['type' => 'income', 'date_from' => $period['from']->toDateString(), 'date_to' => $period['to']->toDateString()]) }}"
                               class="text-gray-900 hover:text-navy-700 hover:underline">
                                {{ $cat['name'] }}
                            </a>
                        </td>
                        <td class="px-5 py-2.5 text-right tabular-nums text-gray-900 font-semibold">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                        @if ($hasCompare)
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-600">{{ \App\Services\FinanceReportService::usd($cat['prior_amount'] ?? 0) }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">
                                @if ($cat['delta'] !== null)
                                    <span class="{{ $cat['delta'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $cat['delta'] >= 0 ? '+' : '' }}{{ number_format($cat['delta'] * 100, 1) }}%
                                    </span>
                                @else
                                    <span class="text-gray-400">new</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $hasCompare ? 4 : 2 }}" class="px-5 py-3 text-gray-400 text-center">No income recorded.</td></tr>
                @endforelse
                <tr class="border-t border-green-100 bg-green-50/60">
                    <td class="px-5 py-2.5 font-bold text-green-800">Total Revenue</td>
                    <td class="px-5 py-2.5 text-right tabular-nums font-bold text-green-800">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</td>
                    @if ($hasCompare)
                        <td class="px-5 py-2.5 text-right tabular-nums text-green-800 font-semibold">{{ \App\Services\FinanceReportService::usd($data['income']['prior_total'] ?? 0) }}</td>
                        <td></td>
                    @endif
                </tr>

                {{-- Expense section ───────────────────────────────────────── --}}
                <tr class="bg-red-50/40">
                    <td colspan="{{ $hasCompare ? 4 : 2 }}" class="px-5 py-2 text-xs font-bold uppercase tracking-widest text-red-700">Expenses</td>
                </tr>
                @forelse ($data['expense']['categories'] as $cat)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-2.5">
                            <a href="{{ route('finance.transactions.index', ['type' => 'expense', 'date_from' => $period['from']->toDateString(), 'date_to' => $period['to']->toDateString()]) }}"
                               class="text-gray-900 hover:text-navy-700 hover:underline">
                                {{ $cat['name'] }}
                            </a>
                        </td>
                        <td class="px-5 py-2.5 text-right tabular-nums text-gray-900 font-semibold">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                        @if ($hasCompare)
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-600">{{ \App\Services\FinanceReportService::usd($cat['prior_amount'] ?? 0) }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">
                                @if ($cat['delta'] !== null)
                                    {{-- For expenses, ▲ is bad → red, ▼ is good → green --}}
                                    <span class="{{ $cat['delta'] <= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $cat['delta'] >= 0 ? '+' : '' }}{{ number_format($cat['delta'] * 100, 1) }}%
                                    </span>
                                @else
                                    <span class="text-gray-400">new</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $hasCompare ? 4 : 2 }}" class="px-5 py-3 text-gray-400 text-center">No expenses recorded.</td></tr>
                @endforelse
                <tr class="border-t border-red-100 bg-red-50/60">
                    <td class="px-5 py-2.5 font-bold text-red-800">Total Expenses</td>
                    <td class="px-5 py-2.5 text-right tabular-nums font-bold text-red-800">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</td>
                    @if ($hasCompare)
                        <td class="px-5 py-2.5 text-right tabular-nums text-red-800 font-semibold">{{ \App\Services\FinanceReportService::usd($data['expense']['prior_total'] ?? 0) }}</td>
                        <td></td>
                    @endif
                </tr>

                {{-- Change in Net Assets ────────────────────────────────── --}}
                <tr class="border-t-2 border-gray-300 bg-gray-50">
                    <td class="px-5 py-3 font-extrabold uppercase tracking-wide text-sm text-gray-900">Change in Net Assets</td>
                    <td class="px-5 py-3 text-right tabular-nums font-extrabold text-base {{ $netClass }}">
                        {{ $netChange >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($netChange) }}
                    </td>
                    @if ($hasCompare)
                        <td class="px-5 py-3 text-right tabular-nums font-bold {{ ($data['prior_net'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            @if ($data['prior_net'] !== null)
                                {{ $data['prior_net'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['prior_net']) }}
                            @endif
                        </td>
                        <td></td>
                    @endif
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ── Insights panel — board-pitch differentiator ─────────────────── --}}
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
