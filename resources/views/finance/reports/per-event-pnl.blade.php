@extends('layouts.app')
@section('title', 'Per-Event P&L')

@section('content')

@php
    $reportTitle = 'Per-Event P&L';
    $hasData = $data !== null;

    $exportQuery = $eventId ? ['event_id' => $eventId] : [];
@endphp

{{-- ── Header ──────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a href="{{ route('finance.reports') }}"
               class="text-xs text-gray-400 hover:text-navy-700 inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                All reports
            </a>
        </div>
        <h1 class="text-xl font-bold text-gray-900">{{ $reportTitle }}</h1>
        @if ($hasData)
            <p class="text-xs text-gray-500 mt-0.5">{{ $data['event']['name'] }} · {{ \Carbon\Carbon::parse($data['event']['date'])->format('M j, Y') }}</p>
        @else
            <p class="text-xs text-gray-500 mt-0.5">Pick an event to see its income, expenses, and cost-per-beneficiary.</p>
        @endif
    </div>

    @if ($hasData)
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.reports.per-event-pnl.print', $exportQuery) }}"
               target="_blank" title="Print" aria-label="Print"
               class="w-9 h-9 inline-flex items-center justify-center border border-gray-300 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
            </a>
            <a href="{{ route('finance.reports.per-event-pnl.pdf', $exportQuery) }}"
               title="Download PDF" aria-label="Download PDF"
               class="w-9 h-9 inline-flex items-center justify-center border border-red-200 text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
            </a>
            <a href="{{ route('finance.reports.per-event-pnl.csv', $exportQuery) }}"
               title="Download CSV" aria-label="Download CSV"
               class="w-9 h-9 inline-flex items-center justify-center border border-green-200 text-green-700 hover:bg-green-50 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            </a>
        </div>
    @endif
</div>

@include('finance._nav')

{{-- ── Event picker ────────────────────────────────────────────────── --}}
<form method="GET" action="{{ url()->current() }}"
      class="flex flex-wrap items-center gap-2 px-4 py-3 mb-5 border border-gray-200 rounded-xl bg-white shadow-sm">
    <label class="text-sm font-semibold text-gray-700 mr-1">Event</label>
    <select name="event_id" onchange="this.form.requestSubmit()"
            class="flex-1 min-w-[260px] text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">— Select an event —</option>
        @foreach ($events as $e)
            <option value="{{ $e->id }}" @selected($eventId === $e->id)>
                {{ $e->name }} ({{ \Carbon\Carbon::parse($e->date)->format('M j, Y') }}) · {{ ucfirst((string) $e->status) }}
            </option>
        @endforeach
    </select>
</form>

@if (! $hasData)
    {{-- Empty state — show before any event is picked ─────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-12 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
        </svg>
        <h3 class="text-sm font-bold text-gray-700 mb-1">Pick an event from the dropdown above</h3>
        <p class="text-xs text-gray-500">Per-event P&amp;L surfaces income vs expense for a single event, plus cost-per-household and cost-per-person served.</p>
    </div>
@else
    @php
        $netClass = $data['net'] >= 0 ? 'text-green-700' : 'text-red-700';

        $incomeSegments = collect($data['income']['categories'])->map(fn ($c) => [
            'label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color'],
        ])->values()->all();
        $expenseSegments = collect($data['expense']['categories'])->map(fn ($c) => [
            'label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color'],
        ])->values()->all();
    @endphp

    {{-- KPI strip — financial line ──────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Income</p>
            <p class="text-2xl font-bold text-green-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Expense</p>
            <p class="text-2xl font-bold text-red-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Net</p>
            <p class="text-2xl font-bold tabular-nums {{ $netClass }}">
                {{ $data['net'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['net']) }}
            </p>
        </div>
    </div>

    {{-- KPI strip — beneficiary line ───────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Households Served</p>
            <p class="text-2xl font-bold text-navy-700 tabular-nums">{{ number_format($data['households_served']) }}</p>
            <p class="text-xs text-gray-500 mt-1.5">snapshot at visit time</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">People Served</p>
            <p class="text-2xl font-bold text-navy-700 tabular-nums">{{ number_format($data['people_served']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-700 mb-1">Cost / Household</p>
            @if ($data['cost_per_household'] !== null)
                <p class="text-2xl font-bold text-amber-800 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['cost_per_household']) }}</p>
            @else
                <p class="text-2xl font-bold text-gray-300 tabular-nums">—</p>
                <p class="text-xs text-gray-500 mt-1.5">no exited visits</p>
            @endif
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-700 mb-1">Cost / Person</p>
            @if ($data['cost_per_person'] !== null)
                <p class="text-2xl font-bold text-amber-800 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['cost_per_person']) }}</p>
            @else
                <p class="text-2xl font-bold text-gray-300 tabular-nums">—</p>
                <p class="text-xs text-gray-500 mt-1.5">no exited visits</p>
            @endif
        </div>
    </div>

    {{-- Dual donut: income / expense ─────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h3 class="text-sm font-bold text-gray-800 mb-1">Income by Category</h3>
            <p class="text-xs text-gray-400 mb-4">{{ $data['event']['name'] }}</p>
            <div class="flex flex-col items-center">
                {!! \App\Support\SvgChart::donut($incomeSegments, [
                    'width'  => 260, 'height' => 220,
                    'center_label' => \App\Services\FinanceReportService::usd($data['income']['total']),
                    'center_sub'   => 'Total Income',
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
            <h3 class="text-sm font-bold text-gray-800 mb-1">Expense by Category</h3>
            <p class="text-xs text-gray-400 mb-4">{{ $data['event']['name'] }}</p>
            <div class="flex flex-col items-center">
                {!! \App\Support\SvgChart::donut($expenseSegments, [
                    'width'  => 260, 'height' => 220,
                    'center_label' => \App\Services\FinanceReportService::usd($data['expense']['total']),
                    'center_sub'   => 'Total Expense',
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

    {{-- Transaction table ─────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800">All Transactions</h3>
            <span class="text-xs text-gray-500">{{ count($data['rows']) }} {{ count($data['rows']) === 1 ? 'row' : 'rows' }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/40 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="text-left px-4 py-3" style="width:90px;">Date</th>
                        <th class="text-left px-4 py-3" style="width:60px;">Type</th>
                        <th class="text-left px-4 py-3">Title / Source</th>
                        <th class="text-left px-4 py-3">Category</th>
                        <th class="text-right px-4 py-3" style="width:120px;">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($data['rows'] as $row)
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-4 py-2.5 text-gray-600 tabular-nums">{{ $row['date'] }}</td>
                            <td class="px-4 py-2.5">
                                @if ($row['type'] === 'income')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-green-50 text-green-700">In</span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-red-50 text-red-700">Out</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-gray-900 font-medium">{{ $row['title'] }}</span>
                                @if ($row['source'])
                                    <span class="text-gray-400 text-xs">— {{ $row['source'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $row['category'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-semibold {{ $row['type'] === 'income' ? 'text-green-700' : 'text-red-700' }}">
                                {{ $row['type'] === 'expense' ? '-' : '+' }}{{ \App\Services\FinanceReportService::usd($row['amount']) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">No completed finance transactions are linked to this event.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Insights ─────────────────────────────────────────────── --}}
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
@endif

@endsection
