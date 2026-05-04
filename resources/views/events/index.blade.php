@extends('layouts.app')
@section('title', 'Event Management')

@section('content')
<div x-data="eventIndex()">

{{-- Header --}}
<div class="flex items-start justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Event Management</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Event Management</span>
        </nav>
    </div>
    <a href="{{ route('events.create') }}"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-lg px-4 py-2.5 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Create Event
    </a>
</div>

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Tabs + Search row --}}
<div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">

    {{-- Tab filters --}}
    <div class="flex rounded-lg border border-gray-300 overflow-hidden text-sm font-semibold flex-shrink-0">
        <a href="{{ route('events.index', array_merge(request()->except('filter', 'page'), ['filter' => 'upcoming'])) }}"
           class="px-4 py-2 transition-colors {{ $filter === 'upcoming' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
            Upcoming ({{ $upcomingCount }})
        </a>
        <a href="{{ route('events.index', array_merge(request()->except('filter', 'page'), ['filter' => 'current'])) }}"
           class="px-4 py-2 border-l border-gray-300 transition-colors {{ $filter === 'current' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
            Today ({{ $currentCount }})
        </a>
        <a href="{{ route('events.index', array_merge(request()->except('filter', 'page'), ['filter' => 'past'])) }}"
           class="px-4 py-2 border-l border-gray-300 transition-colors {{ $filter === 'past' ? 'bg-navy-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
            Past ({{ $pastCount }})
        </a>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('events.index') }}" class="flex gap-2 flex-1">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search events..."
               class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                      focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                      placeholder:text-gray-400">
        <button type="submit"
                class="px-4 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
            Search
        </button>
        @if (request('search'))
            <a href="{{ route('events.index', ['filter' => $filter]) }}"
               class="px-4 py-2 text-sm font-semibold border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
                Clear
            </a>
        @endif
    </form>
</div>

{{-- Event List --}}
@if ($events->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-8 py-16 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
        </svg>
        <p class="text-sm font-medium text-gray-500 mb-2">
            No {{ match($filter) { 'past' => 'past', 'current' => "today's", default => 'upcoming' } }} events found
        </p>
        @if ($filter === 'upcoming')
            <a href="{{ route('events.create') }}" class="text-sm text-brand-600 hover:text-brand-700 font-semibold">
                Create the first event
            </a>
        @endif
    </div>
@else
    <div class="space-y-3">
        @foreach ($events as $event)
            {{-- Entire card is clickable — action buttons stop propagation --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden cursor-pointer hover:shadow-md transition-shadow"
                 @click="window.location.href = '{{ route('events.show', $event) }}'">

                {{-- Top row: name + actions --}}
                <div class="flex items-start justify-between px-6 pt-5 pb-3">
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-lg font-bold text-gray-900">{{ $event->name }}</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $event->statusBadgeClasses() }}">
                                {{ $event->statusLabel() }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-sm text-gray-500">
                            {{-- Date --}}
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                {{ $event->date->format('D, M j, Y') }}
                            </span>
                            {{-- Location --}}
                            @if ($event->location)
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                                    {{ $event->location }}
                                </span>
                            @endif
                            {{-- Assigned Group --}}
                            <span class="flex items-center gap-1.5 text-gray-500">
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

                    {{-- Action icons — @click.stop prevents card navigation --}}
                    <div class="flex items-center gap-1 flex-shrink-0 ml-4" @click.stop>

                        {{-- Mark Complete icon (current only) → opens confirm modal --}}
                        @if ($event->isCurrent())
                        <button type="button"
                                @click="openComplete('{{ addslashes($event->name) }}', '{{ route('events.status', $event) }}')"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors"
                                title="Mark Complete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        </button>
                        @endif

                        {{-- Undo Complete icon (past only) → direct AJAX, no confirm --}}
                        @if ($event->isLocked())
                        <button type="button"
                                @click="undoComplete('{{ route('events.status', $event) }}')"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-amber-500 hover:bg-amber-50 transition-colors"
                                title="Undo Complete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                        </button>
                        @endif

                        {{-- View --}}
                        <a href="{{ route('events.show', $event) }}"
                           class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                           title="View">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        </a>

                        {{-- Public registration link --}}
                        <div x-data="{ open: false, copied: false }" class="relative">
                            <button type="button" @click="open = !open"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                                    title="Public registration link">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                            </button>
                            {{-- Dropdown --}}
                            <div x-show="open" @click.outside="open = false"
                                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute right-0 top-9 z-30 w-80 bg-white rounded-xl border border-gray-200 shadow-lg p-4"
                                 style="display:none;">
                                <p class="text-xs font-semibold text-gray-700 mb-2">Public Registration Link</p>
                                <div class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-lg border border-gray-200">
                                    <span class="flex-1 text-xs text-gray-500 break-all font-mono">{{ route('public.register', $event) }}</span>
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ route('public.register', $event) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            :class="copied ? 'bg-green-100 text-green-700' : 'bg-navy-700 text-white hover:bg-navy-800'"
                                            class="flex-shrink-0 px-2.5 py-1 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                                        <span x-text="copied ? '✓ Copied' : 'Copy'"></span>
                                    </button>
                                </div>
                                <a href="{{ route('public.register', $event) }}" target="_blank"
                                   class="mt-2 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">
                                    Open page →
                                </a>
                            </div>
                        </div>

                        @unless ($event->isLocked())
                        <a href="{{ route('events.edit', $event) }}"
                           class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                           title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                        </a>
                        <button type="button"
                                @click="openDelete({{ $event->id }}, '{{ addslashes($event->name) }}')"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                                title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                        @endunless
                    </div>
                </div>

                {{-- Stats row --}}
                <div class="grid grid-cols-3 divide-x divide-gray-100 border-t border-gray-100 mx-6 mb-5">
                    <div class="pt-4 pr-6 text-center">
                        <p class="text-2xl font-black text-gray-900">{{ $event->pre_registrations_count }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Pre-registered Households</p>
                    </div>
                    <div class="pt-4 px-6 text-center">
                        <p class="text-2xl font-black text-gray-900">{{ $event->assigned_volunteers_count }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Volunteers</p>
                    </div>
                    <div class="pt-4 pl-6 text-center">
                        @if($event->ruleset)
                            <button type="button"
                                    @click.stop="openRulesetPreview({{ $event->id }})"
                                    class="text-base font-bold text-brand-600 hover:text-brand-700 hover:underline underline-offset-2 transition-colors leading-tight">
                                {{ $event->ruleset->name }}
                            </button>
                        @else
                            <p class="text-lg font-bold text-gray-300">—</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-0.5">Ruleset</p>
                    </div>
                </div>

            </div>
        @endforeach
    </div>

    @if ($events->hasPages())
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
@endif

{{-- Mark Complete Confirmation Modal --}}
<div x-show="completeOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="completeOpen = false" style="display:none;">
    <div x-show="completeOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center">
        <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        </div>
        <h2 class="text-base font-bold text-gray-900 mb-2">Mark Event Complete</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Mark <strong class="text-gray-700" x-text="completeName"></strong> as complete?
            The status will change to <strong class="text-gray-700">Past</strong> and the event will be locked from further edits.
        </p>
        <div class="flex items-center gap-3">
            <button type="button" @click="completeOpen = false" :disabled="completeLoading"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors disabled:opacity-60">
                Cancel
            </button>
            <button type="button" @click="confirmComplete()" :disabled="completeLoading"
                    class="flex-1 py-2.5 text-sm font-semibold bg-green-600 hover:bg-green-700 text-white rounded-xl transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                <span x-text="completeLoading ? 'Saving…' : 'Mark Complete'"></span>
            </button>
        </div>
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
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </div>
        <h2 class="text-base font-bold text-gray-900 mb-2">Delete Event</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Delete <strong x-text="deleteName" class="text-gray-700"></strong>? This cannot be undone.
        </p>
        <div class="flex items-center gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form :action="'/events/' + deleteId" method="POST" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full py-2.5 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Ruleset Preview Modal --}}
<div x-show="rulesetOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="rulesetOpen = false" @keydown.escape.window="rulesetOpen = false"
     style="display:none;">

    <div x-show="rulesetOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Allocation Preview</p>
                <h3 class="font-bold text-gray-900 leading-tight" x-text="activeRuleset ? activeRuleset.name : ''"></h3>
            </div>
            <button @click="rulesetOpen = false"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-5 py-4 space-y-4" x-show="activeRuleset">

            {{-- Type badge + description --}}
            <div class="flex items-start gap-3">
                <span x-show="activeRuleset && activeRuleset.allocation_type === 'family_count'"
                      class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700 flex-shrink-0"
                      style="display:none;">By Families</span>
                <span x-show="activeRuleset && activeRuleset.allocation_type !== 'family_count'"
                      class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 flex-shrink-0">By Household Size</span>
                <p class="text-sm text-gray-500 leading-snug" x-text="activeRuleset ? activeRuleset.description : ''" x-show="activeRuleset && activeRuleset.description"></p>
            </div>

            {{-- Size stepper + result --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5"
                       x-text="activeRuleset && activeRuleset.allocation_type === 'family_count' ? 'Number of Families' : 'Household Size'">
                    Household Size
                </label>
                <div class="flex items-center gap-2">
                    <button @click="previewSize = Math.max(1, previewSize - 1)"
                            class="w-9 h-9 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition font-bold text-lg">−</button>
                    <input type="number" x-model.number="previewSize" min="1" max="20"
                           class="flex-1 text-center text-sm border border-gray-300 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500"/>
                    <button @click="previewSize = Math.min(20, previewSize + 1)"
                            class="w-9 h-9 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition font-bold text-lg">+</button>
                </div>
            </div>

            {{-- Bag count result --}}
            <div class="bg-brand-50 border border-brand-100 rounded-xl p-4 text-center">
                <p class="text-xs text-brand-600 font-medium mb-1">Bags Allocated</p>
                <p class="text-5xl font-extrabold text-brand-700" x-text="calcPreviewBags()"></p>
                <p class="text-xs text-brand-500 mt-1"
                   x-text="'for ' + previewSize + ' ' + (activeRuleset && activeRuleset.allocation_type === 'family_count' ? (previewSize === 1 ? 'family' : 'families') : (previewSize === 1 ? 'person' : 'people'))"></p>
            </div>

            {{-- Quick test buttons --}}
            <div>
                <p class="text-xs font-medium text-gray-500 mb-2">Quick Test</p>
                <div class="grid grid-cols-5 gap-1.5">
                    <template x-for="n in [1,2,3,4,5,6,7,8,9,10]" :key="n">
                        <button @click="previewSize = n"
                                :class="previewSize === n ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 border-gray-300 hover:border-brand-400'"
                                class="py-1.5 text-xs font-semibold border rounded-lg transition"
                                x-text="n"></button>
                    </template>
                </div>
            </div>

            {{-- Rules summary table --}}
            <div class="border-t border-gray-100 pt-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Distribution Table</p>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-400 border-b border-gray-100">
                            <th class="text-left pb-1.5 font-medium"
                                x-text="activeRuleset && activeRuleset.allocation_type === 'family_count' ? 'Families' : 'Size'">Size</th>
                            <th class="text-right pb-1.5 font-medium">Bags</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-if="activeRuleset" x-for="rule in activeRuleset.rules" :key="rule.min">
                            <tr :class="isActiveRule(rule) ? 'bg-brand-50' : ''">
                                <td class="py-1.5 text-gray-700" x-text="ruleRangeLabel(rule)"></td>
                                <td class="py-1.5 text-right">
                                    <span :class="isActiveRule(rule) ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700'"
                                          class="inline-flex items-center justify-center w-7 h-7 font-bold text-sm rounded-lg"
                                          x-text="rule.bags"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
@php
    $eventRulesetsMap = $events->filter(fn($e) => $e->ruleset)->mapWithKeys(fn($e) => [
        $e->id => [
            'id'              => $e->ruleset->id,
            'name'            => $e->ruleset->name,
            'allocation_type' => $e->ruleset->allocation_type ?? 'household_size',
            'description'     => $e->ruleset->description ?? '',
            'max_size'        => $e->ruleset->max_household_size,
            'rules'           => $e->ruleset->rules ?? [],
        ]
    ]);
@endphp
window.__eventRulesetsMap = @json($eventRulesetsMap);

function eventIndex() {
    return {
        // ── Delete modal ──────────────────────────────────────────────────────
        deleteId:   null,
        deleteName: '',
        deleteOpen: false,
        openDelete(id, name) {
            this.deleteId = id;
            this.deleteName = name;
            this.deleteOpen = true;
        },

        // ── Mark Complete modal ───────────────────────────────────────────────
        completeOpen:    false,
        completeName:    '',
        completeUrl:     '',
        completeLoading: false,
        openComplete(name, url) {
            this.completeName = name;
            this.completeUrl  = url;
            this.completeOpen = true;
        },
        async confirmComplete() {
            if (this.completeLoading) return;
            this.completeLoading = true;
            try {
                const res = await fetch(this.completeUrl, {
                    method:  'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ status: 'past' }),
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.error ?? 'Could not mark event complete.');
                    this.completeLoading = false;
                    this.completeOpen    = false;
                }
            } catch {
                alert('Network error. Please try again.');
                this.completeLoading = false;
                this.completeOpen    = false;
            }
        },

        // ── Undo Complete (no confirmation needed) ────────────────────────────
        async undoComplete(url) {
            try {
                const res = await fetch(url, {
                    method:  'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ status: 'undo' }),
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.error ?? 'Could not undo.');
                }
            } catch {
                alert('Network error. Please try again.');
            }
        },

        // Ruleset preview modal
        rulesetOpen:   false,
        activeRuleset: null,
        previewSize:   1,

        openRulesetPreview(eventId) {
            const rs = window.__eventRulesetsMap[eventId] ?? null;
            if (!rs) return;
            this.activeRuleset = rs;
            this.previewSize   = 1;
            this.rulesetOpen   = true;
        },

        calcPreviewBags() {
            if (!this.activeRuleset) return 0;
            for (const rule of this.activeRuleset.rules) {
                const min = parseInt(rule.min ?? 1);
                const max = rule.max !== null && rule.max !== undefined ? parseInt(rule.max) : Infinity;
                if (this.previewSize >= min && this.previewSize <= max) return parseInt(rule.bags ?? 0);
            }
            return 0;
        },

        isActiveRule(rule) {
            const min = parseInt(rule.min ?? 1);
            const max = rule.max !== null && rule.max !== undefined ? parseInt(rule.max) : Infinity;
            return this.previewSize >= min && this.previewSize <= max;
        },

        ruleRangeLabel(rule) {
            const min     = parseInt(rule.min ?? 1);
            const max     = rule.max !== null && rule.max !== undefined ? parseInt(rule.max) : null;
            const isFamily = this.activeRuleset && this.activeRuleset.allocation_type === 'family_count';
            const plural  = isFamily ? 'families' : 'people';
            const singular = isFamily ? 'family' : 'person';
            if (max === null) return `${min}+ ${plural}`;
            if (min === max)  return `${min} ${min === 1 ? singular : plural}`;
            return `${min}–${max} ${plural}`;
        },
    };
}
</script>
@endpush
