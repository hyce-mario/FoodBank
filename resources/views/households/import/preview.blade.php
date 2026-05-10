@extends('layouts.app')
@section('title', 'Preview Import')

@section('content')
<div>

{{-- Header --}}
<div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Preview Import</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('households.index') }}" class="hover:text-brand-500 transition-colors">Households</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('households.import.create') }}" class="hover:text-brand-500 transition-colors">Import</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">Preview</span>
        </nav>
        <p class="text-xs text-gray-500 mt-1">
            File: <strong class="text-gray-700">{{ $filename }}</strong>
            · {{ count($rows) }} {{ count($rows) === 1 ? 'row' : 'rows' }}
        </p>
    </div>
</div>

{{-- Flash error --}}
@if (session('error'))
<div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    {{ session('error') }}
</div>
@endif

{{-- Stat strip --}}
@php
    $newCount   = collect($rows)->where('status', 'new')->count();
    $exactCount = collect($rows)->where('status', 'exact_match')->count();
    $fuzzyCount = collect($rows)->where('status', 'fuzzy_match')->count();
@endphp
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Total Rows</p>
        <p class="text-3xl font-bold text-gray-900">{{ count($rows) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">New</p>
        <p class="text-3xl font-bold text-emerald-600">{{ $newCount }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Exact Match</p>
        <p class="text-3xl font-bold text-amber-600">{{ $exactCount }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Fuzzy Match</p>
        <p class="text-3xl font-bold text-orange-500">{{ $fuzzyCount }}</p>
    </div>
</div>

<form method="POST" action="{{ route('households.import.commit') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    {{-- Decision table --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between gap-3 flex-wrap">
            <h2 class="text-sm font-semibold text-gray-800">Per-Row Decisions</h2>
            <p class="text-xs text-gray-500">
                Defaults: <span class="text-emerald-600 font-semibold">new</span> rows → Create ·
                <span class="text-amber-600 font-semibold">match</span> rows → Skip
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Row</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Phone</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Size</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">Matches</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide" style="min-width: 14rem;">Decision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        @php
                            $rn      = $row['row_number'];
                            $d       = $row['data'];
                            $status  = $row['status'];
                            $matches = $row['matches'];
                            $size    = max(1, (int) $d['children_count'] + (int) $d['adults_count'] + (int) $d['seniors_count']);
                            $defaultAction = $status === 'new' ? 'create' : 'skip';
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-3 py-2.5 text-gray-500 tabular-nums font-medium">{{ $rn }}</td>
                            <td class="px-3 py-2.5">
                                @if ($status === 'new')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">New</span>
                                @elseif ($status === 'exact_match')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Exact</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">Fuzzy</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 font-semibold text-gray-900">
                                {{ $d['first_name'] }} {{ $d['last_name'] }}
                            </td>
                            <td class="px-3 py-2.5 text-gray-600 break-all">{{ $d['email'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-gray-600">{{ $d['phone'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-gray-700 tabular-nums">{{ $size }}</td>
                            <td class="px-3 py-2.5">
                                @if (empty($matches))
                                    <span class="text-xs text-gray-400">—</span>
                                @else
                                    <ul class="space-y-0.5">
                                        @foreach (array_slice($matches, 0, 3) as $m)
                                            <li class="text-xs">
                                                <a href="{{ route('households.show', $m['id']) }}" target="_blank" rel="noopener"
                                                   class="text-brand-600 hover:text-brand-700 hover:underline">
                                                    #{{ $m['household_number'] }} {{ $m['full_name'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                        @if (count($matches) > 3)
                                            <li class="text-xs text-gray-500">+ {{ count($matches) - 3 }} more</li>
                                        @endif
                                    </ul>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div x-data="{ action: '{{ $defaultAction }}' }">
                                    <select name="decisions[{{ $rn }}][action]" x-model="action"
                                            class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-lg bg-white
                                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                                        @if ($status === 'new')
                                            <option value="create">Create as new</option>
                                            <option value="skip">Skip</option>
                                        @else
                                            <option value="skip">Skip</option>
                                            <option value="create_anyway">Create anyway</option>
                                            <option value="update">Update existing…</option>
                                        @endif
                                    </select>
                                    @if (! empty($matches))
                                        <select name="decisions[{{ $rn }}][update_target_id]"
                                                x-show="action === 'update'" x-cloak
                                                class="mt-1 w-full px-2 py-1.5 text-xs border border-gray-300 rounded-lg bg-white
                                                       focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                                            @foreach ($matches as $m)
                                                <option value="{{ $m['id'] }}">
                                                    #{{ $m['household_number'] }} — {{ $m['full_name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <p class="text-xs text-gray-500 leading-relaxed max-w-2xl">
            <strong class="text-gray-700">Confirm Import</strong> applies all decisions inside one database transaction —
            if anything fails mid-batch, the entire import rolls back. Empty cells overwrite to NULL on Update rows.
        </p>
        <div class="flex items-center gap-2">
            <a href="{{ route('households.import.create') }}"
               class="px-4 py-2 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="px-4 py-2 text-sm font-semibold bg-green-600 hover:bg-green-700 text-white rounded-xl transition-colors">
                Confirm Import
            </button>
        </div>
    </div>
</form>

</div>
@endsection
