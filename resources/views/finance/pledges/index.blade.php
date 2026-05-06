@extends('layouts.app')
@section('title', 'Finance — Pledges')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.dashboard') }}" class="hover:text-brand-500">Finance</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Pledges</span>
        </nav>
    </div>
    <a href="{{ route('finance.pledges.create') }}"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-lg px-4 py-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Add Pledge
    </a>
</div>

@include('finance._nav')

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<form method="GET" class="flex flex-wrap items-center gap-2 mb-4 bg-white border border-gray-200 rounded-xl px-4 py-2.5">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search donor / source"
           class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        <option value="">All statuses</option>
        @foreach (\App\Models\Pledge::STATUS_LABELS as $val => $lbl)
            <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-navy-700 hover:bg-navy-600 text-white text-sm font-semibold rounded-lg px-4 py-1.5">Apply</button>
    @if (request()->hasAny(['search', 'status']))
        <a href="{{ route('finance.pledges.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    @if ($pledges->isEmpty())
    <div class="py-14 text-center text-gray-400 text-sm">
        No pledges yet. <a href="{{ route('finance.pledges.create') }}" class="text-brand-600 hover:underline">Add the first pledge</a> to start tracking AR aging.
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                    <th class="px-5 py-3">Donor / Source</th>
                    <th class="px-3 py-3 text-right">Amount</th>
                    <th class="px-3 py-3">Pledged</th>
                    <th class="px-3 py-3">Expected</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Category</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($pledges as $p)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-900">
                        {{ $p->source_or_payee }}
                        @if ($p->household)
                            <div class="text-xs text-gray-400">Household #{{ $p->household->id }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-right tabular-nums font-semibold">${{ number_format((float)$p->amount, 2) }}</td>
                    <td class="px-3 py-3 text-gray-600">{{ $p->pledged_at->format('M j, Y') }}</td>
                    <td class="px-3 py-3 text-gray-600">{{ $p->expected_at->format('M j, Y') }}</td>
                    <td class="px-3 py-3">
                        @php
                            $statusCls = match ($p->status) {
                                'open'        => 'bg-blue-100 text-blue-700',
                                'partial'     => 'bg-amber-100 text-amber-700',
                                'fulfilled'   => 'bg-green-100 text-green-700',
                                'written_off' => 'bg-gray-100 text-gray-500',
                                default       => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusCls }}">
                            {{ $p->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-xs text-gray-500">{{ $p->category?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('finance.pledges.edit', $p) }}"
                               class="text-xs font-medium text-gray-600 hover:text-navy-700 border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50">Edit</a>
                            <form method="POST" action="{{ route('finance.pledges.destroy', $p) }}"
                                  onsubmit="return confirm('Delete this pledge?');">
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
    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">{{ $pledges->links() }}</div>
    @endif
</div>

@endsection
