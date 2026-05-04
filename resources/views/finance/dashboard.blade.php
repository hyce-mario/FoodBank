@extends('layouts.app')
@section('title', 'Finance — Dashboard')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Finance</span>
        </nav>
    </div>
    <a href="{{ route('finance.transactions.create') }}"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Add Transaction
    </a>
</div>

@include('finance._nav')

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">

    {{-- Net Balance --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 {{ $kpis['net_balance'] >= 0 ? 'bg-navy-700 text-white' : 'bg-red-600 text-white' }} col-span-2 sm:col-span-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Net Balance</p>
        <p class="text-2xl font-bold tabular-nums">${{ number_format(abs($kpis['net_balance']), 2) }}</p>
        <p class="text-xs text-white/60 mt-0.5">{{ $kpis['net_balance'] >= 0 ? 'surplus' : 'deficit' }}</p>
    </div>

    {{-- Total Income --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Total Income</p>
        <p class="text-2xl font-bold tabular-nums text-green-600">${{ number_format($kpis['total_income'], 2) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">all time</p>
    </div>

    {{-- Total Expenses --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Total Expenses</p>
        <p class="text-2xl font-bold tabular-nums text-red-600">${{ number_format($kpis['total_expenses'], 2) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">all time</p>
    </div>

    {{-- This Month Income --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Month Income</p>
        <p class="text-2xl font-bold tabular-nums text-green-600">${{ number_format($kpis['month_income'], 2) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ now()->format('F Y') }}</p>
    </div>

    {{-- This Month Expenses --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Month Expenses</p>
        <p class="text-2xl font-bold tabular-nums text-red-600">${{ number_format($kpis['month_expenses'], 2) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ now()->format('F Y') }}</p>
    </div>

    {{-- Top Expense Category --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Top Expense</p>
        <p class="text-lg font-bold truncate">{{ $kpis['top_expense_category'] }}</p>
        <p class="text-xs text-gray-400 mt-0.5">by category</p>
    </div>

    {{-- Event-Linked Spend --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Event Spend</p>
        <p class="text-2xl font-bold tabular-nums">${{ number_format($kpis['event_linked_spend'], 2) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">event-linked expenses</p>
    </div>

</div>

{{-- ── Monthly Trend Chart ──────────────────────────────────────────────── --}}
@if(count($trend['labels']) > 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
    <h3 class="text-sm font-bold text-gray-800 mb-1">Monthly Income vs Expenses</h3>
    <p class="text-xs text-gray-400 mb-4">Last 12 months</p>
    <div class="h-64">
        <canvas id="financeTrendChart"></canvas>
    </div>
</div>
@endif

{{-- ── Recent Transactions ──────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900">Recent Transactions</h2>
        <a href="{{ route('finance.transactions.index') }}"
           class="text-xs font-medium text-brand-600 hover:text-brand-700">View all</a>
    </div>

    @if($recent->isEmpty())
    <div class="py-14 text-center text-gray-400 text-sm">
        No transactions recorded yet.
        <a href="{{ route('finance.transactions.create') }}" class="text-brand-600 hover:underline ml-1">Add one now.</a>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Title</th>
                    <th class="px-3 py-3">Type</th>
                    <th class="px-3 py-3">Category</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-3 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($recent as $tx)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ $tx->transaction_date->format('M j, Y') }}</td>
                    <td class="px-5 py-3">
                        <a href="{{ route('finance.transactions.show', $tx) }}"
                           class="font-medium text-gray-900 hover:text-brand-600 transition-colors">
                            {{ $tx->title }}
                        </a>
                        @if($tx->event)
                        <span class="ml-1.5 text-xs text-gray-400">— {{ $tx->event->name }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $tx->isIncome() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ ucfirst($tx->transaction_type) }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-gray-500">{{ $tx->category?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums
                        {{ $tx->isIncome() ? 'text-green-600' : 'text-red-600' }}">
                        {{ $tx->isIncome() ? '+' : '-' }}{{ $tx->formattedAmount() }}
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tx->statusBadgeClasses() }}">
                            {{ ucfirst($tx->status ?? 'completed') }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection

@push('scripts')
@if(count($trend['labels']) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const TREND   = @json($trend);
    const green   = '#16a34a';
    const red     = '#dc2626';
    const grid    = '#F3F4F6';
    const text    = '#6B7280';

    const baseScales = {
        x: { grid: { color: grid }, ticks: { color: text, font: { size: 11 } } },
        y: { grid: { color: grid }, ticks: { color: text, font: { size: 11 }, callback: v => '$' + v.toLocaleString() }, beginAtZero: true },
    };

    const el = document.getElementById('financeTrendChart');
    if (el) {
        new Chart(el, {
            type: 'bar',
            data: {
                labels: TREND.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: TREND.income,
                        backgroundColor: green + '99',
                        borderColor: green,
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Expenses',
                        data: TREND.expense,
                        backgroundColor: red + '99',
                        borderColor: red,
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: text } },
                    tooltip: {
                        backgroundColor: '#1F2937', padding: 8, cornerRadius: 8,
                        titleFont: { size: 12 }, bodyFont: { size: 12 },
                        callbacks: { label: ctx => ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2}) },
                    },
                },
                scales: baseScales,
            },
        });
    }
});
</script>
@endif
@endpush
