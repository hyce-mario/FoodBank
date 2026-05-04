@extends('layouts.app')
@section('title', 'Volunteers')

@section('content')
<div x-data="{ deleteId: null, deleteName: '', deleteOpen: false }">

{{-- Header --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Volunteers</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Volunteers</span>
        </nav>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('volunteer-groups.index') }}"
           class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50
                  font-semibold text-sm rounded-lg px-3 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
            </svg>
            Groups
        </a>
        <a href="{{ route('volunteers.create') }}"
           class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
                  font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add Volunteer
        </a>
    </div>
</div>

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Main Card --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Toolbar — single flex-wrap row: filters + Search + Clear + (divider) +
         Print / PDF / CSV icon buttons. Per-page selector lives at the bottom
         of the table so the toolbar stays focused on the filter+export controls.

         Anchors are inside the form (HTML allows it; they navigate via href
         and don't submit) so the whole row participates in one flex layout
         and wraps cleanly on narrow widths. --}}
    @php
        // Carry active filters through to the export endpoints so a
        // user printing/exporting from a filtered view gets the matching
        // subset rather than the full roster. Strips per_page (irrelevant
        // for exports — they're un-paginated).
        $exportQuery = array_filter([
            'search'    => request('search'),
            'role'      => request('role'),
            'group'     => request('group'),
            'sort'      => request('sort'),
            'direction' => request('direction'),
        ]);
    @endphp
    <form method="GET" action="{{ route('volunteers.index') }}"
          class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100 bg-gray-50/50">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search name, email, phone..."
               class="flex-1 min-w-[180px] px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                      focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                      placeholder:text-gray-400">
        <select name="role"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                       focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                       text-gray-700">
            <option value="">All roles</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="group"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                       focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                       text-gray-700">
            <option value="">All groups</option>
            @foreach ($groups as $g)
                <option value="{{ $g->id }}" @selected((string) request('group') === (string) $g->id)>
                    {{ $g->name }}
                </option>
            @endforeach
        </select>
        {{-- Preserve the per-page choice through filter submits (the
             selector lives at the bottom of the table now). --}}
        @if ($pp = request('per_page'))
            <input type="hidden" name="per_page" value="{{ $pp }}">
        @endif
        <button type="submit"
                class="px-4 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
            Search
        </button>
        @if (request('search') || request('role') || request('group'))
            <a href="{{ route('volunteers.index') }}"
               class="px-4 py-2 text-sm font-semibold border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors text-center">
                Clear
            </a>
        @endif

        {{-- Vertical divider — visible on sm+ where the row stays single-line --}}
        <span class="hidden sm:block w-px h-7 bg-gray-300 mx-1" aria-hidden="true"></span>

        {{-- Export icon buttons — 36×36 square. title + aria-label give
             tooltip + screen-reader text since the buttons are icon-only.
             Each link carries the active filter query string so a
             filtered view exports the matching subset. --}}
        <a href="{{ route('volunteers.export.print', $exportQuery) }}"
           target="_blank"
           title="Print roster"
           aria-label="Print roster"
           class="w-9 h-9 inline-flex items-center justify-center border border-gray-300 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
        </a>
        <a href="{{ route('volunteers.export.csv', $exportQuery) }}"
           title="Download CSV"
           aria-label="Download CSV"
           class="w-9 h-9 inline-flex items-center justify-center border border-green-200 text-green-700 hover:bg-green-50 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        </a>
    </form>

    {{-- Desktop Table --}}
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Groups</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Served</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($volunteers as $vol)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('volunteers.show', $vol) }}"
                                   class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">
                                    {{ $vol->full_name }}
                                </a>
                                @if ((int) $vol->events_served_count === 0)
                                    {{-- 0 events = registered but never served. NOT the same as
                                         "first timer" — that label belongs to the volunteer's first
                                         actual event. Pre-fix, this branch incorrectly showed
                                         "First Timer" for never-served volunteers. --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                                        New
                                    </span>
                                @elseif ((int) $vol->events_served_count === 1)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                        First Timer
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $vol->phone ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $vol->email ?: '—' }}</td>
                        <td class="px-5 py-3.5">
                            @if ($vol->role)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                             bg-brand-100 text-brand-700">
                                    {{ $vol->role }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            {{ $vol->groups_count }}
                            {{ $vol->groups_count == 1 ? 'group' : 'groups' }}
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            {{ $vol->events_served_count }}
                            {{ (int) $vol->events_served_count === 1 ? 'event' : 'events' }}
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('volunteers.show', $vol) }}"
                                   class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
                                   title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                </a>
                                <a href="{{ route('volunteers.edit', $vol) }}"
                                   class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                                </a>
                                <button type="button"
                                        @click="deleteId={{ $vol->id }}; deleteName='{{ addslashes($vol->full_name) }}'; deleteOpen=true"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-red-400 hover:bg-red-50 transition-colors"
                                        title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-14 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-500">No volunteers found</p>
                                <a href="{{ route('volunteers.create') }}"
                                   class="text-sm text-brand-600 hover:text-brand-700 font-semibold">
                                    Add the first volunteer
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Cards --}}
    <div class="sm:hidden divide-y divide-gray-100">
        @forelse ($volunteers as $vol)
            <div class="p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('volunteers.show', $vol) }}"
                               class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">
                                {{ $vol->full_name }}
                            </a>
                            @if ($vol->role)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                                    {{ $vol->role }}
                                </span>
                            @endif
                            @if ((int) $vol->events_served_count === 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                                    New
                                </span>
                            @elseif ((int) $vol->events_served_count === 1)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                    First Timer
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $vol->groups_count }} {{ $vol->groups_count == 1 ? 'group' : 'groups' }}
                            &bull; {{ $vol->events_served_count }} {{ (int) $vol->events_served_count === 1 ? 'event served' : 'events served' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <a href="{{ route('volunteers.edit', $vol) }}"
                           class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                        </a>
                        <button type="button"
                                @click="deleteId={{ $vol->id }}; deleteName='{{ addslashes($vol->full_name) }}'; deleteOpen=true"
                                class="w-9 h-9 flex items-center justify-center rounded-xl border border-red-200 text-red-400 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </div>
                </div>
                @if ($vol->phone || $vol->email)
                    <div class="flex flex-col gap-1 text-sm text-gray-600">
                        @if ($vol->phone)
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                                {{ $vol->phone }}
                            </span>
                        @endif
                        @if ($vol->email)
                            <span class="flex items-center gap-1.5 truncate">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                                {{ $vol->email }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="px-5 py-14 text-center">
                <p class="text-sm text-gray-400">No volunteers yet.</p>
                <a href="{{ route('volunteers.create') }}" class="mt-2 inline-block text-sm text-brand-600 font-semibold">Add the first volunteer</a>
            </div>
        @endforelse
    </div>

    {{-- Footer — pagination + per-page selector. The selector POSTs via
         a tiny form so changing the dropdown reloads the page with the
         new per_page value while preserving the existing filter query
         string. The form lives here (rather than the toolbar) so the
         control sits next to the page links it governs. --}}
    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" action="{{ route('volunteers.index') }}" class="flex items-center gap-2 text-xs text-gray-600"
              x-data x-on:change="$el.submit()">
            {{-- Preserve filter query string when the per-page is changed --}}
            @foreach (['search', 'role', 'group', 'sort', 'direction'] as $k)
                @if ($v = request($k))
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach
            <label for="per_page" class="font-medium text-gray-500">Show</label>
            <select id="per_page" name="per_page"
                    class="px-2 py-1.5 text-sm border border-gray-300 rounded-lg bg-white
                           focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                           text-gray-700">
                @foreach ([15, 25, 50] as $opt)
                    <option value="{{ $opt }}" @selected((int) request('per_page', 15) === $opt)>{{ $opt }} per page</option>
                @endforeach
            </select>
            <noscript>
                <button type="submit" class="px-2 py-1 text-xs font-semibold border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50">Apply</button>
            </noscript>
            <span class="text-gray-400">·</span>
            <span class="text-gray-500">
                Showing <strong class="text-gray-700">{{ $volunteers->firstItem() ?? 0 }}</strong>–<strong class="text-gray-700">{{ $volunteers->lastItem() ?? 0 }}</strong>
                of <strong class="text-gray-700">{{ $volunteers->total() }}</strong>
            </span>
        </form>
        @if ($volunteers->hasPages())
            <div>{{ $volunteers->links() }}</div>
        @endif
    </div>

</div>

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
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </div>
        <h2 class="text-base font-bold text-gray-900 mb-2">Remove Volunteer</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Are you sure you want to remove <strong x-text="deleteName" class="text-gray-700"></strong>?
        </p>
        <div class="flex items-center gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form :action="'/volunteers/' + deleteId" method="POST" class="flex-1">
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
