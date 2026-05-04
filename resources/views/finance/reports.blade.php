@extends('layouts.app')
@section('title', 'Finance — Reports')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.dashboard') }}" class="hover:text-brand-500">Finance</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Reports</span>
        </nav>
    </div>
</div>

@include('finance._nav')

{{-- ── Row 1: Monthly Trend + Expense by Category ──────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

    {{-- Monthly Income vs Expenses --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Monthly Income vs Expenses</h3>
        <p class="text-xs text-gray-400 mb-4">Last 12 months (completed transactions)</p>
        <div class="h-60">
            <canvas id="monthlyTrendChart"></canvas>
        </div>
    </div>

    {{-- Expense by Category --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Expenses by Category</h3>
        <p class="text-xs text-gray-400 mb-4">All time, completed transactions</p>
        @if(count($expenseByCategory['labels']) > 0)
        <div class="h-60">
            <canvas id="expenseByCategoryChart"></canvas>
        </div>
        @else
        <div class="h-60 flex items-center justify-center text-gray-400 text-sm">No expense data yet.</div>
        @endif
    </div>

</div>

{{-- ── Row 2: Income by Source + Event Finance Summary ─────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- Income by Source --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Top Income Sources</h3>
            <p class="text-xs text-gray-400 mt-0.5">All time, top 10</p>
        </div>
        @if($incomeBySource->isEmpty())
        <div class="py-10 text-center text-gray-400 text-sm">No income recorded yet.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-3">Source</th>
                        <th class="px-3 py-3 text-right">Count</th>
                        <th class="px-5 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($incomeBySource as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-800 font-medium">{{ $row->source_or_payee }}</td>
                        <td class="px-3 py-3 text-center text-gray-400">{{ $row->count }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-green-600 tabular-nums">${{ number_format($row->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Event Finance Summary --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Event Finance Summary</h3>
            <p class="text-xs text-gray-400 mt-0.5">Top 10 events by spend</p>
        </div>
        @if($eventSummary->isEmpty())
        <div class="py-10 text-center text-gray-400 text-sm">No event-linked transactions yet.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-3">Event</th>
                        <th class="px-3 py-3 text-right">Income</th>
                        <th class="px-3 py-3 text-right">Expense</th>
                        <th class="px-3 py-3 text-right">Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($eventSummary as $row)
                    @php $net = $row->total_income - $row->total_expense; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            @if($row->event)
                            <a href="{{ route('events.show', $row->event) }}" class="font-medium text-gray-900 hover:text-brand-600">
                                {{ $row->event->name }}
                            </a>
                            <span class="block text-xs text-gray-400">{{ $row->event->date?->format('M j, Y') }}</span>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right text-green-600 tabular-nums">${{ number_format($row->total_income, 2) }}</td>
                        <td class="px-3 py-3 text-right text-red-600 tabular-nums">${{ number_format($row->total_expense, 2) }}</td>
                        <td class="px-3 py-3 text-right font-semibold tabular-nums {{ $net >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                            ${{ number_format(abs($net), 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const green  = '#16a34a';
    const red    = '#dc2626';
    const grid   = '#F3F4F6';
    const text   = '#6B7280';

    const baseTooltip = {
        backgroundColor: '#1F2937', padding: 8, cornerRadius: 8,
        titleFont: { size: 12 }, bodyFont: { size: 12 },
    };

    // Monthly Trend
    const tEl = document.getElementById('monthlyTrendChart');
    if (tEl) {
        const TREND = @json($monthlyTrend);
        new Chart(tEl, {
            type: 'bar',
            data: {
                labels: TREND.labels,
                datasets: [
                    { label: 'Income',   data: TREND.income,  backgroundColor: green + '99', borderColor: green, borderWidth: 1, borderRadius: 4, borderSkipped: false },
                    { label: 'Expenses', data: TREND.expense, backgroundColor: red   + '99', borderColor: red,   borderWidth: 1, borderRadius: 4, borderSkipped: false },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: text } },
                    tooltip: { ...baseTooltip, callbacks: { label: ctx => ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2}) } },
                },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: text, font: { size: 10 } } },
                    y: { grid: { color: grid }, ticks: { color: text, font: { size: 11 }, callback: v => '$' + v.toLocaleString() }, beginAtZero: true },
                },
            },
        });
    }

    // Expense by Category
    const cEl = document.getElementById('expenseByCategoryChart');
    if (cEl) {
        const CAT = @json($expenseByCategory);
        const palette = ['#dc2626','#ea580c','#ca8a04','#16a34a','#0891b2','#7c3aed','#db2777','#64748b','#374151','#9ca3af'];
        new Chart(cEl, {
            type: 'doughnut',
            data: {
                labels: CAT.labels,
                datasets: [{
                    data: CAT.totals,
                    backgroundColor: CAT.labels.map((_, i) => palette[i % palette.length]),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { font: { size: 11 }, color: text, boxWidth: 12 } },
                    tooltip: { ...baseTooltip, callbacks: { label: ctx => ctx.label + ': $' + ctx.parsed.toLocaleString(undefined, {minimumFractionDigits:2}) } },
                },
            },
        });
    }
});
</script>
@endpush
