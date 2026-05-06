@extends('layouts.app')
@section('title', 'Statement of Functional Expenses')

@section('content')

@php
    $reportTitle  = 'Statement of Functional Expenses';
    $exportRoutes = [
        'print' => 'finance.reports.functional-expenses.print',
        'pdf'   => 'finance.reports.functional-expenses.pdf',
        'csv'   => 'finance.reports.functional-expenses.csv',
    ];

    $hasCompare = ! empty($period['compare']);

    // Donut segments — one per function. Skip zero-amount slices so SvgChart::donut
    // doesn't render empty wedges.
    $segments = [];
    foreach ($data['by_function'] as $f) {
        if ($f['total'] > 0) {
            $segments[] = ['label' => $f['label'], 'value' => $f['total'], 'color' => $f['color']];
        }
    }

    // Program-ratio band colour for the headline KPI
    $r = $data['program_ratio'];
    if ($r >= 0.75)      { $ratioClass = 'text-green-700';  $ratioBand = 'meets the 75%+ benchmark'; }
    elseif ($r >= 0.65)  { $ratioClass = 'text-amber-700';  $ratioBand = 'within the 65–75% yellow band'; }
    else                 { $ratioClass = 'text-red-700';    $ratioBand = 'below the 65% benchmark'; }
@endphp

@include('finance.reports._shell', compact('reportTitle', 'period', 'exportRoutes'))

{{-- ── KPI strip — total + program ratio + 3 function totals ─────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Total Expenses</p>
        <p class="text-2xl font-bold text-red-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['total']) }}</p>
        @if ($hasCompare && $data['prior_total'] !== null && $data['prior_total'] > 0)
            @php $delta = ($data['total'] - $data['prior_total']) / $data['prior_total']; @endphp
            <p class="text-xs mt-1.5 {{ $delta <= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 1) }}% vs. {{ $period['compare']['label'] }}
            </p>
        @endif
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Program Ratio</p>
        <p class="text-2xl font-bold tabular-nums {{ $ratioClass }}">{{ number_format($r * 100, 1) }}%</p>
        <p class="text-xs mt-1.5 text-gray-500">{{ $ratioBand }}</p>
    </div>
    @foreach (['program', 'management_general', 'fundraising'] as $key)
        @continue($key === 'program') {{-- Already implied by program ratio; show the other two for balance --}}
    @endforeach
    @php
        $mg = $data['by_function']['management_general'];
        $fr = $data['by_function']['fundraising'];
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Mgmt &amp; General</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ \App\Services\FinanceReportService::usd($mg['total']) }}</p>
        <p class="text-xs mt-1.5 text-gray-500">{{ number_format($mg['share'] * 100, 1) }}% of expenses</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Fundraising</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ \App\Services\FinanceReportService::usd($fr['total']) }}</p>
        <p class="text-xs mt-1.5 text-gray-500">{{ number_format($fr['share'] * 100, 1) }}% of expenses</p>
    </div>
</div>

{{-- ── Donut + Insights ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Functional Allocation</h2>
        @if (! empty($segments))
            <div class="flex justify-center">
                {!! \App\Support\SvgChart::donut($segments, ['width' => 220, 'height' => 220]) !!}
            </div>
            <ul class="mt-4 space-y-2">
                @foreach ($data['by_function'] as $f)
                    @if ($f['total'] > 0)
                    <li class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-sm" style="background: {{ $f['color'] }};"></span>
                            <span class="text-gray-700">{{ $f['label'] }}</span>
                        </span>
                        <span class="text-gray-900 tabular-nums">
                            {{ \App\Services\FinanceReportService::usd($f['total']) }}
                            <span class="text-gray-400 ml-1">{{ number_format($f['share'] * 100, 1) }}%</span>
                        </span>
                    </li>
                    @endif
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-400 text-center py-12">No expenses recorded for this period.</p>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Insights</h2>
        <ul class="space-y-2 text-sm text-gray-700 list-disc list-inside">
            @foreach ($data['insights'] as $bullet)
                <li>{{ $bullet }}</li>
            @endforeach
        </ul>
    </div>
</div>

{{-- ── Detail table — function header → categories → subtotal ───────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900">Expense Detail by Function</h2>
        <p class="text-xs text-gray-400 mt-0.5">Categories grouped by IRS-990 functional bucket. Subtotals per function plus grand total.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                    <th class="px-5 py-3">Category</th>
                    <th class="px-3 py-3 text-right">Amount</th>
                    <th class="px-3 py-3 text-right">% of Function</th>
                    <th class="px-3 py-3 text-right">% of Total</th>
                    @if ($hasCompare)
                        <th class="px-3 py-3 text-right">Δ vs Prior</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($data['by_function'] as $f)
                    {{-- Function header row --}}
                    <tr class="bg-gray-50">
                        <td colspan="{{ $hasCompare ? 5 : 4 }}" class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider"
                            style="color: {{ $f['color'] }};">
                            <span class="inline-block w-2 h-2 rounded-sm mr-2 align-middle" style="background: {{ $f['color'] }};"></span>
                            {{ $f['label'] }}
                        </td>
                    </tr>

                    @if (empty($f['categories']))
                        <tr>
                            <td colspan="{{ $hasCompare ? 5 : 4 }}" class="px-5 py-3 text-xs text-gray-400 italic">No categories under this function yet.</td>
                        </tr>
                    @else
                        @foreach ($f['categories'] as $c)
                            <tr>
                                <td class="px-5 py-2.5 pl-9 text-gray-700">{{ $c['name'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ \App\Services\FinanceReportService::usd($c['amount']) }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums">{{ number_format($c['share'] * 100, 1) }}%</td>
                                <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums">
                                    {{ $data['total'] > 0 ? number_format(($c['amount'] / $data['total']) * 100, 1) : '0.0' }}%
                                </td>
                                @if ($hasCompare)
                                    <td class="px-3 py-2.5 text-right text-gray-400">—</td>
                                @endif
                            </tr>
                        @endforeach
                    @endif

                    {{-- Function subtotal --}}
                    <tr class="border-t border-gray-200 font-semibold">
                        <td class="px-5 py-2.5 text-gray-700">{{ $f['label'] }} Subtotal</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ \App\Services\FinanceReportService::usd($f['total']) }}</td>
                        <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums">100.0%</td>
                        <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums">{{ number_format($f['share'] * 100, 1) }}%</td>
                        @if ($hasCompare)
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                @if (isset($f['delta']) && $f['delta'] !== null)
                                    <span class="{{ $f['delta'] <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $f['delta'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($f['delta']) * 100, 1) }}%
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach

                {{-- Grand total --}}
                <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold">
                    <td class="px-5 py-3 text-gray-900">Grand Total</td>
                    <td class="px-3 py-3 text-right tabular-nums text-red-700">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
                    <td class="px-3 py-3"></td>
                    <td class="px-3 py-3 text-right text-gray-500 tabular-nums">100.0%</td>
                    @if ($hasCompare)
                        <td class="px-3 py-3"></td>
                    @endif
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection
