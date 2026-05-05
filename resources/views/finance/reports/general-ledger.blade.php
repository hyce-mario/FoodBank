@extends('layouts.app')
@section('title', 'General Ledger')

@section('content')

@php
    $reportTitle  = 'General Ledger';
    $exportRoutes = [
        'print' => 'finance.reports.general-ledger.print',
        'pdf'   => 'finance.reports.general-ledger.pdf',
        'csv'   => 'finance.reports.general-ledger.csv',
    ];
@endphp

@include('finance.reports._shell', compact('reportTitle', 'period', 'exportRoutes'))

{{-- ── Filter row ────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ url()->current() }}"
      class="flex flex-wrap items-center gap-2 px-4 py-3 mb-5 border border-gray-200 rounded-xl bg-white shadow-sm">
    @foreach (['period', 'from', 'to'] as $k)
        @if ($v = request($k))
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach

    <input type="text" name="source" value="{{ $filters['source'] ?? '' }}"
           placeholder="Search source / payee / title..."
           class="flex-1 min-w-[180px] px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                  placeholder:text-gray-400">

    <select name="type"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">All types</option>
        <option value="income"  @selected(($filters['type'] ?? '') === 'income')>Income only</option>
        <option value="expense" @selected(($filters['type'] ?? '') === 'expense')>Expense only</option>
    </select>

    <select name="category_id"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">All categories</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected((int)($filters['category_id'] ?? 0) === $c->id)>
                {{ $c->name }} ({{ ucfirst($c->type) }})
            </option>
        @endforeach
    </select>

    <select name="status"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">All statuses</option>
        <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed only</option>
        <option value="pending"   @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
        <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Cancelled</option>
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
        <a href="{{ url()->current() . '?' . http_build_query(array_filter(['period' => request('period'), 'from' => request('from'), 'to' => request('to')])) }}"
           class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Clear
        </a>
    @endif
</form>

{{-- ── KPI strip ────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Total Inflow</p>
        <p class="text-2xl font-bold text-green-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['total_in']) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">income, completed only</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Total Outflow</p>
        <p class="text-2xl font-bold text-red-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['total_out']) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">expense, completed only</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Net Change</p>
        <p class="text-2xl font-bold tabular-nums {{ $data['net_change'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
            {{ $data['net_change'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['net_change']) }}
        </p>
        <p class="text-xs text-gray-500 mt-1.5">{{ $data['count'] }} {{ $data['count'] === 1 ? 'row' : 'rows' }} ({{ $data['counted'] }} completed)</p>
    </div>
</div>

{{-- ── Ledger table ──────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="text-sm font-bold text-gray-800">Transactions</h3>
        <span class="text-xs text-gray-500">{{ $data['count'] }} {{ $data['count'] === 1 ? 'row' : 'rows' }}, ordered earliest first</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/40 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="text-left px-4 py-3" style="width:90px;">Date</th>
                    <th class="text-left px-4 py-3" style="width:60px;">Type</th>
                    <th class="text-left px-4 py-3">Title / Source</th>
                    <th class="text-left px-4 py-3">Category</th>
                    <th class="text-left px-4 py-3">Reference</th>
                    <th class="text-left px-4 py-3" style="width:80px;">Status</th>
                    <th class="text-right px-4 py-3" style="width:110px;">Amount</th>
                    <th class="text-right px-4 py-3" style="width:120px;">Running Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($data['rows'] as $row)
                    <tr class="hover:bg-gray-50/60 {{ ! $row['counted'] ? 'opacity-60' : '' }}">
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
                            @if ($row['event'])
                                <div class="text-xs text-gray-400 mt-0.5">Event: {{ $row['event'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $row['category'] }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs tabular-nums">{{ $row['reference'] ?: '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if ($row['status'] === 'completed')
                                <span class="text-xs font-semibold text-gray-600">Completed</span>
                            @elseif ($row['status'] === 'pending')
                                <span class="text-xs font-semibold text-amber-700">Pending</span>
                            @elseif ($row['status'] === 'cancelled')
                                <span class="text-xs font-semibold text-gray-400 line-through">Cancelled</span>
                            @else
                                <span class="text-xs text-gray-400">{{ ucfirst((string) $row['status']) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-semibold {{ $row['type'] === 'income' ? 'text-green-700' : 'text-red-700' }}">
                            {{ $row['type'] === 'expense' ? '-' : '+' }}{{ \App\Services\FinanceReportService::usd($row['amount']) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums {{ ($row['running_balance'] ?? 0) >= 0 ? 'text-gray-900' : 'text-red-700' }}">
                            @if ($row['running_balance'] !== null)
                                {{ \App\Services\FinanceReportService::usd($row['running_balance']) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No transactions match the applied filters.</td></tr>
                @endforelse

                @if (! empty($data['rows']))
                    <tr class="border-t-2 border-gray-300 bg-gray-50">
                        <td colspan="6" class="px-4 py-3 font-extrabold uppercase tracking-wide text-sm text-gray-900">Closing Balance</td>
                        <td></td>
                        <td class="px-4 py-3 text-right tabular-nums font-extrabold text-base {{ $data['closing_balance'] >= 0 ? 'text-navy-700' : 'text-red-700' }}">
                            {{ \App\Services\FinanceReportService::usd($data['closing_balance']) }}
                        </td>
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
