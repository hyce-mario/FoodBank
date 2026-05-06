@extends('layouts.app')
@section('title', 'Budget vs. Actual')

@section('content')

@php
    $reportTitle  = 'Budget vs. Actual';
    $exportRoutes = [
        'print' => 'finance.reports.budget-vs-actual.print',
        'pdf'   => 'finance.reports.budget-vs-actual.pdf',
        'csv'   => 'finance.reports.budget-vs-actual.csv',
    ];

    $totals = $data['totals'];
@endphp

@include('finance.reports._shell', compact('reportTitle', 'period', 'exportRoutes'))

{{-- ── Direction + Event filter ────────────────────────────────────── --}}
<form method="GET" class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 flex flex-wrap items-center gap-2 mb-5">
    @foreach (request()->except(['direction', 'event_id']) as $k => $v)
        @if (! is_array($v))
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach
    <label class="text-xs font-semibold text-gray-500 uppercase">Direction</label>
    <select name="direction" onchange="this.form.submit()"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        <option value="expense" {{ $direction === 'expense' ? 'selected' : '' }}>Expense</option>
        <option value="income"  {{ $direction === 'income'  ? 'selected' : '' }}>Income</option>
        <option value="both"    {{ $direction === 'both'    ? 'selected' : '' }}>Both</option>
    </select>
    <label class="text-xs font-semibold text-gray-500 uppercase ml-2">Event</label>
    <select name="event_id" onchange="this.form.submit()"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        <option value="">— All scopes —</option>
        @foreach ($events as $ev)
            <option value="{{ $ev->id }}" {{ (int)$eventId === (int)$ev->id ? 'selected' : '' }}>{{ $ev->name }} ({{ $ev->date->format('M Y') }})</option>
        @endforeach
    </select>
</form>

{{-- ── KPI strip ───────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Total Budget</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ \App\Services\FinanceReportService::usd($totals['budget']) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Total Actual</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ \App\Services\FinanceReportService::usd($totals['actual']) }}</p>
    </div>
    @php
        // Variance colour semantics flip by direction:
        //   expense — positive variance (over budget) is red; negative is green
        //   income  — positive variance (over plan)  is green; negative is red
        $v = $totals['variance'];
        if ($direction === 'income') {
            $vClass = $v >= 0 ? 'text-green-700' : 'text-red-700';
        } else {
            $vClass = $v <= 0 ? 'text-green-700' : 'text-red-700';
        }
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Variance</p>
        <p class="text-2xl font-bold tabular-nums {{ $vClass }}">
            {{ $v >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($v) }}
        </p>
        @if ($totals['variance_pct'] !== null)
            <p class="text-xs mt-1.5 text-gray-500">{{ $totals['variance_pct'] >= 0 ? '+' : '' }}{{ number_format($totals['variance_pct'] * 100, 1) }}% vs. budget</p>
        @endif
    </div>
</div>

{{-- ── Over-budget callout (expense direction) ─────────────────────── --}}
@if ($direction !== 'income' && ! empty($data['over_budget']))
<div class="mb-5 bg-red-50 border border-red-200 rounded-2xl px-5 py-4">
    <h3 class="text-sm font-semibold text-red-800 mb-2">Top expense overruns</h3>
    <ul class="space-y-1 text-sm">
        @foreach ($data['over_budget'] as $o)
            <li class="flex items-center justify-between text-red-700">
                <span>{{ $o['name'] }}</span>
                <span class="tabular-nums font-semibold">+{{ \App\Services\FinanceReportService::usd($o['variance']) }}</span>
            </li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Insights ────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-5 mb-5">
    <h2 class="text-sm font-semibold text-gray-900 mb-3">Insights</h2>
    <ul class="space-y-1.5 text-sm text-gray-700 list-disc list-inside">
        @foreach ($data['insights'] as $bullet)
            <li>{{ $bullet }}</li>
        @endforeach
    </ul>
</div>

{{-- ── Detail table ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Per-Category Detail</h2>
        <a href="{{ route('finance.budgets.index') }}" class="text-xs text-brand-600 hover:underline">Manage budgets →</a>
    </div>

    @if (empty($data['rows']))
        <p class="px-5 py-12 text-center text-sm text-gray-400">
            No budgets seeded for this period and no actuals to show.
            <a href="{{ route('finance.budgets.create') }}" class="text-brand-600 hover:underline">Add the first budget</a>.
        </p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                    <th class="px-5 py-3">Category</th>
                    <th class="px-3 py-3">Type</th>
                    <th class="px-3 py-3 text-right">Budget</th>
                    <th class="px-3 py-3 text-right">Actual</th>
                    <th class="px-3 py-3 text-right">Variance</th>
                    <th class="px-3 py-3 text-right">% Var</th>
                    <th class="px-3 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($data['rows'] as $r)
                    @php
                        $rowVarClass = '';
                        if ($r['type'] === 'expense') {
                            $rowVarClass = $r['variance'] > 0 ? 'text-red-700' : ($r['variance'] < 0 ? 'text-green-700' : 'text-gray-700');
                        } else {
                            $rowVarClass = $r['variance'] >= 0 ? 'text-green-700' : 'text-red-700';
                        }
                        $statusClass = match ($r['status']) {
                            'over'      => 'bg-red-100 text-red-700',
                            'under'     => 'bg-green-100 text-green-700',
                            'on_target' => 'bg-gray-100 text-gray-600',
                            default     => 'bg-gray-100 text-gray-500',
                        };
                    @endphp
                    <tr>
                        <td class="px-5 py-2.5 font-medium text-gray-900">{{ $r['category_name'] }}</td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $r['type'] === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ ucfirst($r['type']) }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ \App\Services\FinanceReportService::usd($r['budget']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ \App\Services\FinanceReportService::usd($r['actual']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums {{ $rowVarClass }}">
                            {{ $r['variance'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($r['variance']) }}
                        </td>
                        <td class="px-3 py-2.5 text-right text-xs text-gray-500 tabular-nums">
                            {{ $r['variance_pct'] !== null ? ($r['variance_pct'] >= 0 ? '+' : '') . number_format($r['variance_pct'] * 100, 1) . '%' : '—' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">
                                {{ str_replace('_', ' ', ucfirst($r['status'])) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold">
                    <td class="px-5 py-3 text-gray-900">TOTAL</td>
                    <td></td>
                    <td class="px-3 py-3 text-right tabular-nums">{{ \App\Services\FinanceReportService::usd($totals['budget']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums">{{ \App\Services\FinanceReportService::usd($totals['actual']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums {{ $vClass }}">
                        {{ $totals['variance'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($totals['variance']) }}
                    </td>
                    <td class="px-3 py-3 text-right text-xs text-gray-500 tabular-nums">
                        {{ $totals['variance_pct'] !== null ? ($totals['variance_pct'] >= 0 ? '+' : '') . number_format($totals['variance_pct'] * 100, 1) . '%' : '—' }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>

@endsection
