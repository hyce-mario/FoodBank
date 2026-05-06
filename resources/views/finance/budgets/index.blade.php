@extends('layouts.app')
@section('title', 'Finance — Budgets')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.dashboard') }}" class="hover:text-brand-500">Finance</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Budgets</span>
        </nav>
    </div>
    <a href="{{ route('finance.budgets.create') }}"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add Budget
    </a>
</div>

@include('finance._nav')

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Filter toolbar --}}
<form method="GET" class="flex flex-wrap items-center gap-2 mb-4 bg-white border border-gray-200 rounded-xl px-4 py-2.5">
    <select name="category_id" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        <option value="">All categories</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                {{ $cat->name }} ({{ $cat->type }})
            </option>
        @endforeach
    </select>
    <select name="scope" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        <option value="">All scopes</option>
        <option value="org"   {{ request('scope') === 'org'   ? 'selected' : '' }}>Org-wide</option>
        <option value="event" {{ request('scope') === 'event' ? 'selected' : '' }}>Per-event</option>
    </select>
    <button type="submit" class="bg-navy-700 hover:bg-navy-600 text-white text-sm font-semibold rounded-lg px-4 py-1.5">Apply</button>
    @if(request('category_id') || request('scope'))
        <a href="{{ route('finance.budgets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
    @endif
</form>

{{-- Budgets table --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    @if($budgets->isEmpty())
    <div class="py-14 text-center text-gray-400 text-sm">
        No budgets yet. <a href="{{ route('finance.budgets.create') }}" class="text-brand-600 hover:underline">Add the first budget</a> to start tracking variance.
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                    <th class="px-5 py-3">Category</th>
                    <th class="px-3 py-3">Type</th>
                    <th class="px-3 py-3">Period</th>
                    <th class="px-3 py-3">Scope</th>
                    <th class="px-3 py-3 text-right">Amount</th>
                    <th class="px-5 py-3">Notes</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($budgets as $b)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $b->category?->name ?? '—' }}</td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $b->category?->typeBadgeClasses() ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $b->category?->typeLabel() ?? '—' }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-gray-600">{{ $b->period_start->format('M Y') }}</td>
                    <td class="px-3 py-3 text-xs">
                        @if($b->event)
                            <span class="text-navy-700">{{ $b->event->name }}</span>
                        @else
                            <span class="text-gray-500">Org-wide</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-right tabular-nums font-semibold">${{ number_format((float)$b->amount, 2) }}</td>
                    <td class="px-5 py-3 text-gray-400 max-w-xs truncate">{{ $b->notes ?? '—' }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('finance.budgets.edit', $b) }}"
                               class="text-xs font-medium text-gray-600 hover:text-navy-700 border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50">Edit</a>
                            <form method="POST" action="{{ route('finance.budgets.destroy', $b) }}"
                                  onsubmit="return confirm('Delete this budget row? This cannot be undone.');">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="text-xs font-medium text-red-600 hover:text-red-700 border border-red-200 rounded-lg px-3 py-1.5 hover:bg-red-50">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
        {{ $budgets->links() }}
    </div>
    @endif
</div>

@endsection
