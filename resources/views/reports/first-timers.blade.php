@extends('layouts.app')
@section('title', 'Reports — First-Timers')

@section('content')
{{-- ═══════════════════════════════════════════════════════
     PAGE HEADER
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Analytics &amp; reporting center</p>
    </div>
    @can('reports.export')
    <a href="{{ route('reports.download', array_merge(request()->only(['preset','date_from','date_to']), ['type' => 'first-timers'])) }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors"
       title="Download first-timers list for this period">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Export CSV
    </a>
    @endcan
</div>

{{-- Sub-nav --}}
@include('reports._nav')

{{-- Date filter --}}
@include('reports._filter', ['formAction' => route('reports.first-timers')])

{{-- ═══════════════════════════════════════════════════════
     EXTRA FILTERS
═══════════════════════════════════════════════════════ --}}
<div x-data="firstTimerFilters()" class="bg-white border border-gray-100 rounded-2xl shadow-sm px-5 py-4 mb-5">
    <form method="GET" action="{{ route('reports.first-timers') }}" id="ft-filter-form">
        {{-- Preserve date params --}}
        <input type="hidden" name="preset"    value="{{ request('preset', 'last_30') }}">
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to"   value="{{ request('date_to') }}">

        <div class="flex flex-wrap items-end gap-3">

            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Search</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Name, number, phone…"
                           class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                </div>
            </div>

            {{-- Event --}}
            <div class="min-w-[180px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1 block">First Event</label>
                <select name="event_id"
                        class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2 bg-gray-50
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600 cursor-pointer">
                    <option value="">All Events</option>
                    @foreach ($events as $ev)
                        <option value="{{ $ev->id }}" @selected(request('event_id') == $ev->id)>
                            {{ $ev->name }} ({{ \Carbon\Carbon::parse($ev->date)->format('M j, Y') }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- ZIP --}}
            <div class="min-w-[120px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1 block">ZIP Code</label>
                <select name="zip"
                        class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2 bg-gray-50
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600 cursor-pointer">
                    <option value="">All ZIPs</option>
                    @foreach ($zipCodes as $z)
                        <option value="{{ $z }}" @selected(request('zip') === $z)>{{ $z }}</option>
                    @endforeach
                </select>
            </div>

            {{-- City --}}
            <div class="min-w-[140px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1 block">City</label>
                <input type="text" name="city" value="{{ request('city') }}"
                       placeholder="Filter by city…"
                       class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2 bg-gray-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
            </div>

            {{-- Representative --}}
            <div class="min-w-[170px]">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Rep. Linked</label>
                <select name="represented"
                        class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2 bg-gray-50
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600 cursor-pointer">
                    <option value="">All</option>
                    <option value="1" @selected(request('represented') === '1')>Has Representative</option>
                    <option value="0" @selected(request('represented') === '0')>Direct Attendance</option>
                </select>
            </div>

            {{-- Apply --}}
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 hover:bg-navy-800 text-white
                               text-sm font-semibold rounded-xl transition-colors">
                    Apply Filters
                </button>
                @if (request()->hasAny(['search','event_id','zip','city','represented']))
                    <a href="{{ route('reports.first-timers', request()->only(['preset','date_from','date_to'])) }}"
                       class="text-xs text-gray-500 hover:text-gray-700 px-3 py-2 rounded-xl hover:bg-gray-100 transition-colors">
                        Clear
                    </a>
                @endif
            </div>

        </div>
    </form>
</div>

{{-- ═══════════════════════════════════════════════════════
     KPI CARDS
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">

    {{-- Total First-Timers --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-navy-700 text-white">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">First-Timers</p>
        <p class="text-2xl font-bold tabular-nums">{{ number_format($kpi['total']) }}</p>
        <p class="text-xs text-white/60 mt-0.5">in date range</p>
    </div>

    {{-- Represented First-Timers --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Rep.-Linked</p>
        <p class="text-2xl font-bold tabular-nums">{{ number_format($kpi['represented']) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">have a representative</p>
    </div>

    {{-- Direct Attendance --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-white text-gray-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Direct</p>
        <p class="text-2xl font-bold tabular-nums">{{ number_format($kpi['total'] - $kpi['represented']) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">no representative linked</p>
    </div>

    {{-- Unique Events --}}
    <div class="rounded-2xl border border-gray-100 shadow-sm px-5 py-4 bg-brand-500 text-white">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Events</p>
        <p class="text-2xl font-bold tabular-nums">{{ count($kpi['breakdown']) }}</p>
        <p class="text-xs text-white/60 mt-0.5">produced first-timers</p>
    </div>

</div>

{{-- ── Event Breakdown ─────────────────────────────────────────────────── --}}
@if (count($kpi['breakdown']) > 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 mb-6">
    <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">First-Timers by Event</h2>
    <div class="space-y-3">
        @php
            $maxCount = collect($kpi['breakdown'])->max('count') ?: 1;
        @endphp
        @foreach ($kpi['breakdown'] as $row)
        <div class="flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-700 truncate">{{ $row->first_event_name ?? 'Unknown Event' }}</span>
                    <span class="text-sm font-bold text-navy-700 ml-3 tabular-nums">{{ $row->count }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="bg-navy-700 h-1.5 rounded-full transition-all"
                         style="width: {{ round(($row->count / $maxCount) * 100) }}%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">{{ $row->first_event_date ? \Carbon\Carbon::parse($row->first_event_date)->format('M j, Y') : '' }}</p>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════
     FIRST-TIMERS TABLE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Table header --}}
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">First-Timer Households</h2>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $firstTimers->total() }} household{{ $firstTimers->total() == 1 ? '' : 's' }} found
            </p>
        </div>
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide w-24">ID</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Household</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Phone</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Vehicle</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Size</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">First Event</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">First Date</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Representative</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Pickup</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($firstTimers as $h)
                    <tr class="hover:bg-gray-50/70 transition-colors">
                        <td class="px-5 py-3.5 text-sm text-gray-500 font-mono">{{ $h->household_number }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('households.show', $h) }}"
                                   class="font-semibold text-gray-900 hover:text-brand-600 hover:underline">
                                    {{ $h->full_name }}
                                </a>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 ring-1 ring-green-200">
                                    First-Timer
                                </span>
                            </div>
                            @if ($h->location)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $h->location }}{{ $h->zip ? ', '.$h->zip : '' }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-500">{{ $h->phone ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-sm text-gray-500">{{ $h->vehicle_label ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="text-sm text-gray-700">
                                {{ $h->household_size }}
                                @if ($h->children_count || $h->adults_count || $h->seniors_count)
                                    <span class="text-xs text-gray-400 block">
                                        @if ($h->adults_count) {{ $h->adults_count }}A @endif
                                        @if ($h->children_count) {{ $h->children_count }}C @endif
                                        @if ($h->seniors_count) {{ $h->seniors_count }}S @endif
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($h->first_event_name)
                                <span class="text-sm text-gray-900">{{ $h->first_event_name }}</span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-600">
                            {{ $h->first_event_date ? \Carbon\Carbon::parse($h->first_event_date)->format('M j, Y') : '—' }}
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($h->representative)
                                <a href="{{ route('households.show', $h->representative) }}"
                                   class="text-sm text-brand-600 hover:underline">
                                    {{ $h->representative->full_name }}
                                </a>
                                <p class="text-xs text-gray-400">#{{ $h->representative->household_number }}</p>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($h->representative)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                    Via Rep.
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                    Direct
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-16 text-center">
                            <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-500">No first-timer households found</p>
                            <p class="text-xs text-gray-400 mt-1">Try adjusting the date range or filters</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse ($firstTimers as $h)
            <div class="px-4 py-4">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                            <span class="text-xs text-gray-400 font-mono">#{{ $h->household_number }}</span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                First-Timer
                            </span>
                            @if ($h->representative)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-blue-50 text-blue-700">
                                    Via Rep.
                                </span>
                            @endif
                        </div>
                        <p class="font-semibold text-gray-900">{{ $h->full_name }}</p>
                        @if ($h->phone)<p class="text-sm text-gray-500">{{ $h->phone }}</p>@endif
                        @if ($h->location)<p class="text-xs text-gray-400 mt-0.5">{{ $h->location }}{{ $h->zip ? ', '.$h->zip : '' }}</p>@endif
                    </div>
                    <a href="{{ route('households.show', $h) }}"
                       class="flex-shrink-0 p-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-navy-700 hover:text-white hover:border-navy-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs mt-2">
                    <div>
                        <span class="text-gray-400">First Event</span>
                        <p class="font-medium text-gray-700">{{ $h->first_event_name ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">First Date</span>
                        <p class="font-medium text-gray-700">{{ $h->first_event_date ? \Carbon\Carbon::parse($h->first_event_date)->format('M j, Y') : '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Size</span>
                        <p class="font-medium text-gray-700">
                            {{ $h->household_size }}
                            @if ($h->adults_count) · {{ $h->adults_count }}A @endif
                            @if ($h->children_count) {{ $h->children_count }}C @endif
                            @if ($h->seniors_count) {{ $h->seniors_count }}S @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-400">Vehicle</span>
                        <p class="font-medium text-gray-700">{{ $h->vehicle_label ?? '—' }}</p>
                    </div>
                    @if ($h->representative)
                    <div class="col-span-2">
                        <span class="text-gray-400">Representative</span>
                        <p class="font-medium text-gray-700">{{ $h->representative->full_name }} (#{{ $h->representative->household_number }})</p>
                    </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center text-sm text-gray-400">
                No first-timer households found for this period.
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if ($firstTimers->hasPages())
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-3 border-t border-gray-100">
        <p class="text-sm text-gray-500">
            Showing {{ $firstTimers->firstItem() }}–{{ $firstTimers->lastItem() }} of {{ $firstTimers->total() }}
        </p>
        <div class="flex items-center gap-1 text-sm">
            @if ($firstTimers->onFirstPage())
                <span class="w-8 h-8 flex items-center justify-center text-gray-300 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
                </span>
            @else
                <a href="{{ $firstTimers->previousPageUrl() }}"
                   class="w-8 h-8 flex items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
                </a>
            @endif

            @foreach ($firstTimers->getUrlRange(max(1, $firstTimers->currentPage()-2), min($firstTimers->lastPage(), $firstTimers->currentPage()+2)) as $page => $url)
                <a href="{{ $url }}"
                   class="w-8 h-8 flex items-center justify-center rounded-md text-sm font-medium transition-colors
                          {{ $page == $firstTimers->currentPage() ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $page }}
                </a>
            @endforeach

            @if ($firstTimers->hasMorePages())
                <a href="{{ $firstTimers->nextPageUrl() }}"
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
    @endif

</div>

@endsection

@push('scripts')
<script>
function firstTimerFilters() {
    return {};
}
</script>
@endpush
