@extends('layouts.app')
@section('title', $volunteer->full_name)

@section('content')
<div x-data="{ deleteOpen: false, mergeOpen: false, mergeKeeperId: '', showAllHistory: false }">

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">{{ $volunteer->full_name }}</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteers.index') }}" class="hover:text-brand-500 transition-colors">Volunteers</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">{{ $volunteer->full_name }}</span>
        </nav>
    </div>
    <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
        <a href="{{ route('volunteers.edit', $volunteer) }}"
           class="flex items-center gap-1.5 bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
            Edit
        </a>
        @if ($mergeCandidates->isNotEmpty())
            <button type="button" @click="mergeOpen = true"
                    class="flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors"
                    title="Merge this duplicate into another volunteer record">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                Merge
            </button>
        @endif
        <button type="button" @click="deleteOpen = true"
                class="flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
            Delete
        </button>
    </div>
</div>

@if (session('error'))
<div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    {{ session('error') }}
</div>
@endif

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Stat Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Groups</p>
        <p class="text-3xl font-bold text-gray-900">{{ $volunteer->groups->count() }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Role</p>
        @if ($volunteer->role)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-brand-100 text-brand-700">
                {{ $volunteer->role }}
            </span>
        @else
            <p class="text-2xl font-bold text-gray-400">—</p>
        @endif
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Events Served</p>
        <p class="text-3xl font-bold text-gray-900">{{ $stats['totalEvents'] }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Status</p>
        @if ($stats['isFirstTimer'])
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-bold bg-yellow-100 text-yellow-700">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                First Timer
            </span>
        @else
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-100 text-green-700">
                Returning
            </span>
        @endif
    </div>
</div>

{{-- Details grid --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    {{-- Contact Info --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">Contact Information</h2>
        </div>
        <div class="p-5 space-y-3.5">
            @if ($volunteer->phone)
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $volunteer->phone) }}"
                   class="flex items-center gap-3 text-sm text-gray-700 hover:text-brand-600 transition-colors">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                    {{ $volunteer->phone }}
                </a>
            @endif
            @if ($volunteer->email)
                <a href="mailto:{{ $volunteer->email }}"
                   class="flex items-center gap-3 text-sm text-gray-700 hover:text-brand-600 transition-colors break-all">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                    {{ $volunteer->email }}
                </a>
            @endif
            @if (! $volunteer->phone && ! $volunteer->email)
                <p class="text-sm text-gray-400 italic">No contact information on file.</p>
            @endif
            <div class="pt-3 border-t border-gray-100 grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Added</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $volunteer->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Last Updated</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $volunteer->updated_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Groups --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">Group Memberships</h2>
            <a href="{{ route('volunteer-groups.index') }}"
               class="text-xs text-brand-600 hover:text-brand-700 font-semibold transition-colors">
                Manage groups
            </a>
        </div>
        <div class="p-5">
            @if ($volunteer->groups->isEmpty())
                <p class="text-sm text-gray-400 italic mb-3">Not assigned to any groups yet.</p>
            @else
                <div class="space-y-2 mb-3">
                    @foreach ($volunteer->groups as $group)
                        <a href="{{ route('volunteer-groups.show', $group) }}"
                           class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 hover:border-brand-300 hover:bg-brand-50/30 transition-colors">
                            <span class="text-sm font-semibold text-gray-800">{{ $group->name }}</span>
                            <span class="text-xs text-gray-400">
                                Joined {{ $group->pivot->joined_at ? \Carbon\Carbon::parse($group->pivot->joined_at)->format('M Y') : '—' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Quick-add picker — only shows when there's at least one group
                 the volunteer is NOT yet in. Skipped on empty system to avoid
                 a dead dropdown. --}}
            @if ($availableGroups->isNotEmpty())
                <form method="POST" action="{{ route('volunteers.groups.attach', $volunteer) }}"
                      class="flex items-center gap-2 pt-3 border-t border-gray-100">
                    @csrf
                    <select name="group_id" required
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                        <option value="">Add to a group…</option>
                        @foreach ($availableGroups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-lg transition-colors">
                        Add
                    </button>
                </form>
            @endif
        </div>
    </div>

</div>

{{-- Service Summary Strip --}}
@if ($stats['totalEvents'] > 0)
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">First Service</p>
        <p class="text-sm font-bold text-gray-900">
            {{ $stats['firstService'] ? $stats['firstService']->format('M j, Y') : '—' }}
        </p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Last Service</p>
        <p class="text-sm font-bold text-gray-900">
            {{ $stats['lastService'] ? $stats['lastService']->format('M j, Y') : '—' }}
        </p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total Events</p>
        <p class="text-sm font-bold text-gray-900">{{ $stats['totalEvents'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total Hours</p>
        <p class="text-sm font-bold text-gray-900 tabular-nums">
            {{ number_format($stats['totalHours'] ?? 0, 1) }}h
        </p>
    </div>
</div>
@endif

{{-- Event Service History --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between gap-3 flex-wrap">
        <h2 class="text-sm font-semibold text-gray-800">Event Service History</h2>
        <div class="flex items-center gap-3 flex-wrap">
            @if ($stats['totalEvents'] > 0)
                <span class="text-xs text-gray-400">{{ $stats['totalEvents'] }} {{ $stats['totalEvents'] == 1 ? 'event' : 'events' }}</span>
                {{-- Phase 5.9 — Print + CSV exports of this volunteer's service
                     history. Hidden when there's nothing to export so we don't
                     dangle empty buttons on a fresh-volunteer page. --}}
                <a href="{{ route('volunteers.service-history.print', $volunteer) }}"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-brand-600 transition-colors"
                   title="Print branded service-history sheet">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                    Print
                </a>
                <a href="{{ route('volunteers.service-history.csv', $volunteer) }}"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-brand-600 transition-colors"
                   title="Download CSV of service history">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    CSV
                </a>
            @endif
        </div>
    </div>

    @if ($stats['checkIns']->isEmpty())
        <div class="px-5 py-12 text-center">
            <svg class="w-10 h-10 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
            <p class="text-sm text-gray-400">No event service history yet.</p>
        </div>
    @else
        @php
            // Truncate to the most-recent 15 by default; toggle reveals the rest.
            // checkIns is already ordered by checked_in_at desc in stats().
            $historyLimit = 15;
            $historyTotal = $stats['checkIns']->count();
            $historyOverflow = $historyTotal > $historyLimit;
        @endphp
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Event</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Check-In</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Check-Out</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Hours</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($stats['checkIns'] as $ci)
                    <tr class="hover:bg-gray-50/50 transition-colors"
                        @if ($historyOverflow && $loop->index >= $historyLimit) x-show="showAllHistory" x-cloak @endif>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if ($ci->event)
                                    <a href="{{ route('events.show', $ci->event) }}"
                                       class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">
                                        {{ $ci->event->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400 italic">Event removed</span>
                                @endif
                                @if ($ci->is_first_timer)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                        First Timer
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $ci->event?->date->format('M j, Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $ci->role ?: '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $ci->checked_in_at?->format('g:i A') ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-gray-600 hidden sm:table-cell">
                            {{ $ci->checked_out_at?->format('g:i A') ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-right hidden sm:table-cell">
                            @if($ci->hours_served !== null)
                                <span class="font-semibold text-gray-700 tabular-nums">{{ number_format((float)$ci->hours_served, 1) }}h</span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $ci->sourceBadgeClasses() }}">
                                {{ $ci->sourceLabel() }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @foreach ($stats['checkIns'] as $ci)
            <div class="px-4 py-3.5"
                 @if ($historyOverflow && $loop->index >= $historyLimit) x-show="showAllHistory" x-cloak @endif>
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div>
                        @if ($ci->event)
                            <a href="{{ route('events.show', $ci->event) }}"
                               class="text-sm font-semibold text-gray-900 hover:text-brand-600">
                                {{ $ci->event->name }}
                            </a>
                        @else
                            <span class="text-sm text-gray-400 italic">Event removed</span>
                        @endif
                        @if ($ci->is_first_timer)
                            <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                First Timer
                            </span>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $ci->sourceBadgeClasses() }} flex-shrink-0">
                        {{ $ci->sourceLabel() }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                    <span>{{ $ci->event?->date->format('M j, Y') ?? '—' }}</span>
                    @if ($ci->role)<span>{{ $ci->role }}</span>@endif
                    @if ($ci->checked_in_at)<span>In {{ $ci->checked_in_at->format('g:i A') }}</span>@endif
                    @if ($ci->checked_out_at)<span>Out {{ $ci->checked_out_at->format('g:i A') }}</span>@endif
                    @if ($ci->hours_served !== null)<span class="font-semibold text-gray-700">{{ number_format((float)$ci->hours_served, 1) }}h</span>@endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Show all / Show fewer toggle (only when there's overflow) --}}
        @if ($historyOverflow)
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50 flex items-center justify-center">
                <button type="button" @click="showAllHistory = !showAllHistory"
                        class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">
                    <span x-show="!showAllHistory">
                        Show all {{ $historyTotal }} sessions
                    </span>
                    <span x-show="showAllHistory" x-cloak>
                        Show fewer
                    </span>
                </button>
            </div>
        @endif
    @endif
</div>

{{-- Merge Modal --}}
@if ($mergeCandidates->isNotEmpty())
<div x-show="mergeOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="mergeOpen = false" style="display:none;">
    <div x-show="mergeOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">

        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
            </div>
            <h2 class="text-base font-bold text-gray-900">Merge Volunteer Record</h2>
        </div>

        <p class="text-sm text-gray-600 leading-relaxed mb-4">
            Pick the volunteer record to merge <strong>{{ $volunteer->full_name }}</strong> into.
            All their check-ins, group memberships, and event assignments will move to the chosen record,
            and <strong>{{ $volunteer->full_name }}</strong> will be deleted. This cannot be undone.
        </p>

        <form method="POST" action="{{ route('volunteers.merge', $volunteer) }}">
            @csrf
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                Keeper (the record to keep) <span class="text-red-500">*</span>
            </label>
            <select name="keeper_id" required x-model="mergeKeeperId"
                    class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl bg-white
                           focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400">
                <option value="">Select a volunteer…</option>
                @foreach ($mergeCandidates as $cand)
                    <option value="{{ $cand->id }}">
                        {{ trim($cand->first_name . ' ' . $cand->last_name) }}@if ($cand->phone) — {{ $cand->phone }}@endif
                    </option>
                @endforeach
            </select>

            <div class="bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-2.5 text-xs text-amber-800 mt-3 leading-relaxed">
                <strong>Heads up:</strong> if both volunteers have an open check-in for the same event, the merge
                will be refused — please check one of them out first.
            </div>

            <div class="flex items-center gap-3 mt-5">
                <button type="button" @click="mergeOpen = false"
                        class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        :disabled="!mergeKeeperId"
                        class="flex-1 py-2.5 text-sm font-semibold bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl transition-colors">
                    Merge
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Delete Modal --}}
<div x-show="deleteOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="deleteOpen = false" style="display:none;">
    <div x-show="deleteOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </div>
        <h2 class="text-base font-bold text-gray-900 mb-2">Remove Volunteer</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Remove <strong class="text-gray-700">{{ $volunteer->full_name }}</strong>? This cannot be undone.
        </p>
        <div class="flex items-center gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form action="{{ route('volunteers.destroy', $volunteer) }}" method="POST" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full py-2.5 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">
                    Remove
                </button>
            </form>
        </div>
    </div>
</div>

</div>
@endsection
