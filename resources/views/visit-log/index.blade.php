@extends('layouts.app')
@section('title', 'Visit Log')

@php
/**
 * Format decimal minutes as "Xh Ym" or "Ym"
 */
function fmtDuration(float $mins): string {
    $m = (int) round($mins);
    if ($m < 60) return $m . 'm';
    $h = intdiv($m, 60);
    $r = $m % 60;
    return $r === 0 ? "{$h}h" : "{$h}h {$r}m";
}
@endphp

@push('styles')
<style>
    .vl-badge {
        display: inline-flex; align-items: center; padding: 2px 8px;
        border-radius: 9999px; font-size: 11px; font-weight: 600; letter-spacing: .03em;
    }
    .vl-badge-checked_in { background:#EFF6FF; color:#1D4ED8; }
    .vl-badge-queued     { background:#F5F3FF; color:#6D28D9; }
    .vl-badge-loading    { background:#FFF7ED; color:#C2410C; }
    .vl-badge-loaded     { background:#FEF3C7; color:#B45309; }
    .vl-badge-exited     { background:#F0FDF4; color:#15803D; }
    .vl-long             { background:#F3F4F6 !important; }
</style>
@endpush

@section('content')
<div x-data="visitLog()" x-init="init()">

{{-- ═══════════════════════════════════════════════════════
     PAGE HEADER
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Visit Log</h1>
        <p class="text-xs text-gray-400 mt-0.5">Event-level operational report &amp; workflow analytics</p>
    </div>

    <div class="flex items-center gap-3 flex-wrap">
        {{-- Event selector --}}
        <form method="GET" action="{{ route('visit-log.index') }}" id="event-form">
            <select name="event_id"
                    onchange="document.getElementById('event-form').submit()"
                    class="text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">
                @forelse($events as $ev)
                    <option value="{{ $ev->id }}"
                        {{ $selectedEvent && $ev->id === $selectedEvent->id ? 'selected' : '' }}>
                        {{ $ev->name }} — {{ $ev->date->format('M j, Y') }}
                        @if($ev->isCurrent()) (Today) @endif
                    </option>
                @empty
                    <option disabled>No events available</option>
                @endforelse
            </select>
        </form>

        @if($selectedEvent)
        {{-- Print (respects active filters via Alpine-built href) --}}
        <a :href="printUrl"
           target="_blank" rel="noopener"
           :title="filterLabel ? 'Print filtered: ' + filterLabel : 'Print all visits'"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600
                  border border-gray-200 rounded-xl px-3 py-2 bg-white
                  hover:bg-gray-50 hover:border-gray-300 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
            </svg>
            <span>Print</span>
            <span x-show="filterLabel" class="text-[10px] text-navy-600 font-bold uppercase tracking-wide">·filtered</span>
        </a>
        {{-- CSV Export (respects active filters via Alpine-built href) --}}
        <a :href="exportUrl"
           :title="filterLabel ? 'Export filtered: ' + filterLabel : 'Export all visits'"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600
                  border border-gray-200 rounded-xl px-3 py-2 bg-white
                  hover:bg-gray-50 hover:border-gray-300 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            <span>Export CSV</span>
            <span x-show="filterLabel" class="text-[10px] text-navy-600 font-bold uppercase tracking-wide">·filtered</span>
        </a>
        @endif
    </div>
</div>

@if(! $selectedEvent)
{{-- ─── Empty state ───────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
    </svg>
    <p class="text-gray-500 font-medium">No events with visit data yet</p>
    <p class="text-gray-400 text-sm mt-1">Visit data will appear once an event is set to current or past.</p>
</div>
@else

{{-- ═══════════════════════════════════════════════════════
     EVENT BANNER
═══════════════════════════════════════════════════════ --}}
<div class="bg-gray-100 border border-gray-200 rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-2">
    <div>
        <p class="text-gray-400 text-xs font-semibold uppercase tracking-widest mb-0.5">
            @if($selectedEvent->isCurrent()) Today's Event @else Past Event @endif
        </p>
        <h2 class="text-xl font-black text-gray-900">{{ $selectedEvent->name }}</h2>
        <p class="text-gray-500 text-sm mt-0.5">
            {{ $selectedEvent->date->format('l, F j, Y') }}
            @if($selectedEvent->location)
                &middot; {{ $selectedEvent->location }}
            @endif
            &middot; {{ $selectedEvent->lanes }} {{ Str::plural('lane', $selectedEvent->lanes) }}
        </p>
    </div>
    <div class="text-right">
        <p class="text-4xl font-black text-navy-700">{{ number_format($summary['households_served']) }}</p>
        <p class="text-gray-400 text-xs font-semibold uppercase tracking-wide">Households Served</p>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     KPI CARDS
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">

    {{-- Total visits checked in --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-2xl font-black text-navy-700">{{ number_format($summary['total_visits']) }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Total Check-ins</p>
        <div class="mt-2 h-1 rounded-full bg-gray-100">
            @php $pct = $summary['total_visits'] > 0 ? min(100, round($summary['households_served'] / $summary['total_visits'] * 100)) : 0; @endphp
            <div class="h-1 rounded-full bg-navy-600" style="width: {{ $pct }}%"></div>
        </div>
        <p class="text-[10px] text-gray-400 mt-1">{{ $pct }}% completed</p>
    </div>

    {{-- People served --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-2xl font-black text-navy-700">{{ number_format($summary['people_served']) }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">People Served</p>
        <p class="text-[10px] text-gray-400 mt-3">
            @if($summary['households_served'] > 0)
                {{ round($summary['people_served'] / $summary['households_served'], 1) }} avg per household
            @else
                —
            @endif
        </p>
    </div>

    {{-- Bags distributed --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-2xl font-black text-navy-700">{{ number_format($summary['bags_distributed']) }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Bags Distributed</p>
        <p class="text-[10px] text-gray-400 mt-3">
            @if($summary['households_served'] > 0)
                {{ round($summary['bags_distributed'] / $summary['households_served'], 1) }} avg per household
            @else
                —
            @endif
        </p>
    </div>

    {{-- Avg total time --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <p class="text-2xl font-black text-navy-700">{{ $summary['avg_total_time'] > 0 ? fmtDuration($summary['avg_total_time']) : '—' }}</p>
        <p class="text-xs text-gray-500 font-medium mt-1">Avg Service Time</p>
        <p class="text-[10px] text-gray-400 mt-3">Door to exit, completed visits</p>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════
     TIMING BREAKDOWN + STATUS DISTRIBUTION
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">

    {{-- Avg timing per stage (3 cards in a mini-grid) --}}
    <div class="lg:col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Avg Time Per Stage</h3>

        <div class="space-y-3">
            {{-- Checkin → Queue --}}
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-gray-500">Check-in → Queue</span>
                    <span class="font-bold text-gray-700">{{ $summary['avg_checkin_to_queue'] > 0 ? fmtDuration($summary['avg_checkin_to_queue']) : '—' }}</span>
                </div>
                @php $maxT = max($summary['avg_checkin_to_queue'], $summary['avg_queue_to_loaded'], $summary['avg_loaded_to_exited'], 1); @endphp
                <div class="h-2 bg-gray-100 rounded-full">
                    <div class="h-2 bg-navy-600 rounded-full"
                         style="width: {{ min(100, round($summary['avg_checkin_to_queue'] / $maxT * 100)) }}%"></div>
                </div>
            </div>
            {{-- Queue → Load --}}
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-gray-500">Queue → Loading</span>
                    <span class="font-bold text-gray-700">{{ $summary['avg_queue_to_loaded'] > 0 ? fmtDuration($summary['avg_queue_to_loaded']) : '—' }}</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full">
                    <div class="h-2 bg-navy-600 rounded-full"
                         style="width: {{ min(100, round($summary['avg_queue_to_loaded'] / $maxT * 100)) }}%"></div>
                </div>
            </div>
            {{-- Load → Exit --}}
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-gray-500">Loading → Exit</span>
                    <span class="font-bold text-gray-700">{{ $summary['avg_loaded_to_exited'] > 0 ? fmtDuration($summary['avg_loaded_to_exited']) : '—' }}</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full">
                    <div class="h-2 bg-navy-600 rounded-full"
                         style="width: {{ min(100, round($summary['avg_loaded_to_exited'] / $maxT * 100)) }}%"></div>
                </div>
            </div>
        </div>

        {{-- Bottleneck badge --}}
        @php
            $stages = [
                'Check-in → Queue' => $summary['avg_checkin_to_queue'],
                'Queue → Loading'  => $summary['avg_queue_to_loaded'],
                'Loading → Exit'   => $summary['avg_loaded_to_exited'],
            ];
            $bottleneck = array_search(max($stages), $stages);
        @endphp
        @if(max($stages) > 0)
        <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
            <p class="text-xs text-amber-700 font-semibold">
                ⚠ Bottleneck: {{ $bottleneck }} ({{ fmtDuration(max($stages)) }} avg)
            </p>
        </div>
        @endif
    </div>

    {{-- Process Time Chart --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Hourly Check-ins</h3>
        <div class="relative h-44">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════
     LANE PERFORMANCE + STATUS DISTRIBUTION
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">

    {{-- Lane Performance Chart --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Visits per Lane</h3>
        <div class="relative h-44">
            <canvas id="laneChart"></canvas>
        </div>
    </div>

    {{-- Status Distribution --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Current Status</h3>
        @php $statusCounts = $summary['status_counts']; $totalC = array_sum($statusCounts); @endphp
        <div class="space-y-2">
            @foreach([
                'checked_in' => ['Checked In',  'bg-blue-500'],
                'queued'     => ['Queued',       'bg-purple-500'],
                'loading'    => ['Loading',      'bg-orange-500'],
                'loaded'     => ['Loaded',       'bg-amber-400'],
                'exited'     => ['Exited',       'bg-green-500'],
            ] as $key => [$label, $barClass])
            @php $cnt = $statusCounts[$key] ?? 0; $pctS = $totalC > 0 ? round($cnt / $totalC * 100) : 0; @endphp
            <div>
                <div class="flex justify-between text-xs mb-0.5">
                    <span class="text-gray-500">{{ $label }}</span>
                    <span class="font-bold text-gray-700">{{ $cnt }}</span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full">
                    <div class="h-2 {{ $barClass }} rounded-full" style="width: {{ $pctS }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════
     VISIT DETAIL TABLE
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">

    {{-- Table toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="text-sm font-bold text-gray-700">Visit Detail</h3>
        <div class="flex items-center gap-2 flex-wrap">
            {{-- Search --}}
            <div class="relative">
                <svg class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Name or #"
                       class="pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded-xl
                              focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600 w-36">
            </div>
            {{-- Lane filter --}}
            <select x-model="filterLane"
                    class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 bg-white
                           focus:outline-none focus:ring-2 focus:ring-navy-600">
                <option value="">All Lanes</option>
                @for($l = 1; $l <= $selectedEvent->lanes; $l++)
                    <option value="{{ $l }}">Lane {{ $l }}</option>
                @endfor
            </select>
            {{-- Status filter --}}
            <select x-model="filterStatus"
                    class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 bg-white
                           focus:outline-none focus:ring-2 focus:ring-navy-600">
                <option value="">All Statuses</option>
                <option value="checked_in">Checked In</option>
                <option value="queued">Queued</option>
                <option value="loading">Loading</option>
                <option value="loaded">Loaded</option>
                <option value="exited">Exited</option>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto -mx-5 px-5">
        <table class="w-full text-sm min-w-[900px]">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4">Household</th>
                    <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4">Lane</th>
                    <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4">Status</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4">Check-in</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4">Queued</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4">Loaded</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4">Exited</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4" title="Check-in to Queue">C→Q</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4" title="Queue to Loaded">Q→L</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4" title="Loaded to Exit">L→E</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2 pr-4" title="Total time">Total</th>
                    <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide pb-2">Bags</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="v in paged" :key="v.id">
                    <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
                        :class="v.total_time > 45 ? 'vl-long' : ''">
                        <td class="py-2.5 pr-4">
                            <p class="font-semibold text-gray-900">
                                <span x-text="v.full_name"></span>
                                <template x-if="v.additional_count > 0">
                                    <span class="ml-1 text-xs font-medium text-gray-400"
                                          :title="v.household_count + ' households on this visit'"
                                          x-text="'+' + v.additional_count + ' more'"></span>
                                </template>
                            </p>
                            <p class="text-xs text-gray-400 font-mono" x-text="'#' + v.household_number"></p>
                        </td>
                        <td class="py-2.5 pr-4">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                                         bg-gray-100 text-gray-700 text-sm font-bold"
                                  x-text="v.lane"></span>
                        </td>
                        <td class="py-2.5 pr-4">
                            <span class="vl-badge" :class="'vl-badge-' + v.visit_status" x-text="v.status_label"></span>
                        </td>
                        <td class="py-2.5 pr-4 text-right text-xs text-gray-500 font-mono tabular-nums"
                            x-text="v.start_time_fmt || '—'"></td>
                        <td class="py-2.5 pr-4 text-right text-xs text-gray-500 font-mono tabular-nums"
                            x-text="v.queued_at_fmt || '—'"></td>
                        <td class="py-2.5 pr-4 text-right text-xs text-gray-500 font-mono tabular-nums"
                            x-text="v.loading_completed_at_fmt || '—'"></td>
                        <td class="py-2.5 pr-4 text-right text-xs text-gray-500 font-mono tabular-nums"
                            x-text="v.exited_at_fmt || '—'"></td>
                        <td class="py-2.5 pr-4 text-right tabular-nums">
                            <span class="text-xs font-semibold" :class="v.checkin_to_queue != null ? 'text-gray-700' : 'text-gray-300'"
                                  x-text="fmtMins(v.checkin_to_queue)"></span>
                        </td>
                        <td class="py-2.5 pr-4 text-right tabular-nums">
                            <span class="text-xs font-semibold" :class="v.queue_to_loaded != null ? 'text-gray-700' : 'text-gray-300'"
                                  x-text="fmtMins(v.queue_to_loaded)"></span>
                        </td>
                        <td class="py-2.5 pr-4 text-right tabular-nums">
                            <span class="text-xs font-semibold" :class="v.loaded_to_exited != null ? 'text-gray-700' : 'text-gray-300'"
                                  x-text="fmtMins(v.loaded_to_exited)"></span>
                        </td>
                        <td class="py-2.5 pr-4 text-right tabular-nums">
                            <span class="text-xs font-bold"
                                  :class="v.total_time > 45 ? 'text-red-600' : (v.total_time != null ? 'text-navy-700' : 'text-gray-300')"
                                  x-text="fmtMins(v.total_time)"></span>
                        </td>
                        <td class="py-2.5 text-right">
                            {{-- Bags only count as "distributed" once the visit is exited;
                                 keeps the column reconcilable with the Bags Distributed KPI. --}}
                            <span class="text-sm font-bold"
                                  :class="v.visit_status === 'exited' && v.served_bags != null ? 'text-gray-700' : 'text-gray-300'"
                                  x-text="v.visit_status === 'exited' && v.served_bags != null ? v.served_bags : '—'"></span>
                        </td>
                    </tr>
                </template>
                <template x-if="filtered.length === 0">
                    <tr>
                        <td colspan="12" class="py-10 text-center text-gray-400 text-sm">
                            No visits match your filters.
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Pagination footer --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-3 border-t border-gray-100">
        <p class="text-xs text-gray-500" x-text="rangeLabel"></p>

        <div class="flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>Show</span>
                <select x-model.number="perPage"
                        class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white
                               focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option :value="15">15</option>
                    <option :value="30">30</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                    <option :value="0">All</option>
                </select>
            </div>

            <div class="flex items-center gap-1" x-show="totalPages > 1">
                <button type="button"
                        @click="page = Math.max(1, page - 1)"
                        :disabled="page <= 1"
                        class="px-2 py-1 text-xs rounded-lg border border-gray-200 bg-white text-gray-600
                               hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    ‹ Prev
                </button>
                <span class="text-xs text-gray-500 px-2 tabular-nums"
                      x-text="'Page ' + page + ' of ' + totalPages"></span>
                <button type="button"
                        @click="page = Math.min(totalPages, page + 1)"
                        :disabled="page >= totalPages"
                        class="px-2 py-1 text-xs rounded-lg border border-gray-200 bg-white text-gray-600
                               hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    Next ›
                </button>
            </div>
        </div>
    </div>

</div>
{{-- end if selectedEvent --}}
@endif

</div>{{-- end x-data --}}
@endsection

@push('scripts')
@if($selectedEvent && $chartData)
<script>
// ─── Chart.js ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const CHART_DATA = @json($chartData);

    const gridColor = '#F3F4F6';
    const textColor = '#6B7280';
    const navyColor = '#1e3a5f';

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1F2937',
                padding: 8,
                cornerRadius: 8,
                titleFont: { size: 12 },
                bodyFont: { size: 12 },
            },
        },
        scales: {
            x: {
                grid: { color: gridColor },
                ticks: { color: textColor, font: { size: 11 } },
            },
            y: {
                grid: { color: gridColor },
                ticks: { color: textColor, font: { size: 11 }, precision: 0 },
                beginAtZero: true,
            },
        },
    };

    // Hourly check-ins
    const hourlyEl = document.getElementById('hourlyChart');
    if (hourlyEl && CHART_DATA.hourlyCheckins.labels.length) {
        new Chart(hourlyEl, {
            type: 'bar',
            data: {
                labels: CHART_DATA.hourlyCheckins.labels,
                datasets: [{ data: CHART_DATA.hourlyCheckins.values, backgroundColor: navyColor, borderRadius: 6, borderSkipped: false }],
            },
            options: baseOptions,
        });
    }

    // Lane performance
    const laneEl = document.getElementById('laneChart');
    if (laneEl) {
        new Chart(laneEl, {
            type: 'bar',
            data: {
                labels: CHART_DATA.lanePerformance.labels,
                datasets: [{ data: CHART_DATA.lanePerformance.values, backgroundColor: navyColor, borderRadius: 6, borderSkipped: false }],
            },
            options: {
                ...baseOptions,
                plugins: {
                    ...baseOptions.plugins,
                    tooltip: { ...baseOptions.plugins.tooltip,
                        callbacks: { label: ctx => ' ' + ctx.raw + ' visit' + (ctx.raw !== 1 ? 's' : '') }
                    },
                },
            },
        });
    }
});
</script>
@endif

@php
$visitsJson = $visits->map(function ($v) {
    return [
        'id'                       => $v->id,
        'lane'                     => $v->lane,
        'visit_status'             => $v->visit_status,
        'status_label'             => $v->status_label,
        'served_bags'              => $v->served_bags,
        'household_number'         => $v->household_number,
        'full_name'                => $v->full_name,
        'household_count'          => $v->household_count,
        'additional_count'         => $v->additional_count,
        'household_size'           => $v->household_size,
        'checkin_to_queue'         => $v->checkin_to_queue,
        'queue_to_loaded'          => $v->queue_to_loaded,
        'loaded_to_exited'         => $v->loaded_to_exited,
        'total_time'               => $v->total_time,
        'start_time_fmt'           => optional($v->start_time)->format('g:i A'),
        'queued_at_fmt'            => optional($v->queued_at)->format('g:i A'),
        'loading_completed_at_fmt' => optional($v->loading_completed_at)->format('g:i A'),
        'exited_at_fmt'            => optional($v->exited_at)->format('g:i A'),
    ];
})->values()->all();
@endphp
<script>
// ─── Alpine component ─────────────────────────────────────────────────────────
function visitLog() {
    return {
        search:       '',
        filterLane:   '',
        filterStatus: '',

        // Pagination — perPage of 0 means "All".
        page:    1,
        perPage: 15,

        // Print/CSV base URLs already include event_id; getters below
        // append the active filter state so the server returns the
        // same row set the user is looking at.
        printBase:  @json(route('visit-log.print',  ['event_id' => $selectedEvent->id])),
        exportBase: @json(route('visit-log.export', ['event_id' => $selectedEvent->id])),

        visits: @json($visitsJson),

        get filtered() {
            const q    = this.search.toLowerCase().trim();
            const lane = this.filterLane;
            const st   = this.filterStatus;

            return this.visits.filter(v => {
                if (lane && String(v.lane) !== String(lane)) return false;
                if (st   && v.visit_status !== st)           return false;
                if (q) {
                    const hay = (v.full_name + ' ' + v.household_number).toLowerCase();
                    if (!hay.includes(q)) return false;
                }
                return true;
            });
        },

        get totalPages() {
            if (!this.perPage) return 1;
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
        },

        get paged() {
            if (!this.perPage) return this.filtered;
            const start = (this.page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },

        get filterParams() {
            const p = new URLSearchParams();
            if (this.search.trim()) p.append('search', this.search.trim());
            if (this.filterLane)    p.append('lane',   this.filterLane);
            if (this.filterStatus)  p.append('status', this.filterStatus);
            return p.toString();
        },

        get filterLabel() {
            const parts = [];
            if (this.filterLane)    parts.push('Lane ' + this.filterLane);
            if (this.filterStatus)  parts.push(this.filterStatus.replace('_', ' '));
            if (this.search.trim()) parts.push('"' + this.search.trim() + '"');
            return parts.join(' · ');
        },

        get printUrl() {
            const qs = this.filterParams;
            return qs ? this.printBase + '&' + qs : this.printBase;
        },

        get exportUrl() {
            const qs = this.filterParams;
            return qs ? this.exportBase + '&' + qs : this.exportBase;
        },

        get rangeLabel() {
            const total = this.filtered.length;
            if (total === 0) return '0 visits';
            if (!this.perPage) return total + ' of ' + this.visits.length + ' visits';
            const start = (this.page - 1) * this.perPage + 1;
            const end   = Math.min(start + this.perPage - 1, total);
            return start + '–' + end + ' of ' + total +
                   (total !== this.visits.length ? ' (filtered from ' + this.visits.length + ')' : '');
        },

        fmtMins(m) {
            if (m == null) return '—';
            m = Math.round(m);
            if (m < 60) return m + 'm';
            const h = Math.floor(m / 60);
            const rem = m % 60;
            return rem === 0 ? h + 'h' : h + 'h ' + rem + 'm';
        },

        init() {
            // Filter or page-size changes invalidate the current page index;
            // snap back to page 1 so the user does not land on an empty page.
            this.$watch('search',       () => this.page = 1);
            this.$watch('filterLane',   () => this.page = 1);
            this.$watch('filterStatus', () => this.page = 1);
            this.$watch('perPage',      () => this.page = 1);
        },
    };
}
</script>
@endpush
