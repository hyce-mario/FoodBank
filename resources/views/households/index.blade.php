@extends('layouts.app')
@section('title', 'Households')

@section('content')
<div x-data="householdsPage()" x-init="init()">

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Households Directory</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Households</span>
        </nav>
    </div>
    <a href="{{ route('households.create') }}"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
              font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Add Household
    </a>
</div>

{{-- Flash --}}
@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- ── Main Card ────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Toolbar --}}
    <form method="GET" action="{{ route('households.index') }}"
          class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">

        {{-- Search (also matches zip code) --}}
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search by name, household #, phone, email, or zip..."
                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                          placeholder:text-gray-400">
        </div>

        {{-- Filter Attendance --}}
        <select name="attendance"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50
                       focus:outline-none focus:ring-2 focus:ring-brand-500/20
                       text-gray-600 cursor-pointer min-w-[150px]">
            <option value="">All Households</option>
            <option value="first_timer"  @selected(request('attendance') === 'first_timer')>First-Timers Only</option>
            <option value="returning"    @selected(request('attendance') === 'returning')>Returning Only</option>
        </select>

        {{-- Filter / Apply --}}
        <button type="submit"
                class="inline-flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white
                       text-sm font-semibold rounded-lg px-4 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
            </svg>
            Filter
        </button>

        @if (request()->hasAny(['search','attendance','sort']))
            <a href="{{ route('households.index') }}"
               class="text-xs text-gray-500 hover:text-gray-700 px-2 py-2 hover:bg-gray-100 rounded-lg transition-colors">
                Clear
            </a>
        @endif

        <div class="ml-auto flex items-center gap-1.5">

            @php
                // Carry the current filters/sort onto the export URLs so the
                // exported set matches exactly what's on screen. Strip the
                // `page` and `per_page` keys since exports return all rows.
                $exportQuery = request()->except(['page', 'per_page']);
            @endphp

            {{-- PDF --}}
            <a href="{{ route('households.export.pdf', $exportQuery) }}"
               class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-500 hover:bg-red-600 transition-colors"
               title="Export PDF (all matching rows)">
                <span class="text-[10px] font-bold text-white">PDF</span>
            </a>
            {{-- XLS --}}
            <a href="{{ route('households.export.xlsx', $exportQuery) }}"
               class="w-8 h-8 flex items-center justify-center rounded-lg bg-green-600 hover:bg-green-700 transition-colors"
               title="Export Excel (all matching rows)">
                <span class="text-[10px] font-bold text-white">XLS</span>
            </a>
            {{-- Print --}}
            <a href="{{ route('households.export.print', $exportQuery) }}" target="_blank" rel="noopener"
               class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors text-gray-500"
               title="Print (all matching rows)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                </svg>
            </a>
        </div>
    </form>

    {{-- ── DESKTOP TABLE ─────────────────────────────────────────────────── --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide w-28">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'household_number', 'direction' => request('sort') === 'household_number' && request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                           class="flex items-center gap-1 hover:text-navy-700">
                            ID
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/>
                            </svg>
                        </a>
                    </th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Household</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Location</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Zipcode</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Size</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">First Attended</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Events</th>
                    <th class="px-5 py-3 w-36"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($households as $h)
                    <tr class="hover:bg-gray-50/70 transition-colors group">
                        <td class="px-5 py-3.5 text-sm text-gray-500">{{ $h->household_number }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('households.show', $h) }}"
                                   class="font-semibold text-gray-900 hover:text-navy-700 hover:underline underline-offset-2 transition-colors">
                                    {{ $h->full_name }}
                                </a>
                                @if ((int) $h->events_attended_count === 1)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 ring-1 ring-green-200">
                                        First-Timer
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-500">{{ $h->email ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-sm text-gray-500">{{ $h->location ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-sm text-gray-500">{{ $h->zip ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-1.5 text-sm text-gray-700">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                                </svg>
                                {{ $h->household_size }}
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-500">
                            {{ $h->first_event_date ? \Carbon\Carbon::parse($h->first_event_date)->format('M j, Y') : '—' }}
                        </td>
                        <td class="px-5 py-3.5">
                            @if ((int) $h->events_attended_count > 0)
                                <span class="inline-flex items-center gap-1 text-sm text-gray-700">
                                    {{ $h->events_attended_count }}
                                    <span class="text-gray-400 text-xs">event{{ $h->events_attended_count == 1 ? '' : 's' }}</span>
                                </span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1.5">
                                {{-- View --}}
                                <a href="{{ route('households.show', $h) }}"
                                   class="p-1.5 rounded-md text-gray-400 hover:text-navy-700 hover:bg-gray-100 transition-colors"
                                   title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                </a>
                                {{-- QR --}}
                                <button type="button" title="QR Code"
                                        @click="openQr({ number: '{{ $h->household_number }}', name: '{{ addslashes($h->full_name) }}', size: {{ $h->household_size }}, children: {{ (int) $h->children_count }}, adults: {{ (int) $h->adults_count }}, seniors: {{ (int) $h->seniors_count }}, token: '{{ $h->qr_token }}', regenerateUrl: '{{ route('households.regenerate-qr', $h) }}' })"
                                        class="p-1.5 rounded-md text-gray-400 hover:text-navy-700 hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/>
                                        <path d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375a3 3 0 0 0 3-3V13.5M13.5 16.875v3M13.5 16.875H12m4.125-5.625H13.5m0-3.375v3.375m0-3.375h3.375"/>
                                    </svg>
                                </button>
                                {{-- Edit --}}
                                <a href="{{ route('households.edit', $h) }}"
                                   class="p-1.5 rounded-md text-gray-400 hover:text-navy-700 hover:bg-gray-100 transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                    </svg>
                                </a>
                                {{-- Delete --}}
                                <button type="button" title="Delete"
                                        @click="openDelete({ name: '{{ addslashes($h->full_name) }}', url: '{{ route('households.destroy', $h) }}' })"
                                        class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-14 text-center">
                            <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-500">No households found</p>
                            <a href="{{ route('households.create') }}" class="text-xs text-brand-500 hover:underline mt-1 inline-block">Add your first household</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── MOBILE CARDS ──────────────────────────────────────────────────── --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse ($households as $h)
            <div class="px-4 py-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-xs text-gray-400 font-mono">#{{ $h->household_number }}</span>
                            <span class="flex items-center gap-0.5 text-xs text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                                </svg>
                                {{ $h->household_size }}
                            </span>
                            @if ((int) $h->events_attended_count === 1)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                    First-Timer
                                </span>
                            @endif
                        </div>
                        <a href="{{ route('households.show', $h) }}"
                           class="block font-semibold text-gray-900 hover:text-navy-700 hover:underline underline-offset-2 transition-colors">
                            {{ $h->full_name }}
                        </a>
                        @if ($h->email)<p class="text-sm text-gray-500 truncate">{{ $h->email }}</p>@endif
                        @if ($h->location)<p class="text-xs text-gray-400 mt-0.5">{{ $h->location }}{{ $h->zip ? ', '.$h->zip : '' }}</p>@endif
                        @if ($h->first_event_date)
                            <p class="text-xs text-gray-400 mt-0.5">
                                First attended: {{ \Carbon\Carbon::parse($h->first_event_date)->format('M j, Y') }}
                                @if ((int) $h->events_attended_count > 1)
                                    &middot; {{ $h->events_attended_count }} events
                                @endif
                            </p>
                        @endif
                    </div>
                    <a href="{{ route('households.show', $h) }}"
                       class="flex-shrink-0 p-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-navy-700 hover:text-white hover:border-navy-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button"
                            @click="openQr({ number: '{{ $h->household_number }}', name: '{{ addslashes($h->full_name) }}', size: {{ $h->household_size }}, children: {{ (int) $h->children_count }}, adults: {{ (int) $h->adults_count }}, seniors: {{ (int) $h->seniors_count }}, token: '{{ $h->qr_token }}', regenerateUrl: '{{ route('households.regenerate-qr', $h) }}' })"
                            class="flex items-center justify-center gap-1 py-2 text-xs font-medium border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/></svg>
                        QR Code
                    </button>
                    <a href="{{ route('households.edit', $h) }}"
                       class="flex items-center justify-center gap-1 py-2 text-xs font-medium border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                        Edit
                    </a>
                    <button type="button"
                            @click="openDelete({ name: '{{ addslashes($h->full_name) }}', url: '{{ route('households.destroy', $h) }}' })"
                            class="flex items-center justify-center gap-1 py-2 text-xs font-medium border border-red-200 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        Delete
                    </button>
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center text-sm text-gray-400">
                No households found.
                <a href="{{ route('households.create') }}" class="text-brand-500 hover:underline ml-1">Add one</a>
            </div>
        @endforelse
    </div>

    {{-- ── Pagination Footer ─────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-3 border-t border-gray-100">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>Row Per Page</span>
            <select onchange="window.location.href=this.value"
                    class="text-sm border border-gray-200 rounded-md px-2 py-1 focus:outline-none">
                @foreach ([10, 25, 50, 100] as $pp)
                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => 1]) }}"
                            @selected(request('per_page', 10) == $pp)>{{ $pp }}</option>
                @endforeach
            </select>
            <span>Entries</span>
        </div>
        <div class="flex items-center gap-1 text-sm">
            {{-- Prev --}}
            @if ($households->onFirstPage())
                <span class="w-8 h-8 flex items-center justify-center text-gray-300 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
                </span>
            @else
                <a href="{{ $households->previousPageUrl() }}"
                   class="w-8 h-8 flex items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
                </a>
            @endif

            @foreach ($households->getUrlRange(max(1, $households->currentPage()-2), min($households->lastPage(), $households->currentPage()+2)) as $page => $url)
                <a href="{{ $url }}"
                   class="w-8 h-8 flex items-center justify-center rounded-md text-sm font-medium transition-colors
                          {{ $page == $households->currentPage() ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $page }}
                </a>
            @endforeach

            {{-- Next --}}
            @if ($households->hasMorePages())
                <a href="{{ $households->nextPageUrl() }}"
                   class="w-8 h-8 flex items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @else
                <span class="w-8 h-8 flex items-center justify-center text-gray-300 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </span>
            @endif
        </div>
    </div>
</div>

{{-- ═══════════════ QR MODAL ════════════════════════════════════════════ --}}
<div x-show="qrOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="qrOpen = false" style="display:none;">

    <div x-show="qrOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">

        <h2 class="text-lg font-bold text-gray-900">QR Code</h2>
        <p class="text-sm text-gray-400 mb-4">User QR code and ID</p>

        <p class="text-2xl font-bold text-gray-900 mb-4" x-text="'#' + (currentHousehold.number || '')"></p>

        <div class="flex justify-center mb-4">
            <div class="border-2 border-gray-100 rounded-xl p-3 bg-white inline-block">
                <canvas id="qrCanvas" width="160" height="160"></canvas>
            </div>
        </div>

        <p class="font-semibold text-gray-900" x-text="currentHousehold.name"></p>

        {{-- Family tag — same hover/tap-revealed demographic chip used on intake/scanner --}}
        <div class="flex justify-center mt-1.5" x-data="{ showDemo: false }">
            <span @mouseenter="showDemo = true" @mouseleave="showDemo = false"
                  @click.stop="showDemo = !showDemo"
                  class="relative inline-block cursor-help align-middle">
                <span class="text-sm font-semibold text-gray-700">1 Family</span>
                <span x-show="showDemo" style="display:none"
                      x-transition:enter="transition ease-out duration-150"
                      x-transition:enter-start="opacity-0 translate-y-1"
                      x-transition:enter-end="opacity-100 translate-y-0"
                      class="absolute left-1/2 -translate-x-1/2 top-full mt-1 z-30 min-w-[10rem] bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                    <span class="block text-sm font-semibold text-gray-900 mb-2">
                        <span x-text="currentHousehold.size || 0"></span>
                        <span x-text="(currentHousehold.size == 1) ? 'Member' : 'Members'"></span>
                    </span>
                    <span class="block text-xs text-gray-600 space-y-1">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span><span class="font-semibold text-gray-800" x-text="currentHousehold.children || 0"></span><span x-text="(currentHousehold.children == 1) ? 'Child' : 'Children'"></span></span>
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span><span class="font-semibold text-gray-800" x-text="currentHousehold.adults || 0"></span><span x-text="(currentHousehold.adults == 1) ? 'Adult' : 'Adults'"></span></span>
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span><span class="font-semibold text-gray-800" x-text="currentHousehold.seniors || 0"></span><span x-text="(currentHousehold.seniors == 1) ? 'Senior' : 'Seniors'"></span></span>
                    </span>
                </span>
            </span>
        </div>

        <div class="flex items-stretch gap-2 mt-6">
            <button type="button" @click="qrOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                Cancel
            </button>
            <button type="button" onclick="printQr()"
                    class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-sm font-semibold
                           bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659"/>
                </svg>
                Print QR
            </button>
            <form :action="currentHousehold.regenerateUrl" method="POST" class="flex-1">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center gap-1.5 py-2.5 text-sm font-semibold
                               bg-brand-500 hover:bg-brand-600 text-white rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Generate New QR
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════ DELETE MODAL ════════════════════════════════════════ --}}
<div x-show="deleteOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="deleteOpen = false" style="display:none;">

    <div x-show="deleteOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">

        <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </div>

        <h2 class="text-lg font-bold text-gray-900 mb-2">Delete Household</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Are you sure you want to delete this household?
            This action cannot be undone.
        </p>

        <div class="flex items-center gap-3">
            <button type="button" @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                Cancel
            </button>
            <form :action="deleteTarget.url" method="POST" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 py-2.5 text-sm font-semibold
                               bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
<script>
function householdsPage() {
    return {
        qrOpen: false,
        deleteOpen: false,
        currentHousehold: {},
        deleteTarget: {},
        init() {},
        openQr(h) {
            this.currentHousehold = h;
            this.qrOpen = true;
            this.$nextTick(() => {
                const c = document.getElementById('qrCanvas');
                if (c) new QRious({ element: c, value: h.token || h.number, size: 160, foreground: '#1b2b4b', background: '#ffffff', level: 'H' });
            });
        },
        openDelete(t) { this.deleteTarget = t; this.deleteOpen = true; },
    };
}
function printQr() {
    const c = document.getElementById('qrCanvas');
    const d = c.toDataURL('image/png');
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>QR Code</title><style>body{font-family:sans-serif;text-align:center;padding:40px}img{width:200px;height:200px;border:2px solid #e5e7eb;border-radius:12px;padding:8px}h2{color:#1b2b4b}</style></head><body><img src="${d}"/><script>window.onload=()=>{window.print();window.close()}<\/script></body></html>`);
    w.document.close();
}
</script>
@endpush
