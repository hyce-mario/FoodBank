@extends('layouts.app')
@section('title', 'Event Reviews')

@section('content')

{{-- ── Header ───────────────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Event Reviews</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
            <span class="text-gray-700 font-medium">Reviews</span>
        </nav>
    </div>

    <a href="{{ route('public.reviews.create') }}" target="_blank"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-lg px-3.5 py-2.5 transition-colors self-start">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
        </svg>
        Public Review Form
    </a>
</div>

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
    </svg>
    {{ session('success') }}
</div>
@endif

{{-- ── Filter bar ───────────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('reviews.index') }}"
      class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 mb-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

        {{-- Event filter --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Event</label>
            <div class="relative">
                <select name="event_id"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white appearance-none
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                    <option value="">All Events</option>
                    @foreach ($allEvents as $ev)
                        <option value="{{ $ev->id }}" {{ request('event_id') == $ev->id ? 'selected' : '' }}>
                            {{ $ev->name }} ({{ $ev->date->format('M j, Y') }})
                        </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Rating filter --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Rating</label>
            <div class="relative">
                <select name="rating"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white appearance-none
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                    <option value="">Any Rating</option>
                    @foreach ([5,4,3,2,1] as $r)
                        <option value="{{ $r }}" {{ request('rating') == $r ? 'selected' : '' }}>
                            {{ str_repeat('★', $r) }}{{ str_repeat('☆', 5 - $r) }} ({{ $r }} star{{ $r !== 1 ? 's' : '' }})
                        </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Search --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Search</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search reviews or emails…"
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                          placeholder:text-gray-300">
        </div>

        {{-- Actions --}}
        <div class="flex items-end gap-2">
            <button type="submit"
                    class="flex-1 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
                Filter
            </button>
            @if (request()->hasAny(['event_id','rating','search','from','to']))
                <a href="{{ route('reviews.index') }}"
                   class="py-2 px-3 text-sm font-medium text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg bg-white transition-colors">
                    Clear
                </a>
            @endif
        </div>

    </div>
</form>

{{-- ── Event review groups ──────────────────────────────────────────────────── --}}
@if ($events->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-16 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-gray-900 mb-1">No reviews found</p>
        <p class="text-sm text-gray-400">
            @if (request()->hasAny(['event_id','rating','search']))
                Try adjusting your filters, or
                <a href="{{ route('reviews.index') }}" class="text-brand-500 hover:underline">clear all filters</a>.
            @else
                Reviews submitted by the public will appear here, grouped by event.
            @endif
        </p>
    </div>
@else
    <div class="space-y-5">
        @foreach ($events as $event)
            @php
                $reviews     = $event->reviews;
                $totalAll    = $event->reviews()->count(); // true total (unfiltered)
                $avgAll      = round($event->reviews()->where('is_visible', true)->avg('rating') ?? 0, 1);
                $visibleCount = $event->reviews()->where('is_visible', true)->count();
                $filteredCount = $reviews->count();
            @endphp

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                {{-- Event header ─────────────────────────────────────────── --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3
                            px-5 py-4 border-b border-gray-100 bg-gray-50/60">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-brand-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-4.5 h-4.5 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">{{ $event->name }}</h2>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $event->date->format('D, M j, Y') }}</p>
                        </div>
                    </div>

                    {{-- Stats badges --}}
                    <div class="flex items-center gap-3 flex-wrap">
                        {{-- Average rating --}}
                        <div class="flex items-center gap-1.5">
                            <span class="text-yellow-400 text-base leading-none">★</span>
                            <span class="text-sm font-bold text-gray-800">{{ $avgAll > 0 ? $avgAll : '—' }}</span>
                            <span class="text-xs text-gray-400">avg</span>
                        </div>
                        <div class="w-px h-4 bg-gray-200"></div>
                        {{-- Total visible reviews --}}
                        <div class="flex items-center gap-1">
                            <span class="text-sm font-bold text-gray-800">{{ $visibleCount }}</span>
                            <span class="text-xs text-gray-400">review{{ $visibleCount !== 1 ? 's' : '' }}</span>
                        </div>
                        {{-- Hidden count badge --}}
                        @if ($totalAll > $visibleCount)
                            <span class="text-xs font-medium px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-full">
                                {{ $totalAll - $visibleCount }} hidden
                            </span>
                        @endif
                        <a href="{{ route('events.show', $event) }}"
                           class="text-xs font-semibold text-brand-500 hover:text-brand-600 transition-colors">
                            View Event →
                        </a>
                    </div>
                </div>

                {{-- Review list ──────────────────────────────────────────── --}}
                @if ($reviews->isEmpty())
                    <div class="px-5 py-8 text-center">
                        <p class="text-sm text-gray-400">No reviews match the current filter for this event.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach ($reviews as $review)
                            <div class="px-5 py-4 {{ ! $review->is_visible ? 'bg-gray-50/80 opacity-60' : '' }}">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">

                                    {{-- Left: stars + text --}}
                                    <div class="flex-1 min-w-0">
                                        {{-- Star row --}}
                                        <div class="flex items-center gap-2 mb-2">
                                            <div class="flex items-center">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <span class="text-lg leading-none {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-200' }}">★</span>
                                                @endfor
                                            </div>
                                            @if (! $review->is_visible)
                                                <span class="text-xs font-semibold px-2 py-0.5 bg-gray-200 text-gray-500 rounded-full">Hidden</span>
                                            @endif
                                        </div>

                                        {{-- Review text --}}
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

                                    {{-- Right: toggle button --}}
                                    <div class="flex-shrink-0">
                                        <form method="POST"
                                              action="{{ route('reviews.toggle-visibility', $review) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors
                                                           {{ $review->is_visible
                                                               ? 'border-gray-200 text-gray-500 hover:bg-gray-100'
                                                               : 'border-green-200 text-green-700 bg-green-50 hover:bg-green-100' }}">
                                                {{ $review->is_visible ? 'Hide' : 'Show' }}
                                            </button>
                                        </form>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        @endforeach
    </div>
@endif

@endsection
