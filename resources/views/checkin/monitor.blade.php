@extends('layouts.app')
@section('title', 'Visit Monitor')

@push('styles')
<style>
.sortable-ghost  { opacity:.25; border:2px dashed #94a3b8 !important; border-radius:1rem; }
.sortable-chosen { box-shadow:0 8px 24px rgba(0,0,0,.12); }
.sortable-drag   { box-shadow:0 14px 36px rgba(0,0,0,.18); opacity:.98; }
@keyframes live-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }
.live-dot { animation:live-pulse 2s ease-in-out infinite; }
</style>
@endpush

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Visit Monitor</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
            <span class="text-gray-700 font-medium">Monitor</span>
        </nav>
    </div>

    <div class="flex items-center gap-2 sm:ml-auto flex-wrap">
        <form method="GET" action="{{ route('monitor.index') }}">
            <div class="relative">
                <select name="event" onchange="this.form.submit()"
                        class="pl-3.5 pr-8 py-2 text-sm border border-gray-200 rounded-lg bg-white appearance-none
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 font-medium text-gray-800">
                    <option value="">— Select event —</option>
                    @foreach ($events as $ev)
                        <option value="{{ $ev->id }}" {{ $selected && $selected->id === $ev->id ? 'selected' : '' }}>
                            {{ $ev->name }}
                        </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </div>
            </div>
        </form>

        @if ($selected)
        <div class="flex items-center gap-1.5 text-xs text-gray-400">
            <span class="live-dot w-2 h-2 rounded-full bg-green-400"></span>
            <span id="refresh-label">Loading…</span>
        </div>
        <button onclick="Monitor.load()" type="button"
                class="w-8 h-8 flex items-center justify-center bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-gray-500">
            <svg id="refresh-icon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
            </svg>
        </button>
        <a href="{{ route('checkin.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-white border border-gray-200 rounded-lg px-3 py-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            Check-in
        </a>
        @endif
    </div>
</div>

@if (! $selected)
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-20 text-center">
    <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
        <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        </svg>
    </div>
    <p class="text-sm font-semibold text-gray-700 mb-1">No event selected</p>
    <p class="text-sm text-gray-400">Select an event from the dropdown above to open the live monitor.</p>
</div>

@else

{{-- ── Global stats ──────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-3 sm:grid-cols-6 gap-2.5 mb-5">
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </div>
        <div><p id="stat-checked-in" class="text-lg font-black text-gray-900">—</p><p class="text-[10px] text-gray-400">Checked In</p></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>
            </svg>
        </div>
        <div><p id="stat-queued" class="text-lg font-black text-gray-900">—</p><p class="text-[10px] text-gray-400">Queued</p></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-orange-50 flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"/>
            </svg>
        </div>
        <div><p id="stat-loading" class="text-lg font-black text-gray-900">—</p><p class="text-[10px] text-gray-400">Loading</p></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-green-50 flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
            </svg>
        </div>
        <div><p id="stat-served" class="text-lg font-black text-gray-900">—</p><p class="text-[10px] text-gray-400">Served</p></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-navy-50 flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-navy-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
            </svg>
        </div>
        <div><p id="stat-families" class="text-lg font-black text-gray-900">—</p><p class="text-[10px] text-gray-400">Families</p></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
        </div>
        <div><p id="stat-people" class="text-lg font-black text-gray-900">—</p><p class="text-[10px] text-gray-400">People</p></div>
    </div>
</div>

{{-- ── Lane tabs ─────────────────────────────────────────────────────────── --}}
@if ($selected->lanes > 1)
<div class="flex gap-2 mb-4 overflow-x-auto pb-1">
    @for ($l = 1; $l <= $selected->lanes; $l++)
    <button type="button"
            class="lane-tab shrink-0 px-5 py-2 rounded-xl text-sm font-bold transition-colors {{ $l === 1 ? 'bg-navy-700 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}"
            data-lane="{{ $l }}" onclick="Monitor.switchLane({{ $l }})">
        Lane {{ $l }}
    </button>
    @endfor
</div>
@endif

{{-- ── Board: 4 columns ──────────────────────────────────────────────────── --}}
<div class="overflow-x-auto">
<div class="grid grid-cols-4 gap-4 min-w-[900px]">

    {{-- ── Intake column ──────────────────────────────────────────────────── --}}
    <div>
        <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Intake</p>
                </div>
                <span id="col-stat-intake" class="text-xs font-semibold text-blue-600 bg-blue-50 rounded-full px-2.5 py-0.5">0 arrived</span>
            </div>
        </div>
        <div id="intake-list" class="space-y-2 min-h-[4rem]"></div>
        <p id="intake-empty" class="hidden text-center text-gray-400 text-xs py-8">No check-ins yet</p>
    </div>

    {{-- ── Scanner column ─────────────────────────────────────────────────── --}}
    <div>
        <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-3">
            <div class="flex items-center gap-2 mb-2.5">
                <span class="w-2 h-2 rounded-full bg-purple-400 shrink-0"></span>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Scanner / Queue</p>
            </div>
            <div class="flex gap-4">
                <div>
                    <p id="col-stat-scanner-arrived" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">Arrived</p>
                </div>
                <div>
                    <p id="col-stat-scanner-queued" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">In Queue</p>
                </div>
                <div class="ml-auto text-right">
                    <p id="col-stat-scanner-served" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">Served</p>
                </div>
            </div>
        </div>
        <p class="text-xs text-gray-400 mb-2">Drag to reorder</p>
        <div id="scanner-list" class="space-y-2 min-h-[4rem]"></div>
        <p id="scanner-empty" class="hidden text-center text-gray-400 text-xs py-8">No active visits</p>
    </div>

    {{-- ── Loader column ──────────────────────────────────────────────────── --}}
    <div>
        <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-3">
            <div class="flex items-center gap-2 mb-2.5">
                <span class="w-2 h-2 rounded-full bg-orange-400 shrink-0"></span>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Loader</p>
            </div>
            <div class="flex gap-4">
                <div>
                    <p id="col-stat-loader-toload" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">To Load</p>
                </div>
                <div>
                    <p id="col-stat-loader-loaded" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">Loaded</p>
                </div>
                <div class="ml-auto text-right">
                    <p id="col-stat-loader-served" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">Served</p>
                </div>
            </div>
        </div>
        <p class="text-xs text-gray-400 mb-2">Drag to reorder</p>
        <div id="loader-list" class="space-y-2 min-h-[4rem]"></div>
        <p id="loader-empty" class="hidden text-center text-gray-400 text-xs py-8">No vehicles in queue</p>
    </div>

    {{-- ── Exit column ─────────────────────────────────────────────────────── --}}
    <div>
        <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-3">
            <div class="flex items-center gap-2 mb-2.5">
                <span class="w-2 h-2 rounded-full bg-green-400 shrink-0"></span>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Exit</p>
            </div>
            <div class="flex gap-4">
                <div>
                    <p id="col-stat-exit-ready" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">Ready to Exit</p>
                </div>
                <div class="ml-auto text-right">
                    <p id="col-stat-exit-served" class="text-xl font-black text-gray-900">—</p>
                    <p class="text-[10px] text-gray-400">Served Today</p>
                </div>
            </div>
        </div>
        <div id="exit-list" class="space-y-2 min-h-[4rem]"></div>
        <p id="exit-empty" class="hidden text-center text-gray-400 text-xs py-8">No vehicles ready to exit</p>
    </div>

</div>
</div>

{{-- Phase 2.1.e: insufficient-stock modal (supervisor path) --}}
<div id="mon-stock-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.5)"
     role="dialog" aria-modal="true" aria-labelledby="mon-stock-modal-title">
    <div class="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4">
        <h2 id="mon-stock-modal-title" class="text-lg font-black text-gray-900 mb-1">Not Enough Stock</h2>
        <p id="mon-stock-modal-msg" class="text-sm text-gray-600 mb-5"></p>
        <div class="flex gap-3">
            <button id="mon-stock-modal-skip"
                    class="flex-1 py-3 rounded-xl bg-navy-700 hover:bg-navy-800 text-white font-black text-sm transition-colors">
                Skip &amp; Mark Loaded
            </button>
            <button id="mon-stock-modal-cancel"
                    class="flex-1 py-3 rounded-xl bg-white border border-gray-200 text-gray-700 font-black text-sm transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

@if ($selected)
<script>
const Monitor = (function () {

    // ── Config ─────────────────────────────────────────────────────────────
    const LANES          = {{ $selected->lanes }};
    const CSRF           = document.querySelector('meta[name="csrf-token"]').content;
    const DATA_URL       = '{{ route('monitor.data', $selected) }}';
    const REORDER_URL    = '{{ route('monitor.reorder', $selected) }}';
    const TRANSITION_URL = '{{ url('/monitor/' . $selected->id . '/visits') }}';

    // ── State ───────────────────────────────────────────────────────────────
    let activeLane     = 1;
    let isDragging     = false;
    // Phase 1.1.c.3: suppresses the 10s poll while a reorder POST is in flight.
    // Without this, the poll could land between drag-end and POST-resolve,
    // overwrite the cached `allLanes` with stale updated_at tokens, and cause
    // the next drag to 409 spuriously.
    let pendingReorder = false;
    let scannerSortable = null;
    let loaderSortable  = null;
    let allLanes       = {};   // cached: allLanes[laneNum] = { checked_in, queued, loaded }
    let globalStats    = {};

    // ── Escape HTML ─────────────────────────────────────────────────────────
    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function el(id) { return document.getElementById(id); }
    function set(id, v) { const e = el(id); if (e) e.textContent = v ?? '—'; }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Card HTML: Intake ────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    function intakeCardHtml(v) {
        const hh     = v.household;
        const waited = v.waited_min > 0 ? `${v.waited_min} min ago` : 'just now';
        const repBadge = v.is_representative_pickup
            ? `<span class="inline-block text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">★ Rep Pickup</span>`
            : '';
        return `
        <div class="rounded-2xl border border-gray-200 bg-white p-5 select-none">
            <p class="font-black text-gray-900 text-base leading-tight">${esc(hh.vehicle_label || '—')}</p>
            <p class="text-xs font-mono text-gray-400 mt-0.5">#${esc(hh.household_number)} · Ln ${v.lane}</p>
            ${repBadge ? `<div class="mt-1.5">${repBadge}</div>` : ''}
            <div class="flex items-center justify-between mt-3 text-xs text-gray-500">
                <span>${v.total_people} ppl · ${v.bags_needed} bags</span>
                <span class="text-gray-400">${waited}</span>
            </div>
        </div>`;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Card HTML: Scanner ───────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    function scannerCardHtml(v, pos) {
        const hh     = v.household;
        const queued = v.visit_status === 'queued';
        const border = queued ? 'border-purple-300 bg-purple-50' : 'border-gray-200 bg-white';
        const isRep  = v.is_representative_pickup;

        const action = queued
            ? `<span class="px-3 py-2 rounded-xl bg-purple-100 text-purple-700 text-xs font-bold whitespace-nowrap">Queued</span>`
            : `<button onclick="Monitor.queueVisit(${v.id}, this)"
                       class="px-4 py-2.5 rounded-xl bg-navy-700 hover:bg-navy-800 active:bg-navy-900 text-white text-sm font-bold transition-colors whitespace-nowrap">
                   Queue
               </button>`;

        const repCount = (v.represented_households || []).length + 1;
        const repBadge = isRep
            ? `<span class="inline-flex items-center text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5 ml-1.5">★ Rep · ${repCount} families</span>`
            : '';

        const repList = isRep && v.represented_households && v.represented_households.length > 0
            ? `<div class="mt-1.5 pl-2 border-l-2 border-amber-200 space-y-0.5">
                   ${v.represented_households.map(r =>
                       `<p class="text-[10px] text-amber-700">↳ ${esc(r.full_name)} · ${r.household_size} ppl${r.bags_needed != null ? ' · ' + r.bags_needed + ' bags' : ''}</p>`
                   ).join('')}
               </div>`
            : '';

        return `
        <div class="rounded-2xl border-2 ${border} p-4 select-none" data-id="${v.id}" data-lane="${v.lane}" data-updated-at="${esc(v.updated_at || '')}">
            <div class="flex items-start gap-3">
                <div class="drag-handle flex flex-col items-center gap-1.5 cursor-grab active:cursor-grabbing shrink-0 pt-0.5 touch-none">
                    <span class="pos-num w-8 h-8 rounded-full bg-navy-700 text-white text-sm font-black flex items-center justify-center">${pos}</span>
                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-black text-gray-900 text-base leading-tight">${esc(hh.vehicle_label || '—')}</p>
                    <p class="text-sm text-gray-600 mt-0.5 flex items-center flex-wrap gap-y-0.5">
                        <span class="font-mono text-gray-400">#${esc(hh.household_number)}</span>
                        &nbsp;·&nbsp;${esc(hh.full_name)}
                        ${repBadge}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        <strong>${v.total_people}</strong> ppl &nbsp;·&nbsp;
                        <strong>${v.bags_needed}</strong> bags &nbsp;·&nbsp;
                        ${v.waited_min} min
                    </p>
                    ${repList}
                </div>
                <div class="shrink-0">${action}</div>
            </div>
        </div>`;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Card HTML: Loader ────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    function loaderCardHtml(v, pos) {
        const hh          = v.household;
        const isRep       = v.is_representative_pickup;
        const reps        = v.represented_households || [];
        const repBagsSum  = reps.reduce((s, r) => s + (r.bags_needed || 0), 0);
        const primaryBags = v.bags_needed - repBagsSum;
        const familyCount = isRep ? reps.length + 1 : 1;

        const repRows = isRep && reps.length > 0
            ? `<div class="mt-2 pt-2 border-t border-amber-100 space-y-1">
                   <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wide mb-1">★ Rep Pickup — ${reps.length + 1} households</p>
                   <div class="flex items-center gap-1.5 text-xs text-gray-600">
                       <span class="text-amber-400 font-bold">★</span>
                       <span>${hh.household_size} ppl</span>
                       <span class="text-orange-600 font-bold ml-auto">${primaryBags} bags</span>
                   </div>
                   ${reps.map(r => `
                   <div class="flex items-center gap-1.5 text-xs text-gray-600">
                       <span class="text-amber-300 font-bold">↳</span>
                       <span>${r.household_size} ppl</span>
                       ${r.bags_needed != null ? `<span class="text-orange-600 font-bold ml-auto">${r.bags_needed} bags</span>` : ''}
                   </div>`).join('')}
               </div>`
            : '';

        return `
        <div class="rounded-2xl border border-navy-100 bg-navy-50 p-4 select-none" data-id="${v.id}" data-lane="${v.lane}" data-updated-at="${esc(v.updated_at || '')}">
            <div class="flex items-start gap-3">
                <div class="drag-handle flex flex-col items-center gap-1.5 cursor-grab active:cursor-grabbing shrink-0 pt-0.5 touch-none">
                    <span class="pos-num w-8 h-8 rounded-full bg-navy-700 text-white text-sm font-black flex items-center justify-center">${pos}</span>
                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-black text-gray-900 text-base leading-tight">${esc(hh.vehicle_label || '—')}</p>
                    <p class="text-sm font-mono text-gray-400 mt-0.5">#${esc(hh.household_number)} · Ln ${v.lane}</p>
                    <div class="flex gap-2 mt-2.5">
                        <div class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-center min-w-[3.5rem]">
                            <p class="text-xl font-black text-gray-900">${familyCount}</p>
                            <p class="text-[10px] text-gray-500">${familyCount === 1 ? 'Family' : 'Families'}</p>
                        </div>
                        <div class="bg-orange-50 border border-orange-200 rounded-xl px-3 py-2 text-center flex-1">
                            <p class="text-3xl font-black text-orange-700">${v.bags_needed}</p>
                            <p class="text-[10px] text-orange-600 font-semibold">Total Bags</p>
                        </div>
                    </div>
                    ${repRows}
                </div>
            </div>
            <button onclick="Monitor.markLoaded(${v.id}, this)"
                    class="mt-3 w-full py-3.5 rounded-2xl bg-navy-700 hover:bg-navy-800 active:bg-navy-900 text-white font-black text-sm transition-colors">
                Loading Complete
            </button>
        </div>`;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Card HTML: Exit ──────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    function exitCardHtml(v) {
        const hh          = v.household;
        const isRep       = v.is_representative_pickup;
        const reps        = v.represented_households || [];
        const repBagsSum  = reps.reduce((s, r) => s + (r.bags_needed || 0), 0);
        const primaryBags = v.bags_needed - repBagsSum;
        const familyCount = isRep ? reps.length + 1 : 1;

        const repBadge = isRep
            ? `<p class="text-xs font-bold text-amber-700 mt-1">★ Rep Pickup · ${reps.length + 1} households</p>`
            : '';

        const repDetails = isRep && reps.length > 0
            ? `<div class="mt-2 pt-2 border-t border-green-100 space-y-1">
                   <div class="flex items-center gap-1.5 text-xs text-gray-500">
                       <span class="text-amber-400 font-bold">★</span>
                       <span>${hh.household_size} ppl</span>
                       <span class="font-bold ml-auto">${primaryBags} bags</span>
                   </div>
                   ${reps.map(r => `
                   <div class="flex items-center gap-1.5 text-xs text-gray-500">
                       <span class="text-amber-300 font-bold">↳</span>
                       <span>${r.household_size} ppl</span>
                       ${r.bags_needed != null ? `<span class="font-bold ml-auto">${r.bags_needed} bags</span>` : ''}
                   </div>`).join('')}
               </div>`
            : '';

        return `
        <div class="rounded-2xl border-2 border-green-300 bg-white p-4" data-id="${v.id}">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="min-w-0 flex-1">
                    <p class="font-black text-gray-900 text-xl leading-tight">${esc(hh.vehicle_label || '—')}</p>
                    <p class="text-xs font-mono text-gray-400 mt-1">#${esc(hh.household_number)} · Lane ${v.lane}</p>
                    ${repBadge}
                </div>
                <div class="text-right shrink-0">
                    <p class="text-4xl font-black text-green-700">${v.bags_needed}</p>
                    <p class="text-[10px] text-green-600 font-semibold uppercase tracking-wide">bags</p>
                </div>
            </div>
            <div class="flex gap-2 mb-3">
                <div class="flex-1 bg-gray-50 border border-gray-200 rounded-xl py-2 text-center">
                    <p class="text-2xl font-black text-gray-900">${familyCount}</p>
                    <p class="text-[10px] text-gray-500 font-medium">${familyCount === 1 ? 'Family' : 'Families'}</p>
                </div>
                <div class="flex-1 bg-gray-50 border border-gray-200 rounded-xl py-2 text-center">
                    <p class="text-lg font-black text-gray-700">Ln ${v.lane}</p>
                    <p class="text-[10px] text-gray-500 font-medium">Lane</p>
                </div>
            </div>
            ${repDetails}
            <button onclick="Monitor.markExited(${v.id}, this)"
                    class="mt-3 w-full py-4 rounded-2xl bg-navy-700 hover:bg-navy-800 active:bg-navy-900 text-white font-black text-base transition-colors">
                Served &amp; Exit
            </button>
        </div>`;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Render all 4 columns ─────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    function renderAll() {
        if (isDragging) return;
        const data = allLanes[activeLane] || { checked_in: [], queued: [], loaded: [] };
        renderIntake(data);
        renderScanner(data);
        renderLoader(data);
        renderExit(data);
        updateColumnStats(data);
    }

    function renderIntake(data) {
        const list  = el('intake-list');
        const empty = el('intake-empty');
        const visits = data.checked_in || [];
        if (visits.length === 0) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            list.innerHTML = visits.map(v => intakeCardHtml(v)).join('');
        }
    }

    function renderScanner(data) {
        const list  = el('scanner-list');
        const empty = el('scanner-empty');
        // Scanner sees checked_in + queued combined, sorted by queue_position then start_time
        const visits = [...(data.checked_in || []), ...(data.queued || [])]
            .sort((a, b) => (a.queue_position - b.queue_position) || a.start_time.localeCompare(b.start_time));

        if (visits.length === 0) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            list.innerHTML = visits.map((v, i) => scannerCardHtml(v, i + 1)).join('');
        }

        if (scannerSortable) scannerSortable.destroy();
        scannerSortable = Sortable.create(list, {
            animation: 180, handle: '.drag-handle',
            ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen', dragClass: 'sortable-drag',
            onStart() { isDragging = true; },
            onEnd()   { isDragging = false; renumber(list); sendReorder(); },
        });
    }

    function renderLoader(data) {
        const list  = el('loader-list');
        const empty = el('loader-empty');
        const visits = (data.queued || []).sort((a, b) => a.queue_position - b.queue_position);

        if (visits.length === 0) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            list.innerHTML = visits.map((v, i) => loaderCardHtml(v, i + 1)).join('');
        }

        if (loaderSortable) loaderSortable.destroy();
        loaderSortable = Sortable.create(list, {
            animation: 180, handle: '.drag-handle',
            ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen', dragClass: 'sortable-drag',
            onStart() { isDragging = true; },
            onEnd()   { isDragging = false; renumber(list); sendReorder(); },
        });
    }

    function renderExit(data) {
        const list  = el('exit-list');
        const empty = el('exit-empty');
        const visits = data.loaded || [];

        if (visits.length === 0) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            list.innerHTML = visits.map(v => exitCardHtml(v)).join('');
        }
    }

    function renumber(list) {
        list.querySelectorAll('.pos-num').forEach((el, i) => el.textContent = i + 1);
    }

    function updateColumnStats(data) {
        const arrived = (data.checked_in || []).length;
        const queued  = (data.queued    || []).length;
        const loaded  = (data.loaded    || []).length;
        const served  = globalStats.served ?? 0;

        set('col-stat-intake', `${arrived} arrived`);
        set('col-stat-scanner-arrived', arrived);
        set('col-stat-scanner-queued',  queued);
        set('col-stat-scanner-served',  served);
        set('col-stat-loader-toload',   queued);
        set('col-stat-loader-loaded',   loaded);
        set('col-stat-loader-served',   served);
        set('col-stat-exit-ready',      loaded);
        set('col-stat-exit-served',     served);
    }

    function updateGlobalStats(s) {
        set('stat-checked-in', s.checked_in);
        set('stat-queued',     s.queued);
        set('stat-loading',    s.loading);
        set('stat-served',     s.served);
        set('stat-families',   s.families_served);
        set('stat-people',     s.people_served);
        const label = el('refresh-label');
        if (label) label.textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Reorder ──────────────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    async function sendReorder() {
        const moves = [];
        // Collect from scanner list (covers both checked_in + queued)
        el('scanner-list')?.querySelectorAll('[data-id]').forEach((card, idx) => {
            moves.push({
                id:             parseInt(card.dataset.id),
                lane:           activeLane,
                queue_position: idx + 1,
                updated_at:     card.dataset.updatedAt || '',
            });
        });
        if (!moves.length) return;
        pendingReorder = true;
        try {
            const res = await fetch(REORDER_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body:    JSON.stringify({ moves }),
            });
            if (res.status === 409) {
                // Stale optimistic-lock token: pull fresh data instead of fighting.
                pendingReorder = false;
                load();
                return;
            }
            if (!res.ok) return;
            const body = await res.json();
            // Refresh per-card updated_at tokens so subsequent drags don't 409.
            // Patch both the live DOM and the cached `allLanes` — otherwise a
            // re-render driven by the cache (e.g. switchLane) before the next
            // poll would write the old token back into the DOM.
            const tokenMap = {};
            (body.visits || []).forEach(fresh => {
                tokenMap[fresh.id] = fresh.updated_at || '';
                const card = el('scanner-list')?.querySelector(`[data-id="${fresh.id}"]`);
                if (card) card.dataset.updatedAt = fresh.updated_at || '';
            });
            const lane = allLanes[activeLane];
            if (lane) {
                ['checked_in', 'queued'].forEach(bucket => {
                    (lane[bucket] || []).forEach(v => {
                        if (tokenMap[v.id] !== undefined) v.updated_at = tokenMap[v.id];
                    });
                });
            }
        } catch {} finally {
            pendingReorder = false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Actions (via supervisor transition endpoint) ──────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    // skipInventory=true is set when the supervisor taps "Skip & Mark Loaded"
    // on the insufficient-stock modal (Phase 2.1.e).
    async function transition(visitId, status, btn, originalLabel, skipInventory) {
        btn.disabled = true; btn.textContent = 'Saving…';
        try {
            const body = { status };
            if (skipInventory) body.skip_inventory = 1;
            const res = await fetch(`${TRANSITION_URL}/${visitId}/transition`, {
                method:  'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body:    JSON.stringify(body),
            });
            if (res.ok) {
                await load(); // refresh data
            } else if (res.status === 422 && status === 'loaded') {
                const data = await res.json().catch(() => ({}));
                if (data.error === 'insufficient_stock') {
                    el('mon-stock-modal-msg').textContent =
                        `Needed ${data.needed}, available ${data.available}. Skip the inventory deduction and mark as loaded anyway?`;
                    const modal     = el('mon-stock-modal');
                    const skipBtn   = el('mon-stock-modal-skip');
                    const cancelBtn = el('mon-stock-modal-cancel');
                    modal.classList.remove('hidden');
                    skipBtn.focus();
                    const closeModal = () => { modal.classList.add('hidden'); btn.focus(); };
                    const onKey = e => { if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', onKey); } };
                    document.addEventListener('keydown', onKey);
                    skipBtn.onclick = () => {
                        closeModal(); document.removeEventListener('keydown', onKey);
                        transition(visitId, 'loaded', btn, originalLabel, true);
                    };
                    cancelBtn.onclick = () => {
                        closeModal(); document.removeEventListener('keydown', onKey);
                        btn.disabled = false; btn.textContent = originalLabel;
                    };
                } else {
                    btn.disabled = false; btn.textContent = originalLabel;
                }
            } else {
                btn.disabled = false; btn.textContent = originalLabel;
            }
        } catch {
            btn.disabled = false; btn.textContent = originalLabel;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ── Data fetch ───────────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────────
    async function load() {
        if (pendingReorder) return;
        const icon = el('refresh-icon');
        if (icon) icon.classList.add('animate-spin');
        try {
            const res  = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            allLanes     = data.lanes  || {};
            globalStats  = data.stats  || {};
            if (!isDragging) renderAll();
            updateGlobalStats(globalStats);
        } catch {}
        finally {
            if (icon) icon.classList.remove('animate-spin');
        }
    }

    // ── Lane tab switching ────────────────────────────────────────────────────
    function switchLane(laneNum) {
        activeLane = laneNum;
        document.querySelectorAll('.lane-tab').forEach(tab => {
            const active = parseInt(tab.dataset.lane) === laneNum;
            tab.className = `lane-tab shrink-0 px-5 py-2 rounded-xl text-sm font-bold transition-colors ${
                active ? 'bg-navy-700 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
            }`;
        });
        renderAll();
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
        await load();
        setInterval(() => { if (!isDragging && !pendingReorder) load(); }, 10_000);
    });

    return {
        load,
        switchLane,
        queueVisit:  (id, btn) => transition(id, 'queued',  btn, 'Queue'),
        markLoaded:  (id, btn) => transition(id, 'loaded',  btn, 'Loading Complete'),
        markExited:  (id, btn) => transition(id, 'exited',  btn, 'Served & Exit'),
    };

})();
</script>
@endif
@endpush
