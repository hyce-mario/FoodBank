@extends('layouts.app')
@section('title', 'Finance — Transactions')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.dashboard') }}" class="hover:text-brand-500">Finance</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Transactions</span>
        </nav>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('finance.transactions.create', ['type' => 'income']) }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 border border-green-300 bg-green-50 hover:bg-green-100 rounded-lg px-4 py-2 transition-colors">
            + Income
        </a>
        <a href="{{ route('finance.transactions.create', ['type' => 'expense']) }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-red-700 border border-red-300 bg-red-50 hover:bg-red-100 rounded-lg px-4 py-2 transition-colors">
            + Expense
        </a>
    </div>
</div>

@include('finance._nav')

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- ── Filter Totals ────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Income (filtered)</p>
        <p class="text-xl font-bold text-green-600 tabular-nums">${{ number_format($incomeTotals, 2) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Expenses (filtered)</p>
        <p class="text-xl font-bold text-red-600 tabular-nums">${{ number_format($expenseTotals, 2) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Net (filtered)</p>
        @php $net = $incomeTotals - $expenseTotals; @endphp
        <p class="text-xl font-bold tabular-nums {{ $net >= 0 ? 'text-gray-900' : 'text-red-600' }}">${{ number_format(abs($net), 2) }}</p>
    </div>
</div>

{{-- ── Main Card ────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('finance.transactions.index') }}"
          class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">

        {{-- Search --}}
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search transactions..."
                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
        </div>

        {{-- Type --}}
        <select name="type" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            <option value="">All Types</option>
            <option value="income"  {{ request('type') === 'income'  ? 'selected' : '' }}>Income</option>
            <option value="expense" {{ request('type') === 'expense' ? 'selected' : '' }}>Expense</option>
        </select>

        {{-- Category --}}
        <select name="category_id" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                {{ $cat->name }} ({{ ucfirst($cat->type) }})
            </option>
            @endforeach
        </select>

        {{-- Status --}}
        <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            <option value="">All Statuses</option>
            @foreach(\App\Models\FinanceTransaction::STATUSES as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>

        {{-- Date Range --}}
        <input type="date" name="date_from" value="{{ request('date_from') }}"
               class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
        <input type="date" name="date_to" value="{{ request('date_to') }}"
               class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20">

        <button type="submit"
                class="px-4 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
            Filter
        </button>
        @if(request()->anyFilled(['search','type','category_id','status','date_from','date_to']))
        <a href="{{ route('finance.transactions.index') }}"
           class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            Clear
        </a>
        @endif
    </form>

    {{-- Table --}}
    @if($transactions->isEmpty())
    <div class="py-14 text-center text-gray-400 text-sm">
        No transactions found.
        @if(!request()->anyFilled(['search','type','category_id','status','date_from','date_to']))
        <a href="{{ route('finance.transactions.create') }}" class="text-brand-600 hover:underline ml-1">Add the first one.</a>
        @endif
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
                    <th class="px-5 py-3">Source / Payee</th>
                    <th class="px-3 py-3">Event</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($transactions as $tx)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ $tx->transaction_date->format('M j, Y') }}</td>
                    <td class="px-5 py-3">
                        <a href="{{ route('finance.transactions.show', $tx) }}"
                           class="font-medium text-gray-900 hover:text-brand-600 transition-colors">
                            {{ $tx->title }}
                        </a>
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $tx->isIncome() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ ucfirst($tx->transaction_type) }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-gray-500 whitespace-nowrap">{{ $tx->category?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-gray-500 max-w-[160px] truncate">{{ $tx->source_or_payee }}</td>
                    <td class="px-3 py-3 text-gray-400 text-xs">
                        @if($tx->event)
                        <a href="{{ route('events.show', $tx->event) }}" class="hover:text-brand-600 truncate max-w-[100px] block">
                            {{ $tx->event->name }}
                        </a>
                        @else
                        —
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums whitespace-nowrap
                        {{ $tx->isIncome() ? 'text-green-600' : 'text-red-600' }}">
                        {{ $tx->isIncome() ? '+' : '-' }}{{ $tx->formattedAmount() }}
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tx->statusBadgeClasses() }}">
                            {{ ucfirst($tx->status ?? 'completed') }}
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <a href="{{ route('finance.transactions.edit', $tx) }}"
                           class="text-xs text-gray-500 hover:text-gray-800 font-medium">Edit</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($transactions->hasPages())
    <div class="px-5 py-4 border-t border-gray-100">
        {{ $transactions->links() }}
    </div>
    @endif
    @endif

</div>

@endsection
