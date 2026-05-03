@extends('layouts.app')
@section('title', $event->name)

@push('styles')
<style>
    /* Event Report status badges (Phase B). Inline custom CSS so we don't
       depend on Tailwind colour classes that may not be in the prebuilt
       bundle (no Node available). */
    .er-status            { display:inline-flex; align-items:center; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; }
    .er-status-checked_in { background:#EFF6FF; color:#1D4ED8; }
    .er-status-queued     { background:#F5F3FF; color:#6D28D9; }
    .er-status-loading    { background:#FFF7ED; color:#C2410C; }
    .er-status-loaded     { background:#FEF3C7; color:#B45309; }
    .er-status-exited     { background:#F0FDF4; color:#15803D; }
</style>
@endpush

@section('content')

@php
    $filterLabel = match($event->status) {
        'current'  => "Today's Events",
        'past'     => 'Past Events',
        default    => 'Upcoming Events',
    };
    $publicUrl   = route('public.register', $event);

    // Pre-compute for safe @json() usage (avoids array-literal-inside-directive parse error)
    $assignedVolsMapped = $event->assignedVolunteers->map(fn($v) => [
        'id'   => $v->id,
        'name' => $v->full_name,
        'role' => $v->role ?? '',
    ]);
    $detachUrlBase = url("events/{$event->id}/volunteers");

    $mediaMapped = $event->media->map(fn($m) => [
        'id'             => $m->id,
        'type'           => $m->type,
        'url'            => $m->url,
        'name'           => $m->name,
        'size_formatted' => $m->size_formatted,
        'mime_type'      => $m->mime_type,
    ]);
@endphp

<div x-data="eventShow()" x-cloak>

{{-- ── Header ──────────────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="text-xl font-bold text-gray-900">{{ $event->name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $event->statusBadgeClasses() }}">
                {{ $event->statusLabel() }}
            </span>
        </div>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5 flex-wrap">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('events.index') }}" class="hover:text-brand-500 transition-colors">Events</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('events.index', ['filter' => $event->status]) }}"
               class="hover:text-brand-500 transition-colors">{{ $filterLabel }}</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">{{ $event->name }}</span>
        </nav>
    </div>

    {{-- Action buttons --}}
    <div class="flex items-center gap-2 flex-shrink-0 flex-wrap self-start">
        <button type="button" @click="regLinkOpen = true"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-lg px-3.5 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>
            Public Registration Link
        </button>

        @if ($event->isCurrent())
        {{-- Mark Complete: AJAX status change --}}
        <div x-data="statusBtn('{{ route('events.status', $event) }}', 'current')">
            <button type="button"
                    @click="advance()"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg px-3.5 py-2.5 transition-colors">
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span x-text="loading ? 'Updating…' : 'Mark Complete'"></span>
            </button>
        </div>
        @endif

        @if ($event->isLocked())
        {{-- Undo Complete: revert past → current/upcoming based on date --}}
        <div x-data="statusBtn('{{ route('events.status', $event) }}', 'past')">
            <button type="button"
                    @click="advance()"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg px-3.5 py-2.5 transition-colors">
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                <span x-text="loading ? 'Updating…' : 'Undo Complete'"></span>
            </button>
        </div>
        @endif

        @unless ($event->isLocked())
        <a href="{{ route('events.edit', $event) }}"
           class="inline-flex items-center gap-2 bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold rounded-lg px-3.5 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
            Edit Event
        </a>
        <button type="button" @click="deleteOpen = true"
                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg px-3.5 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
            Delete Event
        </button>
        @endunless
    </div>
</div>

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

@if (session('error'))
<div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
    {{ session('error') }}
</div>
@endif

{{-- ── Tabs ─────────────────────────────────────────────────────────────────── --}}
<div class="flex items-center gap-1 mb-5">
    <button type="button" @click="activeTab = 'details'"
            :class="activeTab === 'details' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Event Details
    </button>
    <button type="button" @click="activeTab = 'preregistered'"
            :class="activeTab === 'preregistered' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Attendees ({{ $event->preRegistrations->count() }})
    </button>
    <button type="button" @click="activeTab = 'photos'"
            :class="activeTab === 'photos' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Photos &amp; Video
    </button>
    <button type="button" @click="activeTab = 'reviews'"
            :class="activeTab === 'reviews' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Reviews ({{ $event->reviews->count() }})
    </button>
    <button type="button" @click="activeTab = 'inventory'"
            :class="activeTab === 'inventory' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Inventory ({{ $event->inventoryAllocations->count() }})
    </button>
    <button type="button" @click="activeTab = 'finance'"
            :class="activeTab === 'finance' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Finance ({{ $eventTransactions->count() }})
    </button>
    <button type="button" @click="activeTab = 'volunteers'"
            :class="activeTab === 'volunteers' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Volunteers ({{ $event->volunteerCheckIns->count() }})
    </button>
    @if ($event->isCurrent())
    <button type="button" @click="activeTab = 'queue'"
            :class="activeTab === 'queue' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors">
        Queue Access
    </button>
    @endif
</div>

{{-- ── Event Details Tab ───────────────────────────────────────────────────── --}}
<div x-show="activeTab === 'details'">

    {{-- Stat cards — white with border + shadow, colored icon badge per metric.
         Numbers come from $eventStats (computed live in EventController::show). --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        {{-- Food Packs — orange (matches the brand "delivery / output" hue) --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($eventStats['packs_served']) }}</p>
                    <p class="text-xs text-gray-500 mt-1.5 font-medium">Food Pack Served</p>
                </div>
            </div>
        </div>

        {{-- Households — blue (households / people identity) --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12 12 2.25 21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($eventStats['households_served']) }}</p>
                    <p class="text-xs text-gray-500 mt-1.5 font-medium">Households</p>
                </div>
            </div>
        </div>

        {{-- Volunteers Served — green (helpful / showed up) --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($eventStats['volunteers_served']) }}</p>
                    <p class="text-xs text-gray-500 mt-1.5 font-medium">Volunteer Served</p>
                </div>
            </div>
        </div>

        {{-- Attendees (pre-registered) — navy (matches dashboard quick-action navy) --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-navy-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-navy-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($eventStats['attendees_pre_reg']) }}</p>
                    <p class="text-xs text-gray-500 mt-1.5 font-medium">Attendees</p>
                </div>
            </div>
        </div>
    </div>

    {{-- 2-col layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

        {{-- Left column --}}
        <div class="space-y-5 min-w-0">

            {{-- Event meta --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
                <h2 class="text-base font-bold text-gray-900 mb-3">{{ $event->name }}</h2>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-600">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        {{ $event->date->format('D, M j, Y') }}
                    </span>
                    @if ($event->location)
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                            {{ $event->location }}
                        </span>
                    @endif
                    <span class="flex items-center gap-2 text-gray-400">
                        Assigned Group:
                        @if ($event->volunteerGroup)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border border-gray-300 text-gray-700 bg-white">
                                {{ $event->volunteerGroup->name }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs border border-gray-200 text-gray-400 bg-white">—</span>
                        @endif
                    </span>
                </div>
            </div>

            {{-- Event Report (Phase B) --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Event Report</h3>
                    <div class="flex items-center gap-2">
                        {{-- Export PDF / Excel / Print buttons stay inert here;
                             Phase C.3 ships a real CSV + branded print sheet. --}}
                        <button type="button" title="Export PDF" disabled
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 opacity-60 cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 1.5L18.5 9H13zM8 17v-1h8v1zm0-3v-1h8v1zm0-3V10h4v1z"/></svg>
                        </button>
                        <button type="button" title="Export Excel" disabled
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-green-200 bg-green-50 text-green-600 opacity-60 cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 1.5L18.5 9H13zm-3 8.5 2 3h-1.3l-1.2-2-1.2 2H8l2-3-2-3h1.3l1.2 2 1.2-2H13z"/></svg>
                        </button>
                        <button type="button" title="Print" disabled
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-600 opacity-60 cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/60">
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">ID</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Household</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Household Size</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Bags</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Check-in Time</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody data-testid="event-report-body">
                            @forelse ($visitReport as $visit)
                                @php
                                    $allHouseholds = $visit->households;
                                    // The pivot ordering above (visit_households.id ASC) is
                                    // the source of truth for "primary first, then represented".
                                    $primary       = $allHouseholds->first();
                                    $represented   = $allHouseholds->slice(1);
                                    $ruleset       = $event->ruleset;
                                    $timeFmt       = $visit->start_time
                                        ? $visit->start_time->format('M j, g:i A')
                                        : '—';
                                    $statusKey     = $visit->visit_status;
                                    $statusLabel   = $visit->statusLabel();
                                @endphp
                                @if ($primary)
                                    <tr class="border-b border-gray-100" data-row="primary" data-visit-id="{{ $visit->id }}">
                                        <td class="px-5 py-3 text-xs font-mono tabular-nums text-gray-700">#{{ $primary->household_number }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-900 font-medium">{{ $primary->full_name }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 tabular-nums">{{ (int) ($primary->pivot->household_size ?? $primary->household_size) }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 tabular-nums">
                                            @if ($ruleset)
                                                {{ (int) $ruleset->getBagsFor((int) ($primary->pivot->household_size ?? $primary->household_size)) }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-xs text-gray-600 tabular-nums">{{ $timeFmt }}</td>
                                        <td class="px-5 py-3">
                                            <span class="er-status er-status-{{ $statusKey }}">{{ $statusLabel }}</span>
                                        </td>
                                    </tr>
                                    @foreach ($represented as $rep)
                                        <tr class="border-b border-gray-100 bg-gray-50/30" data-row="represented" data-visit-id="{{ $visit->id }}">
                                            <td class="px-5 py-2.5 text-xs font-mono tabular-nums text-gray-600">#{{ $rep->household_number }}</td>
                                            <td class="px-5 py-2.5 text-sm text-gray-700">
                                                <span class="text-gray-400 mr-1">↳</span>{{ $rep->full_name }}
                                            </td>
                                            <td class="px-5 py-2.5 text-sm text-gray-700 tabular-nums">{{ (int) ($rep->pivot->household_size ?? $rep->household_size) }}</td>
                                            <td class="px-5 py-2.5 text-sm text-gray-700 tabular-nums">
                                                @if ($ruleset)
                                                    {{ (int) $ruleset->getBagsFor((int) ($rep->pivot->household_size ?? $rep->household_size)) }}
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-2.5 text-xs text-gray-600 tabular-nums">{{ $timeFmt }}</td>
                                            <td class="px-5 py-2.5">
                                                <span class="er-status er-status-{{ $statusKey }}">{{ $statusLabel }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-14 text-center">
                                        <svg class="w-10 h-10 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        <p class="text-sm text-gray-400">No check-ins recorded yet.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between px-5 py-3.5 border-t border-gray-100 bg-gray-50/40 flex-wrap gap-3">
                    <form method="GET" action="{{ route('events.show', $event) }}" class="flex items-center gap-2 text-sm text-gray-500">
                        <span>Row Per Page</span>
                        <select name="details_per" onchange="this.form.submit()"
                                class="px-2 py-1 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none">
                            @foreach ([10, 25, 50] as $opt)
                                <option value="{{ $opt }}" @selected((int) $detailsPerPage === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <span>Entries</span>
                    </form>
                    <div>
                        {{ $visitReport->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Volunteer Sidebar --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col h-[600px]">

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">
                    Volunteers
                    <span class="ml-1 text-xs font-normal text-gray-400">(<span x-text="volunteers.length">{{ $event->assignedVolunteers->count() }}</span>)</span>
                </h3>
                <a href="{{ route('events.edit', $event) }}"
                   class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                   title="Edit volunteers">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                </a>
            </div>

            {{-- Search --}}
            <div class="px-4 py-3 border-b border-gray-100">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    <input type="text" x-model="volSearch" placeholder="Search volunteers..."
                           class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  placeholder:text-gray-400">
                </div>
            </div>

            {{-- Volunteer list --}}
            <div class="divide-y divide-gray-50 flex-1 overflow-y-auto">

                {{-- Populated list --}}
                <template x-for="vol in filteredVolunteers" :key="vol.id">
                    <div class="flex items-center gap-3 px-4 py-3 group">

                        {{-- Checkbox: clicking unchecks/removes the volunteer --}}
                        <button type="button"
                                @click="removeVolunteer(vol.id)"
                                :disabled="removing === vol.id"
                                class="w-5 h-5 rounded flex-shrink-0 flex items-center justify-center
                                       bg-brand-500 border-2 border-brand-500
                                       hover:bg-red-500 hover:border-red-500
                                       disabled:opacity-50 transition-colors group/cb"
                                title="Uncheck to remove">
                            <svg x-show="removing !== vol.id"
                                 class="w-3 h-3 text-white group-hover/cb:hidden" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            {{-- X icon on hover --}}
                            <svg x-show="removing !== vol.id"
                                 class="w-3 h-3 text-white hidden group-hover/cb:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                            {{-- Spinner while removing --}}
                            <svg x-show="removing === vol.id"
                                 class="w-3 h-3 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                        </button>

                        <span class="flex-1 text-sm font-medium text-gray-900 truncate" x-text="vol.name"></span>
                        <span x-show="vol.role"
                              class="text-xs text-gray-400 flex-shrink-0 bg-gray-100 px-2 py-0.5 rounded-full"
                              x-text="vol.role"></span>
                    </div>
                </template>

                {{-- No search results --}}
                <div x-show="filteredVolunteers.length === 0 && volSearch.trim() !== ''"
                     class="px-4 py-6 text-center text-sm text-gray-400">
                    No volunteers match your search.
                </div>

                {{-- Fully empty (no volunteers at all) --}}
                <div x-show="volunteers.length === 0 && volSearch.trim() === ''"
                     class="px-4 py-10 text-center">
                    <svg class="w-8 h-8 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                    <p class="text-sm text-gray-400">No volunteers assigned.</p>
                    <a href="{{ route('events.edit', $event) }}"
                       class="mt-2 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">
                        Assign volunteers →
                    </a>
                </div>
            </div>

            {{-- Footer count --}}
            <div x-show="volunteers.length > 0"
                 class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                <p class="text-xs text-gray-400">
                    <span x-text="volunteers.length"></span>
                    <span x-text="volunteers.length === 1 ? ' volunteer assigned' : ' volunteers assigned'"></span>
                </p>
            </div>
        </div>

    </div>
</div>

{{-- ── Attendees Tab ────────────────────────────────────────────────────────── --}}
<div x-show="activeTab === 'preregistered'">
    @if ($event->preRegistrations->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-8 py-16 text-center">
            <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
            <p class="text-sm font-medium text-gray-500 mb-1">No attendees yet</p>
            <p class="text-xs text-gray-400">Share the <button @click="activeTab='details'; $nextTick(() => regLinkOpen=true)" class="text-brand-600 font-semibold hover:underline">public registration link</button> to start collecting sign-ups.</p>
        </div>
    @else
        {{-- Phase C.1 — Pre-reg stat cards. All cards share the same chrome
             (white + gray outline + light shadow); icon badges are neutral
             so the card row reads as one calm strip. Always one row. --}}
        <div class="grid grid-cols-5 gap-4 mb-5">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p data-stat="total" class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($attendeeStats['total']) }}</p>
                        <p class="text-xs text-gray-500 mt-1.5 font-medium">Total Attendees</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p data-stat="children" class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($attendeeStats['children']) }}</p>
                        <p class="text-xs text-gray-500 mt-1.5 font-medium">Children</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p data-stat="adults" class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($attendeeStats['adults']) }}</p>
                        <p class="text-xs text-gray-500 mt-1.5 font-medium">Adults</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p data-stat="seniors" class="text-2xl font-black text-gray-900 tabular-nums leading-none">{{ number_format($attendeeStats['seniors']) }}</p>
                        <p class="text-xs text-gray-500 mt-1.5 font-medium">Seniors</p>
                    </div>
                </div>
            </div>

            {{-- Phase C.2 — Forecast card. Card face stays clean (icon + big
                 number + label); the breakdown lives in a callout that reveals
                 on hover, click, or keyboard activation, so the 5-card row
                 reads as one calm strip until someone reaches for the detail. --}}
            <div data-card="forecast"
                 @if ($attendeeForecast['enabled'])
                    x-data="{ open: false }"
                    @mouseenter="open = true"
                    @mouseleave="open = false"
                    @click="open = !open"
                    @keydown.enter.prevent="open = !open"
                    @keydown.space.prevent="open = !open"
                    @keydown.escape="open = false"
                    role="button"
                    tabindex="0"
                    :aria-expanded="open.toString()"
                    aria-haspopup="true"
                 @endif
                 class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5 relative @if ($attendeeForecast['enabled']) cursor-help focus:outline-none focus:ring-2 focus:ring-brand-400 @endif">
                @if ($attendeeForecast['enabled'])
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p data-stat="forecast-total" class="text-2xl font-black text-gray-900 tabular-nums leading-none">~{{ number_format($attendeeForecast['projected_total']) }}</p>
                            <p class="text-xs text-gray-500 mt-1.5 font-medium flex items-center gap-1">
                                Attendees Predicted
                                <svg class="w-3 h-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                                </svg>
                            </p>
                        </div>
                    </div>

                    {{-- Callout: aligned to the right edge so it never spills off
                         the rightmost card in the 5-up grid. Dark surface for
                         legibility against the light page. --}}
                    <div x-show="open"
                         x-transition.opacity.duration.150ms
                         x-cloak
                         data-testid="forecast-callout"
                         class="absolute top-full right-0 mt-2 z-20 w-64 bg-gray-900 text-white text-xs rounded-lg shadow-lg px-4 py-3 leading-relaxed">
                        <p class="text-gray-400 uppercase tracking-wider text-[10px] font-semibold mb-1.5">Forecast breakdown</p>
                        <p>
                            Pre-reg: <span class="font-semibold tabular-nums">{{ number_format($attendeeForecast['current_pre_reg']) }}</span>
                        </p>
                        <p>
                            Walk-in (est): <span class="font-semibold tabular-nums">{{ number_format($attendeeForecast['projected_walk_ins']) }}</span>
                        </p>
                        <p class="text-gray-400 mt-2 italic">
                            Based on last event ({{ number_format($attendeeForecast['last_event_visits']) }} visits)
                        </p>
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-500 leading-snug">Not enough history yet</p>
                            <p class="text-[11px] text-gray-400 mt-1 leading-snug">Hold an event to start forecasting.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            {{-- Table header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">Attendees</h3>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400 mr-2">{{ $event->preRegistrations->count() }} total</span>
                    {{-- Export PDF stays inert; Excel-via-phpoffice was the
                         deferred locked decision. --}}
                    <button type="button" title="Export PDF (coming soon)" disabled
                            class="w-8 h-8 flex items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 opacity-60 cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 1.5L18.5 9H13zM8 17v-1h8v1zm0-3v-1h8v1zm0-3V10h4v1z"/></svg>
                    </button>
                    {{-- The "Excel" button slot is now a real CSV export. The
                         locked decision was CSV + Print for v1; we kept the
                         green spreadsheet icon since it reads as "tabular
                         export" without saying "Excel". --}}
                    <a href="{{ route('events.attendees.csv', $event) }}" title="Export CSV"
                            class="w-8 h-8 flex items-center justify-center rounded-lg border border-green-200 bg-green-50 text-green-600 hover:bg-green-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 1.5L18.5 9H13zm-3 8.5 2 3h-1.3l-1.2-2-1.2 2H8l2-3-2-3h1.3l1.2 2 1.2-2H13z"/></svg>
                    </a>
                    <a href="{{ route('events.attendees.print', $event) }}" target="_blank" rel="noopener" title="Print"
                            class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                    </a>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/60">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                <span class="flex items-center gap-1">
                                    ID
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 3L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/></svg>
                                </span>
                            </th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Household</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Email</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Location</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Zipcode</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Size</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($event->preRegistrations as $reg)
                            <tr class="hover:bg-gray-50/40 align-top">
                                {{-- ID --}}
                                <td class="px-5 py-3.5 text-sm font-mono text-gray-500 whitespace-nowrap">
                                    {{ $reg->attendee_number ?? str_pad($reg->id, 5, '0', STR_PAD_LEFT) }}
                                </td>

                                {{-- Household name --}}
                                <td class="px-5 py-3.5 font-semibold text-gray-900 whitespace-nowrap">
                                    {{ $reg->full_name }}
                                </td>

                                {{-- Email --}}
                                <td class="px-5 py-3.5 text-gray-500 text-xs">
                                    {{ $reg->email }}
                                </td>

                                {{-- Location --}}
                                <td class="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                                    {{ collect([$reg->city, $reg->state])->filter()->implode(', ') ?: '—' }}
                                </td>

                                {{-- Zipcode --}}
                                <td class="px-5 py-3.5 text-gray-500 text-xs">
                                    {{ $reg->zipcode ?: '—' }}
                                </td>

                                {{-- Household size --}}
                                <td class="px-5 py-3.5 text-gray-700 font-semibold">
                                    {{ $reg->household_size }}
                                </td>

                                {{-- Status --}}
                                <td class="px-5 py-3.5">
                                    @if ($reg->match_status === 'matched')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-500 text-white">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            Matched
                                        </span>
                                        @if ($reg->household)
                                            <p class="text-xs text-gray-400 mt-1">#{{ $reg->household->household_number }}</p>
                                        @endif

                                    @elseif ($reg->match_status === 'potential_match')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border border-orange-400 text-orange-600 bg-orange-50">
                                            Potential Match
                                        </span>
                                        @if ($reg->potentialHousehold)
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $reg->potentialHousehold->full_name }}
                                                (#{{ $reg->potentialHousehold->household_number }})
                                            </p>
                                            <div class="flex items-center gap-2 mt-1.5">
                                                <form method="POST"
                                                      action="{{ route('events.attendees.match', [$event, $reg]) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-navy-700 hover:bg-navy-800 text-white transition-colors">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                                                        Confirm Match
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('events.attendees.dismiss', [$event, $reg]) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border border-gray-300 text-gray-500 hover:border-red-300 hover:text-red-600 hover:bg-red-50 transition-colors">
                                                        Not them
                                                    </button>
                                                </form>
                                            </div>
                                        @endif

                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                            New
                                        </span>
                                        <form method="POST"
                                              action="{{ route('events.attendees.register', [$event, $reg]) }}"
                                              class="mt-1.5">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-green-600 hover:bg-green-700 text-white transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                                Register as Household
                                            </button>
                                        </form>
                                    @endif
                                </td>

                                {{-- Delete action — opens an Alpine confirm modal (see bottom of tab). --}}
                                <td class="px-4 py-3.5 text-right">
                                    <button type="button"
                                            @click="openAttendeeDelete(@js($reg->full_name . ' (#' . $reg->attendee_number . ')'), @js(route('events.attendees.delete', [$event, $reg])))"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:border-red-200 hover:bg-red-50 hover:text-red-500 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-t border-gray-100 bg-gray-50/40">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Row Per Page</span>
                    <select class="px-2 py-1 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none">
                        <option>10</option><option>25</option><option>50</option>
                    </select>
                    <span>Entries</span>
                </div>
                <div class="flex items-center gap-1">
                    <button disabled class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 text-gray-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                    </button>
                    <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-navy-700 text-white text-xs font-bold">1</span>
                    <button disabled class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 text-gray-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Attendee delete confirmation modal ─────────────────────────────────
         Replaces the previous browser confirm(). Lives inside the eventShow()
         Alpine scope so attendeeDelete.* state is reactive. --}}
    <div x-show="attendeeDelete.show" x-cloak style="display:none"
         @keydown.escape.window="closeAttendeeDelete()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div @click.away="closeAttendeeDelete()"
             role="dialog" aria-modal="true" aria-labelledby="attendeeDeleteTitle"
             class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 id="attendeeDeleteTitle" class="text-base font-bold text-gray-900">Remove pre-registered attendee?</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        <span class="font-semibold text-gray-700" x-text="attendeeDelete.name"></span>
                        will be removed from this event's pre-registration list. Their household record (if any) is not affected.
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="closeAttendeeDelete()"
                        class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">
                    Cancel
                </button>
                <form method="POST" :action="attendeeDelete.url" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg">
                        Remove
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ── Photos & Video Tab ──────────────────────────────────────────────────── --}}
<div x-show="activeTab === 'photos'">

    {{-- Hidden file input — accept attribute driven by the
         general.allowed_upload_formats setting so the picker stays in sync
         with what the server will actually accept. --}}
    <input type="file" id="mediaFileInput"
           accept="{{ implode(',', $mediaUploadConfig['mimes']) }}"
           multiple class="hidden"
           @change="handleFiles($event)">

    {{-- Upload button --}}
    <button type="button" @click="triggerUpload()"
            :disabled="uploading"
            class="w-full flex items-center justify-center gap-2 bg-navy-700 hover:bg-navy-800
                   disabled:opacity-60 text-white font-semibold text-sm rounded-xl py-3.5 mb-5
                   transition-colors">
        <template x-if="!uploading">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
            </svg>
        </template>
        <template x-if="uploading">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </template>
        <span x-text="uploading ? 'Uploading…' : 'Upload Photos or Videos'"></span>
    </button>

    {{-- Upload error --}}
    <div x-show="uploadError"
         x-transition
         class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
        <span x-text="uploadError"></span>
        <button @click="uploadError = null" class="ml-auto text-red-400 hover:text-red-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Grid --}}
    <template x-if="mediaItems.length === 0 && !uploading">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-8 py-16 text-center">
            <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
            </svg>
            <p class="text-sm font-medium text-gray-500 mb-1">No photos or videos yet</p>
            <p class="text-xs text-gray-400">Click Upload to add images or videos for this event</p>
        </div>
    </template>

    <template x-if="mediaItems.length > 0">
        <div>
            {{-- Count bar --}}
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs text-gray-500 font-medium">
                    <span x-text="mediaItems.length"></span>
                    <span x-text="mediaItems.length === 1 ? 'file' : 'files'"></span>
                </p>
                <p class="text-xs text-gray-400">Click an image to preview</p>
            </div>

            {{-- Grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <template x-for="(item, index) in mediaItems" :key="item.id">
                    <div class="relative group bg-gray-100 rounded-xl overflow-hidden aspect-square border border-gray-200 shadow-sm cursor-pointer"
                         @click="item.type === 'document' ? window.open(item.url, '_blank') : openLightbox(index)">

                        {{-- Image thumbnail --}}
                        <template x-if="item.type === 'image'">
                            <img :src="item.url" :alt="item.name"
                                 class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105">
                        </template>

                        {{-- Video thumbnail --}}
                        <template x-if="item.type === 'video'">
                            <div class="w-full h-full bg-gray-900 relative">
                                <video :src="item.url" class="w-full h-full object-cover opacity-80" preload="metadata" muted></video>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="w-10 h-10 rounded-full bg-black/60 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Document tile (PDF) — file-icon + name + size,
                             no preview. Click opens the file in a new tab. --}}
                        <template x-if="item.type === 'document'">
                            <div class="w-full h-full bg-red-50 flex flex-col items-center justify-center p-3 text-center">
                                <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mb-2">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                    </svg>
                                </div>
                                <p class="text-[11px] font-semibold text-red-700 leading-tight line-clamp-2 break-all" x-text="item.name"></p>
                                <p class="text-[10px] text-red-500/80 mt-0.5" x-text="item.size_formatted"></p>
                            </div>
                        </template>

                        {{-- Hover overlay --}}
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors duration-150 pointer-events-none"></div>

                        {{-- Type badge --}}
                        <template x-if="item.type === 'video'">
                            <span class="absolute top-1.5 left-1.5 text-[10px] font-bold uppercase tracking-wide bg-black/60 text-white rounded px-1.5 py-0.5 pointer-events-none">
                                Video
                            </span>
                        </template>
                        <template x-if="item.type === 'document'">
                            <span class="absolute top-1.5 left-1.5 text-[10px] font-bold uppercase tracking-wide bg-red-600 text-white rounded px-1.5 py-0.5 pointer-events-none">
                                PDF
                            </span>
                        </template>

                        {{-- Delete button — always visible, bottom-right --}}
                        <button type="button"
                                @click.stop="confirmDelete(item)"
                                :disabled="deleting === item.id"
                                class="absolute bottom-1.5 right-1.5 w-7 h-7 bg-red-600 hover:bg-red-700
                                       rounded-lg flex items-center justify-center shadow-lg
                                       transition-colors disabled:opacity-50 z-10">
                            <template x-if="deleting !== item.id">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                </svg>
                            </template>
                            <template x-if="deleting === item.id">
                                <svg class="w-3.5 h-3.5 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </template>
                        </button>

                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- ── Preview Modal ────────────────────────────────────────────────────── --}}
    {{-- Overlay (click outside to close) --}}
    <div x-show="lightboxIndex !== null"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="closeLightbox()"
         @keydown.escape.window="closeLightbox()"
         @keydown.arrow-left.window="prevMedia()"
         @keydown.arrow-right.window="nextMedia()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8
                bg-black/60 backdrop-blur-sm"
         style="display:none;">

        {{-- Modal card --}}
        <div x-show="lightboxIndex !== null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.stop
             class="relative bg-white rounded-2xl shadow-2xl ring-1 ring-gray-200
                    flex flex-col w-full max-w-4xl max-h-[90vh] overflow-hidden">

            {{-- ── Card header ──────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 flex-shrink-0">
                {{-- Counter --}}
                <span class="text-gray-400 text-xs font-semibold tabular-nums">
                    <span x-text="lightboxIndex !== null ? lightboxIndex + 1 : ''"></span>
                    &nbsp;/&nbsp;
                    <span x-text="mediaItems.length"></span>
                </span>

                {{-- File name (centre) --}}
                <p class="flex-1 text-gray-700 text-sm font-medium truncate text-center mx-4"
                   x-text="lightboxItem ? lightboxItem.name : ''"></p>

                {{-- Close × --}}
                <button @click="closeLightbox()"
                        class="flex-shrink-0 w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200
                               flex items-center justify-center text-gray-500 hover:text-gray-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- ── Media area ───────────────────────────────────────────────── --}}
            <div class="flex-1 flex items-center justify-center gap-3 px-3 py-4 min-h-0 overflow-hidden bg-gray-100">

                {{-- Prev arrow --}}
                <button @click="prevMedia()"
                        :disabled="lightboxIndex === 0"
                        class="flex-shrink-0 w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200
                               disabled:opacity-20 disabled:cursor-not-allowed
                               flex items-center justify-center text-gray-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </button>

                {{-- Image / Video / Document --}}
                <div class="flex-1 flex items-center justify-center min-w-0 min-h-0">
                    <template x-if="lightboxItem && lightboxItem.type === 'image'">
                        <img :src="lightboxItem.url" :alt="lightboxItem.name"
                             class="max-w-full max-h-[60vh] object-contain rounded-lg shadow-xl select-none">
                    </template>
                    <template x-if="lightboxItem && lightboxItem.type === 'video'">
                        <video :src="lightboxItem.url" controls autoplay
                               class="max-w-full max-h-[60vh] rounded-lg shadow-xl">
                        </video>
                    </template>
                    <template x-if="lightboxItem && lightboxItem.type === 'document'">
                        <div class="flex flex-col items-center justify-center text-center p-8">
                            <div class="w-16 h-16 rounded-2xl bg-red-100 flex items-center justify-center mb-3">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                </svg>
                            </div>
                            <p class="text-base font-semibold text-gray-900 mb-1" x-text="lightboxItem.name"></p>
                            <p class="text-sm text-gray-500 mb-4" x-text="lightboxItem.size_formatted"></p>
                            <a :href="lightboxItem.url" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                </svg>
                                Open / Download
                            </a>
                        </div>
                    </template>
                </div>

                {{-- Next arrow --}}
                <button @click="nextMedia()"
                        :disabled="lightboxIndex === mediaItems.length - 1"
                        class="flex-shrink-0 w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200
                               disabled:opacity-20 disabled:cursor-not-allowed
                               flex items-center justify-center text-gray-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>

            </div>

            {{-- ── Card footer ──────────────────────────────────────────────── --}}
            <div class="flex-shrink-0 flex items-center justify-between gap-3
                        px-4 py-3 border-t border-gray-200 bg-gray-50">

                {{-- File info --}}
                <div class="min-w-0">
                    <p class="text-gray-600 text-xs font-medium truncate"
                       x-text="lightboxItem ? lightboxItem.name : ''"></p>
                    <p class="text-gray-400 text-xs mt-0.5"
                       x-text="lightboxItem ? lightboxItem.size_formatted : ''"></p>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-2 flex-shrink-0">

                    {{-- Cancel / Close --}}
                    <button type="button" @click="closeLightbox()"
                            class="inline-flex items-center gap-1.5 bg-gray-200 hover:bg-gray-300
                                   text-gray-700 text-sm font-semibold rounded-xl px-4 py-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                        Close
                    </button>

                    {{-- Delete --}}
                    <button type="button"
                            @click="lightboxItem && confirmDelete(lightboxItem)"
                            :disabled="lightboxItem && deleting === lightboxItem.id"
                            class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700
                                   disabled:opacity-50 text-white text-sm font-semibold
                                   rounded-xl px-4 py-2 transition-colors">
                        <template x-if="!(lightboxItem && deleting === lightboxItem.id)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                        </template>
                        <template x-if="lightboxItem && deleting === lightboxItem.id">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </template>
                        <span x-text="lightboxItem && deleting === lightboxItem.id ? 'Deleting…' : 'Delete'"></span>
                    </button>

                </div>
            </div>

        </div>
    </div>

    {{-- ── Delete Confirm Modal ─────────────────────────────────────────────── --}}
    <div x-show="deleteTarget !== null"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="deleteTarget = null"
         @keydown.escape.window="deleteTarget = null"
         class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4"
         style="display:none;">

        <div x-show="deleteTarget !== null"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">

            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-bold text-gray-900 mb-1">Delete file?</h3>
                    <p class="text-sm text-gray-500 truncate" x-text="deleteTarget ? deleteTarget.name : ''"></p>
                    <p class="text-xs text-gray-400 mt-1">This cannot be undone.</p>
                </div>
            </div>

            <div class="flex gap-2 mt-5">
                <button @click="deleteTarget = null"
                        class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                    Cancel
                </button>
                <button @click="executeDelete()"
                        :disabled="deleting !== null"
                        class="flex-1 py-2.5 text-sm font-semibold bg-red-600 hover:bg-red-700
                               disabled:opacity-60 text-white rounded-xl transition-colors">
                    <span x-text="deleting ? 'Deleting…' : 'Delete'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

{{-- ── Volunteers Tab ───────────────────────────────────────────────────────── --}}
<div x-show="activeTab === 'volunteers'" style="display:none">
    @include('events.partials._volunteers_tab')
</div>

{{-- ── Queue Access Tab ────────────────────────────────────────────────────── --}}
@if ($event->isCurrent())
<div x-show="activeTab === 'queue'" style="display:none">

    @php
    $roles = [
        ['key' => 'intake',  'label' => 'Intake',          'color' => 'blue',   'code' => $event->intake_auth_code],
        ['key' => 'scanner', 'label' => 'Scanner / Queue',  'color' => 'purple', 'code' => $event->scanner_auth_code],
        ['key' => 'loader',  'label' => 'Loader',           'color' => 'orange', 'code' => $event->loader_auth_code],
        ['key' => 'exit',    'label' => 'Exit',             'color' => 'green',  'code' => $event->exit_auth_code],
    ];
    $colorMap = [
        'blue'   => ['bg' => 'bg-blue-50',   'border' => 'border-blue-200',   'badge' => 'bg-blue-100 text-blue-700',   'btn' => 'bg-blue-600 hover:bg-blue-700'],
        'purple' => ['bg' => 'bg-purple-50', 'border' => 'border-purple-200', 'badge' => 'bg-purple-100 text-purple-700','btn' => 'bg-purple-600 hover:bg-purple-700'],
        'orange' => ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'badge' => 'bg-orange-100 text-orange-700','btn' => 'bg-orange-600 hover:bg-orange-700'],
        'green'  => ['bg' => 'bg-green-50',  'border' => 'border-green-200',  'badge' => 'bg-green-100 text-green-700', 'btn' => 'bg-green-600 hover:bg-green-700'],
    ];
    @endphp

    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-5 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <div>
            <p class="text-sm text-amber-800">These codes grant access to event-day stations. Share them only with staff. Codes are valid while the event is <strong>Today</strong>.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        @foreach ($roles as $r)
        @php $c = $colorMap[$r['color']]; @endphp
        <div class="{{ $c['bg'] }} {{ $c['border'] }} border rounded-2xl p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-bold text-gray-800">{{ $r['label'] }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $c['badge'] }}">Active</span>
            </div>

            <div class="flex gap-2 mb-3">
                @foreach (str_split($r['code']) as $char)
                <div class="flex-1 h-14 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-2xl font-bold text-gray-800 shadow-sm">
                    {{ $char }}
                </div>
                @endforeach
            </div>

            {{-- Station URL --}}
            <div class="mb-3 bg-white/60 rounded-lg px-2.5 py-2 font-mono text-xs text-gray-500 truncate border border-white">
                {{ url('/' . $r['key'] . '/' . $event->id) }}
            </div>
            <a href="{{ route('event-day.' . $r['key'], $event->id) }}"
               target="_blank"
               class="flex items-center gap-1.5 text-xs font-semibold {{ $c['btn'] }} text-white px-3 py-1.5 rounded-lg transition-colors w-fit">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
                Open Station
            </a>
        </div>
        @endforeach
    </div>

    {{-- Regenerate --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-5 flex items-start justify-between gap-4">
        <div>
            <h3 class="text-sm font-bold text-gray-800 mb-1">Regenerate All Codes</h3>
            <p class="text-xs text-gray-500">This will invalidate all current codes. Any staff member already at a station will need to re-enter the new code.</p>
        </div>
        <button type="button" @click="regenOpen = true"
                class="shrink-0 inline-flex items-center gap-2 bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
            </svg>
            Regenerate Codes
        </button>
    </div>

</div>
@endif

{{-- ── Regenerate Codes Modal ───────────────────────────────────────────────── --}}
<div x-show="regenOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="regenOpen = false"
     @keydown.escape.window="regenOpen = false"
     style="display:none;">
    <div x-show="regenOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">

        {{-- Icon --}}
        <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
            </svg>
        </div>

        <h2 class="text-base font-bold text-gray-900 text-center mb-2">Regenerate Access Codes?</h2>
        <p class="text-sm text-gray-500 text-center mb-1">All 4 station codes will be replaced with new ones.</p>
        <p class="text-sm text-amber-700 font-medium text-center bg-amber-50 rounded-lg px-3 py-2 mb-5">
            Anyone currently using a station will be locked out and must enter the new code.
        </p>

        <div class="flex gap-3">
            <button type="button" @click="regenOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form action="{{ route('events.regenerate-codes', $event) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit"
                        class="w-full py-2.5 text-sm font-semibold bg-gray-800 hover:bg-gray-900 text-white rounded-xl transition-colors">
                    Yes, Regenerate
                </button>
            </form>
        </div>

    </div>
</div>

{{-- ── Delete Modal ────────────────────────────────────────────────────────── --}}
<div x-show="deleteOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="deleteOpen = false">
    <div x-show="deleteOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </div>
        <h2 class="text-base font-bold text-gray-900 mb-2">Delete Event</h2>
        <p class="text-sm text-gray-500 mb-6">Delete <strong class="text-gray-700">{{ $event->name }}</strong>? This cannot be undone.</p>
        <div class="flex gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form action="{{ route('events.destroy', $event) }}" method="POST" class="flex-1">
                @csrf @method('DELETE')
                <button class="w-full py-2.5 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">Delete</button>
            </form>
        </div>
    </div>
</div>

{{-- ── Public Registration Link Modal ─────────────────────────────────────── --}}
<div x-show="regLinkOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="regLinkOpen = false">
    <div x-show="regLinkOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-gray-900">Public Registration Link</h2>
            <button @click="regLinkOpen = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <p class="text-sm text-gray-500 mb-4">Share this link so households can pre-register for <strong>{{ $event->name }}</strong>.</p>
        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200">
            <span class="flex-1 text-xs text-gray-600 break-all font-mono">{{ $publicUrl }}</span>
            <button type="button" @click="copyLink()"
                    :class="copied ? 'bg-green-100 text-green-700' : 'bg-navy-700 text-white hover:bg-navy-800'"
                    class="flex-shrink-0 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
            </button>
        </div>
        <div class="mt-3">
            <a href="{{ $publicUrl }}" target="_blank"
               class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">
                Open registration page →
            </a>
        </div>
    </div>
</div>

{{-- ── Inventory Tab ───────────────────────────────────────────────────────── --}}
<div x-show="activeTab === 'inventory'" style="display:none">

    @php
        $allocations   = $event->inventoryAllocations;
        $totalAlloc    = $allocations->sum('allocated_quantity');
        $totalDist     = $allocations->sum('distributed_quantity');
        $totalReturned = $allocations->sum('returned_quantity');
        $totalRemaining= $allocations->sum(fn($a) => $a->remainingQuantity());
    @endphp

    {{-- Flash errors for allocation actions --}}
    @if (session('alloc_error'))
    <div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
        {{ session('alloc_error') }}
    </div>
    @endif

    {{-- Phase D — bulk allocation summary warning. Listed rows that got
         skipped due to insufficient stock / no existing alloc / replace-down
         floor surface here, line by line. --}}
    @if (session('alloc_warning'))
    <div class="mb-4 flex items-start gap-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.732 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
        <pre class="whitespace-pre-wrap font-sans">{{ session('alloc_warning') }}</pre>
    </div>
    @endif

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 text-center">
            <p class="text-2xl font-black text-gray-900">{{ $allocations->count() }}</p>
            <p class="text-xs text-gray-400 mt-1 font-medium uppercase tracking-wide">Items</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 text-center">
            <p class="text-2xl font-black text-gray-900">{{ number_format($totalAlloc) }}</p>
            <p class="text-xs text-gray-400 mt-1 font-medium uppercase tracking-wide">Allocated</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 text-center">
            <p class="text-2xl font-black text-green-600">{{ number_format($totalDist) }}</p>
            <p class="text-xs text-gray-400 mt-1 font-medium uppercase tracking-wide">Distributed</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 text-center">
            <p class="text-2xl font-black text-blue-600">{{ number_format($totalReturned) }}</p>
            <p class="text-xs text-gray-400 mt-1 font-medium uppercase tracking-wide">Returned</p>
        </div>
    </div>

    {{-- Main card --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        {{-- Toolbar --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Inventory Allocations</h2>
            @if (!$event->isLocked())
            <div class="flex items-center gap-2">
                {{-- Phase D — desktop-only bulk drawer trigger. Mobile keeps
                     the single-add path so this button hides under lg. --}}
                <button type="button"
                        @click="openBulkAllocate()"
                        class="hidden lg:inline-flex items-center gap-1.5 bg-white border border-brand-500 text-brand-600
                               hover:bg-brand-50 font-semibold text-xs rounded-lg px-3 py-2 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>
                    </svg>
                    Bulk Allocate
                </button>
                <button @click="openInvModal('allocate')"
                        class="inline-flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white
                               font-semibold text-xs rounded-lg px-3 py-2 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Allocate Item
                </button>
            </div>
            @endif
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Allocated</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Distributed</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Returned</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Remaining</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($allocations as $alloc)
                    @php $remaining = $alloc->remainingQuantity(); @endphp
                    <tr class="hover:bg-gray-50/60 transition-colors group">
                        {{-- Item --}}
                        <td class="px-5 py-3.5">
                            <a href="{{ route('inventory.items.show', $alloc->item) }}"
                               class="font-semibold text-gray-800 hover:text-brand-600 transition-colors">
                                {{ $alloc->item->name }}
                            </a>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $alloc->item->category?->name ?? 'Uncategorized' }}
                                &middot; {{ $alloc->item->unit_type }}
                            </p>
                            @if ($alloc->notes)
                                <p class="text-xs text-gray-400 italic mt-0.5">"{{ $alloc->notes }}"</p>
                            @endif
                        </td>
                        {{-- Allocated --}}
                        <td class="px-4 py-3.5 text-right tabular-nums">
                            <span class="font-semibold text-gray-800">{{ number_format($alloc->allocated_quantity) }}</span>
                        </td>
                        {{-- Distributed --}}
                        <td class="px-4 py-3.5 text-right tabular-nums">
                            <span class="font-semibold text-green-700">{{ number_format($alloc->distributed_quantity) }}</span>
                        </td>
                        {{-- Returned --}}
                        <td class="px-4 py-3.5 text-right tabular-nums">
                            <span class="font-semibold text-blue-600">{{ number_format($alloc->returned_quantity) }}</span>
                        </td>
                        {{-- Remaining --}}
                        <td class="px-4 py-3.5 text-right tabular-nums">
                            <span class="font-semibold {{ $remaining > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                                {{ number_format($remaining) }}
                            </span>
                        </td>
                        {{-- Actions --}}
                        <td class="px-4 py-3.5 text-right">
                            @if (!$event->isLocked())
                            <div class="flex items-center justify-end gap-1">
                                {{-- Update distributed --}}
                                <button @click="openInvModal('distributed', {{ $alloc->id }}, {{ $alloc->allocated_quantity - $alloc->returned_quantity }}, '{{ addslashes($alloc->item->unit_type) }}')"
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1.5 rounded-lg
                                               bg-green-50 text-green-700 hover:bg-green-100 transition-colors"
                                        title="Update Distributed">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                                    Distributed
                                </button>
                                {{-- Return --}}
                                @if ($alloc->canReturn())
                                <button @click="openInvModal('return', {{ $alloc->id }}, {{ $remaining }}, '{{ addslashes($alloc->item->unit_type) }}')"
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1.5 rounded-lg
                                               bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                                        title="Return to Inventory">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                    Return
                                </button>
                                @endif
                                {{-- Delete --}}
                                @if ($alloc->distributed_quantity === 0 && $alloc->returned_quantity === 0)
                                <form method="POST" action="{{ route('events.inventory.destroy', [$event, $alloc]) }}"
                                      onsubmit="return confirm('Remove this allocation? The stock will be restored.')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400
                                                   hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Remove Allocation">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                            @else
                                <span class="text-xs text-gray-400">Locked</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-14 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <svg class="w-9 h-9 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-500">No inventory allocated yet</p>
                                @if (!$event->isLocked())
                                    <button @click="openInvModal('allocate')"
                                            class="text-xs text-brand-500 hover:underline font-semibold mt-1">
                                        Allocate your first item →
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ INVENTORY MODALS ════════════════════════════════════════════════ --}}

    {{-- Shared backdrop --}}
    <div x-show="invModal !== null"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-40"
         @click="closeInvModal()"
         style="display:none">
    </div>

    {{-- ── Allocate Item modal ─────────────────────────────────────────── --}}
    <div x-show="invModal === 'allocate'"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display:none"
         @click.self="closeInvModal()">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-brand-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-bold text-gray-900">Allocate Inventory Item</h2>
                </div>
                <button @click="closeInvModal()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('events.inventory.store', $event) }}">
                @csrf
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Item <span class="text-red-500">*</span>
                        </label>
                        <select name="inventory_item_id" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 cursor-pointer
                                       focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                            <option value="">— Select item —</option>
                            @foreach ($inventoryItems->groupBy(fn($i) => $i->category?->name ?? 'Uncategorized') as $catName => $catItems)
                                <optgroup label="{{ $catName }}">
                                    @foreach ($catItems as $invItem)
                                        <option value="{{ $invItem->id }}">
                                            {{ $invItem->name }} — {{ number_format($invItem->quantity_on_hand) }} {{ $invItem->unit_type }} on hand
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Quantity to Allocate <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="allocated_quantity" min="1" step="1" required
                               placeholder="0"
                               class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Notes</label>
                        <textarea name="notes" rows="2"
                                  placeholder="Optional notes..."
                                  class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 resize-none
                                         focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400"></textarea>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 text-xs text-amber-700">
                        Stock will be immediately deducted from inventory when you allocate.
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" @click="closeInvModal()"
                            class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-brand-500 hover:bg-brand-600
                                   text-white text-sm font-semibold rounded-lg transition-colors">
                        Confirm Allocation
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Update Distributed modal ────────────────────────────────────── --}}
    <div x-show="invModal === 'distributed'"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display:none"
         @click.self="closeInvModal()">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-green-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-bold text-gray-900">Update Distributed</h2>
                </div>
                <button @click="closeInvModal()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            {{-- Dynamic form: action built from invAllocId --}}
            <template x-for="alloc in [invAllocId]" :key="alloc">
                <form method="POST"
                      :action="`{{ url('events/' . $event->id . '/inventory/') }}/${alloc}/distributed`">
                    @csrf @method('PATCH')
                    <div class="px-6 py-5 space-y-4">
                        <p class="text-sm text-gray-500">
                            Enter the total number of units distributed to households
                            (max: <span class="font-semibold text-gray-800" x-text="invAllocMax + ' ' + invAllocUnit"></span>).
                        </p>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Distributed Quantity <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="distributed_quantity" min="0" :max="invAllocMax" step="1" required
                                       placeholder="0"
                                       class="flex-1 px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50
                                              focus:outline-none focus:ring-2 focus:ring-green-500/20 focus:border-green-400">
                                <span class="text-sm text-gray-500 font-medium" x-text="invAllocUnit"></span>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                        <button type="button" @click="closeInvModal()"
                                class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 hover:bg-green-700
                                       text-white text-sm font-semibold rounded-lg transition-colors">
                            Save
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    {{-- ── Return Stock modal ──────────────────────────────────────────── --}}
    <div x-show="invModal === 'return'"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display:none"
         @click.self="closeInvModal()">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-bold text-gray-900">Return to Inventory</h2>
                </div>
                <button @click="closeInvModal()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <template x-for="alloc in [invAllocId]" :key="alloc">
                <form method="POST"
                      :action="`{{ url('events/' . $event->id . '/inventory/') }}/${alloc}/return`">
                    @csrf
                    <div class="px-6 py-5 space-y-4">
                        <p class="text-sm text-gray-500">
                            Remaining: <span class="font-semibold text-gray-800" x-text="invAllocMax + ' ' + invAllocUnit"></span>.
                            Returned stock goes back to the inventory shelf.
                        </p>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                                Quantity to Return <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="return_quantity" min="1" :max="invAllocMax" step="1" required
                                       placeholder="0"
                                       class="flex-1 px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50
                                              focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400">
                                <span class="text-sm text-gray-500 font-medium" x-text="invAllocUnit"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Notes</label>
                            <textarea name="notes" rows="2"
                                      placeholder="e.g. 5 bags remaining after event..."
                                      class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 resize-none
                                             focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                        <button type="button" @click="closeInvModal()"
                                class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700
                                       text-white text-sm font-semibold rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>
                            </svg>
                            Confirm Return
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    {{-- ── Phase D — Bulk Allocate Drawer ───────────────────────────────────
         Wide centered modal: search box, scrollable item table with running
         per-row qty input, mode selector (add / subtract / replace), sticky
         footer with running totals. Desktop-only (the trigger button is
         hidden under lg). Submits as a single POST to events.inventory.bulk.
         Each item row carries its own pre-existing allocation as initial
         data so the operator sees what's already there at a glance. --}}
    @if (!$event->isLocked())
        @php
            $existingAllocByItem = $event->inventoryAllocations->keyBy('inventory_item_id');
        @endphp

        <div x-show="bulkOpen"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-40"
             @click="bulkOpen = false"
             style="display:none">
        </div>

        <div x-show="bulkOpen"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="display:none"
             @click.self="bulkOpen = false"
             @keydown.escape.window="bulkOpen = false">
            <div class="w-full max-w-4xl max-h-[90vh] bg-white rounded-2xl shadow-2xl flex flex-col" @click.stop>

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-brand-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>
                            </svg>
                        </div>
                        <h2 class="text-base font-bold text-gray-900">Bulk Allocate Inventory</h2>
                    </div>
                    <button @click="bulkOpen = false" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('events.inventory.bulk', $event) }}" class="flex-1 flex flex-col min-h-0">
                    @csrf

                    {{-- Search + mode --}}
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3 flex-wrap">
                        <div class="relative flex-1 min-w-[200px]">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                            <input type="text" x-model="bulkSearch" placeholder="Search items..."
                                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                        </div>
                        <fieldset class="flex items-center gap-1 text-xs">
                            <legend class="sr-only">Mode for items already allocated</legend>
                            <span class="text-gray-500 mr-2">When already allocated:</span>
                            <template x-for="opt in [{val:'add', label:'Add'}, {val:'subtract', label:'Subtract'}, {val:'replace', label:'Replace'}]" :key="opt.val">
                                <label class="cursor-pointer">
                                    <input type="radio" name="mode" :value="opt.val" x-model="bulkMode" class="sr-only peer">
                                    <span class="px-3 py-1.5 rounded-lg font-semibold border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 peer-checked:bg-brand-50 peer-checked:border-brand-400 peer-checked:text-brand-700 transition-colors"
                                          x-text="opt.label"></span>
                                </label>
                            </template>
                        </fieldset>
                    </div>

                    {{-- Scrollable table --}}
                    <div class="flex-1 overflow-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white z-10 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">On Hand</th>
                                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Reorder</th>
                                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Already Alloc</th>
                                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide w-32">Quantity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($inventoryItems as $i => $item)
                                    @php
                                        $existing  = $existingAllocByItem->get($item->id);
                                        $alreadyQty = $existing?->allocated_quantity ?? 0;
                                    @endphp
                                    <tr data-bulk-row
                                        data-item-name="{{ strtolower($item->name) }}"
                                        x-show="bulkRowMatches('{{ strtolower(addslashes($item->name)) }}')"
                                        class="hover:bg-gray-50/60 transition-colors">
                                        <td class="px-5 py-2.5">
                                            <input type="hidden" name="items[{{ $i }}][inventory_item_id]" value="{{ $item->id }}">
                                            <p class="font-semibold text-gray-800">{{ $item->name }}</p>
                                            <p class="text-xs text-gray-400">{{ $item->category?->name ?? 'Uncategorized' }} · {{ $item->unit_type }}</p>
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums">
                                            <span class="font-semibold {{ $item->stockStatus() === 'out' ? 'text-red-600' : ($item->stockStatus() === 'low' ? 'text-amber-600' : 'text-gray-800') }}">
                                                {{ number_format($item->quantity_on_hand) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-500">{{ number_format($item->reorder_level) }}</td>
                                        <td class="px-4 py-2.5 text-right tabular-nums">
                                            @if ($alreadyQty > 0)
                                                <span class="font-semibold text-blue-700">{{ number_format($alreadyQty) }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number" min="0" step="1"
                                                   name="items[{{ $i }}][allocated_quantity]"
                                                   value="0"
                                                   data-item-qty
                                                   @input="recalcBulkTotals()"
                                                   class="w-24 px-2 py-1 text-sm text-right border border-gray-200 rounded-lg bg-gray-50
                                                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 tabular-nums">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if ($inventoryItems->isEmpty())
                            <div class="text-center py-12 text-gray-400 text-sm">
                                No active inventory items. <a href="{{ route('inventory.items.create') }}" class="text-brand-600 hover:underline font-semibold">Add one →</a>
                            </div>
                        @endif
                    </div>

                    {{-- Notes + sticky footer --}}
                    <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/40 flex-shrink-0">
                        <input type="text" name="notes" placeholder="Optional batch note (applied to every line)"
                               class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-3 flex-shrink-0">
                        <p class="text-xs text-gray-500">
                            <span class="font-semibold text-gray-800 tabular-nums" x-text="bulkSelectedCount"></span> item(s) selected,
                            <span class="font-semibold text-gray-800 tabular-nums" x-text="bulkTotalUnits"></span> total units
                        </p>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="bulkOpen = false"
                                    class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="bulkSelectedCount === 0"
                                    class="inline-flex items-center gap-2 px-5 py-2 bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 disabled:cursor-not-allowed
                                           text-white text-sm font-semibold rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                Apply Allocation
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>

{{-- ── Reviews Tab ─────────────────────────────────────────────────────────── --}}
<div x-show="activeTab === 'reviews'">

    @php
        $reviewCount = $event->reviews->count();
        $avgRating   = $reviewCount > 0 ? round($event->reviews->avg('rating'), 1) : null;
    @endphp

    {{-- Summary row --}}
    <div class="grid grid-cols-2 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5 text-center">
            <p class="text-3xl font-black text-gray-900">{{ $reviewCount }}</p>
            <p class="text-sm text-gray-400 mt-1">Total Reviews</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5 text-center">
            <p class="text-3xl font-black text-gray-900">
                {{ $avgRating ?? '—' }}
                @if ($avgRating)
                    <span class="text-yellow-400 text-2xl">★</span>
                @endif
            </p>
            <p class="text-sm text-gray-400 mt-1">Average Rating</p>
        </div>
    </div>

    @if ($event->reviews->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-12 text-center">
            <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700 mb-1">No reviews yet</p>
            <p class="text-xs text-gray-400 mb-4">Reviews submitted by attendees will appear here.</p>
            <a href="{{ route('public.reviews.create') }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:text-brand-600 transition-colors">
                Open review form
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
            </a>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-gray-50/60">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    Latest Reviews
                </p>
                <a href="{{ route('reviews.index', ['event_id' => $event->id]) }}"
                   class="text-xs font-semibold text-brand-500 hover:text-brand-600 transition-colors">
                    Manage all reviews →
                </a>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($event->reviews->take(10) as $review)
                    <div class="px-5 py-4">
                        {{-- Stars --}}
                        <div class="flex items-center gap-1 mb-2">
                            @for ($i = 1; $i <= 5; $i++)
                                <span class="text-base leading-none {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-200' }}">★</span>
                            @endfor
                        </div>
                        {{-- Text --}}
                        <p class="text-sm text-gray-700 leading-relaxed mb-2">{{ $review->review_text }}</p>
                        {{-- Meta --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-400">
                            @if ($review->reviewer_name)
                                <span class="font-medium text-gray-500">{{ $review->reviewer_name }}</span>
                            @endif
                            @if ($review->email)
                                <span>{{ $review->email }}</span>
                            @endif
                            <span>{{ $review->created_at->format('M j, Y') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($reviewCount > 10)
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 text-center">
                    <a href="{{ route('reviews.index', ['event_id' => $event->id]) }}"
                       class="text-xs font-semibold text-brand-500 hover:text-brand-600 transition-colors">
                        View all {{ $reviewCount }} reviews →
                    </a>
                </div>
            @endif

        </div>
    @endif

</div>

{{-- ── Finance Tab ──────────────────────────────────────────────────────────── --}}
<div x-show="activeTab === 'finance'" style="display:none">
    @include('events.partials._finance_tab')
</div>

</div>

@push('scripts')
<script>
function statusBtn(url, currentStatus) {
    const nextFor = { current: 'past', past: 'undo' };
    return {
        loading: false,
        async advance() {
            if (this.loading) return;
            this.loading = true;
            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: nextFor[currentStatus] }),
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.error ?? 'Could not update status.');
                    this.loading = false;
                }
            } catch {
                alert('Network error. Please try again.');
                this.loading = false;
            }
        },
    };
}

function eventShow() {
    return {
        activeTab:   @json(session('open_tab', 'details')),
        deleteOpen:  false,

        // ── Attendee delete confirmation ─────────────────────────────────
        // Replaces the previous browser confirm() dialog with an in-page
        // modal. Each row's delete button calls openAttendeeDelete(...)
        // with the resolved name + form action; the single modal at the
        // bottom of the attendees tab does the actual POST.
        attendeeDelete: { show: false, name: '', url: '' },
        openAttendeeDelete(name, url) {
            this.attendeeDelete = { show: true, name: name, url: url };
        },
        closeAttendeeDelete() {
            this.attendeeDelete = { show: false, name: '', url: '' };
        },

        // ── Volunteer admin check-in / checkout / bulk ───────────────────
        // Modal 1: per-volunteer check-in with a time picker.
        // Modal 2: per-checkin checkout with a time picker.
        // Modal 3: bulk check-in confirmation.
        volCheckIn:      { show: false, volunteerId: null, volunteerName: '', defaultRole: '', time: '' },
        volCheckout:     { show: false, checkInId: null, volunteerName: '', checkedInAt: '', time: '' },
        volBulk:         { show: false, count: 0 },
        volBulkCheckout: { show: false, count: 0 },

        openVolCheckIn(volunteerId, volunteerName, defaultRole) {
            this.volCheckIn = {
                show:          true,
                volunteerId:   volunteerId,
                volunteerName: volunteerName,
                defaultRole:   defaultRole || '',
                time:          this.localNow(),  // datetime-local format
            };
        },
        closeVolCheckIn() {
            this.volCheckIn = { show: false, volunteerId: null, volunteerName: '', defaultRole: '', time: '' };
        },
        openVolCheckout(checkInId, volunteerName, checkedInAt) {
            this.volCheckout = {
                show:          true,
                checkInId:     checkInId,
                volunteerName: volunteerName,
                checkedInAt:   checkedInAt || '',
                time:          this.localNow(),
            };
        },
        closeVolCheckout() {
            this.volCheckout = { show: false, checkInId: null, volunteerName: '', checkedInAt: '', time: '' };
        },
        openVolBulk(count) {
            this.volBulk = { show: true, count: count };
        },
        closeVolBulk() {
            this.volBulk = { show: false, count: 0 };
        },
        openVolBulkCheckout(count) {
            this.volBulkCheckout = { show: true, count: count };
        },
        closeVolBulkCheckout() {
            this.volBulkCheckout = { show: false, count: 0 };
        },

        // Helper — produce a string suitable for <input type="datetime-local">
        // matching the user's local clock. PHP-side parsing is tolerant of
        // either local or ISO so we don't need to reconcile timezones here.
        localNow() {
            const d = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        },

        // ── Inventory ─────────────────────────────────────────────────────
        invModal:    null,
        invAllocId:  null,
        invAllocMax: 0,
        invAllocUnit: '',
        openInvModal(name, allocId = null, max = 0, unit = '') {
            this.invModal    = name;
            this.invAllocId  = allocId;
            this.invAllocMax = max;
            this.invAllocUnit = unit;
        },
        closeInvModal() { this.invModal = null; this.invAllocId = null; },

        // ── Phase D — bulk allocate drawer ────────────────────────────────
        bulkOpen:           false,
        bulkSearch:         '',
        bulkMode:           'add',
        bulkSelectedCount:  0,
        bulkTotalUnits:     0,
        openBulkAllocate() {
            this.bulkOpen          = true;
            this.bulkSearch        = '';
            this.bulkMode          = 'add';
            this.bulkSelectedCount = 0;
            this.bulkTotalUnits    = 0;
            // Reset every qty input to 0 — the markup renders with value="0",
            // but the form may have been populated by a previous open.
            this.$nextTick(() => {
                document.querySelectorAll('[data-item-qty]').forEach(el => { el.value = 0; });
            });
        },
        bulkRowMatches(itemNameLower) {
            const q = this.bulkSearch.trim().toLowerCase();
            return q === '' || itemNameLower.includes(q);
        },
        recalcBulkTotals() {
            let count = 0, units = 0;
            document.querySelectorAll('[data-item-qty]').forEach(el => {
                const n = parseInt(el.value || '0', 10);
                if (n > 0) { count++; units += n; }
            });
            this.bulkSelectedCount = count;
            this.bulkTotalUnits    = units;
        },

        regLinkOpen: false,
        regenOpen:   false,
        copied:     false,
        volSearch:  '',
        removing:   null,
        volunteers: @json($assignedVolsMapped),
        detachBase: @json($detachUrlBase),

        // ── Media ──────────────────────────────────────────────────────────
        mediaItems:    @json($mediaMapped),
        uploadUrl:     '{{ route('events.media.store', $event) }}',
        deleteBase:    '{{ url("events/{$event->id}/media") }}',
        uploadMaxMb:   {{ (int) $mediaUploadConfig['max_mb'] }},
        uploading:     false,
        uploadError:   null,
        deleting:      null,
        lightboxIndex: null,
        deleteTarget:  null,

        get lightboxItem() {
            return this.lightboxIndex !== null ? this.mediaItems[this.lightboxIndex] ?? null : null;
        },

        triggerUpload() {
            document.getElementById('mediaFileInput').value = '';
            document.getElementById('mediaFileInput').click();
        },

        async handleFiles(event) {
            const files = [...event.target.files];
            if (!files.length) return;
            this.uploadError = null;
            this.uploading   = true;

            for (const file of files) {
                // Resolve a useful human message for whatever went wrong, even
                // when the response isn't JSON (session expired returns the
                // login HTML; PHP post_max_size violations return an empty
                // body; a 500 returns the Laravel error page). The previous
                // handler called res.json() unconditionally, threw on parse,
                // and surfaced "Network error" for every cause. This branch
                // table maps every realistic failure mode to a copy that
                // tells the operator what to actually do.
                let res, rawText, parsed = null;
                try {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                    res = await fetch(this.uploadUrl, {
                        method: 'POST',
                        body:    formData,
                        headers: {
                            // Force Laravel to negotiate JSON for redirects /
                            // validation errors instead of HTML pages, and
                            // mark the request as XHR so middleware behaves.
                            'Accept':           'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                } catch (networkErr) {
                    // True network-level failure (DNS, offline, CORS, abort).
                    this.uploadError = 'Could not reach the server. Check your internet connection and try again.';
                    break;
                }

                // Read the body once as text so we can both try-parse JSON
                // and have something to fall back on if parse fails.
                try { rawText = await res.text(); } catch { rawText = ''; }
                if (rawText) {
                    try { parsed = JSON.parse(rawText); } catch { parsed = null; }
                }

                if (res.ok) {
                    if (parsed && parsed.media) {
                        this.mediaItems.push(parsed.media);
                        continue;
                    }
                    // 2xx but unparseable response — defensive fallback so
                    // we don't push an undefined media row.
                    this.uploadError = 'Upload succeeded but the server response was unexpected. Refresh to verify.';
                    break;
                }

                // Map common HTTP status codes to actionable messages.
                this.uploadError = this.uploadErrorFor(res.status, file, parsed);
                break;
            }

            this.uploading = false;
        },

        uploadErrorFor(status, file, body) {
            const fileLabel = file?.name ? `"${file.name}"` : 'this file';
            switch (status) {
                case 401:
                case 419:
                    // 401 = Laravel auth(); 419 = CSRF token mismatch / page expired.
                    return 'Your session has expired. Refresh the page and sign in again before retrying.';
                case 413:
                    // PHP refused the upload at the server level (post_max_size).
                    return `${fileLabel} is too large. The current limit is ${this.uploadMaxMb}MB. Either upload a smaller file, or — if your admin has raised the limit in settings — also raise PHP \`upload_max_filesize\` and \`post_max_size\` to match.`;
                case 422: {
                    // Validation error — surface Laravel's per-field message.
                    const fieldErr = body?.errors?.file?.[0] || body?.message;
                    return fieldErr || `${fileLabel} did not pass validation.`;
                }
                case 429:
                    return 'Too many uploads in a short time. Wait a moment and try again.';
                case 500:
                case 502:
                case 503:
                case 504:
                    return 'The server hit an error while saving the file. Try again in a moment; if it persists, ask your admin to check the application log.';
                default: {
                    const msg = body?.message || body?.errors?.file?.[0];
                    return msg || `Upload failed (HTTP ${status}). Try a different file or refresh the page.`;
                }
            }
        },

        openLightbox(index) {
            this.lightboxIndex = index;
        },

        closeLightbox() {
            this.lightboxIndex = null;
        },

        prevMedia() {
            if (this.lightboxIndex > 0) this.lightboxIndex--;
        },

        nextMedia() {
            if (this.lightboxIndex < this.mediaItems.length - 1) this.lightboxIndex++;
        },

        confirmDelete(item) {
            this.deleteTarget = item;
        },

        async executeDelete() {
            if (!this.deleteTarget || this.deleting) return;
            const id = this.deleteTarget.id;
            this.deleting = id;

            try {
                const res = await fetch(`${this.deleteBase}/${id}`, {
                    method:  'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                });
                if (res.ok) {
                    const removedIndex = this.mediaItems.findIndex(m => m.id === id);
                    this.mediaItems = this.mediaItems.filter(m => m.id !== id);
                    this.deleteTarget = null;
                    // adjust lightbox index after removal
                    if (this.lightboxIndex !== null) {
                        if (this.mediaItems.length === 0) {
                            this.lightboxIndex = null;
                        } else if (removedIndex <= this.lightboxIndex) {
                            this.lightboxIndex = Math.max(0, this.lightboxIndex - 1);
                        }
                    }
                }
            } catch (e) {
                // silently ignore
            } finally {
                this.deleting = null;
            }
        },

        get filteredVolunteers() {
            const q = this.volSearch.toLowerCase().trim();
            if (!q) return this.volunteers;
            return this.volunteers.filter(v =>
                v.name.toLowerCase().includes(q) ||
                (v.role && v.role.toLowerCase().includes(q))
            );
        },

        async removeVolunteer(id) {
            if (this.removing) return;
            this.removing = id;
            try {
                const res = await fetch(`${this.detachBase}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                });
                if (res.ok) {
                    this.volunteers = this.volunteers.filter(v => v.id !== id);
                }
            } catch (e) {
                // silently ignore network errors; state is unchanged
            } finally {
                this.removing = null;
            }
        },

        copyLink() {
            navigator.clipboard.writeText('{{ $publicUrl }}').then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        },
    };
}
</script>
@endpush

@endsection
