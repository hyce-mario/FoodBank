@extends('layouts.event-day')

@section('title', 'Scanner — ' . $event->name)

@section('header')
<header class="bg-purple-600 shrink-0 px-4 py-4 flex items-center justify-between shadow-md">
    <div>
        <p class="text-xs text-purple-200 uppercase tracking-widest font-semibold">Scanner / Queue</p>
        <h1 class="text-white text-xl font-black leading-tight mt-0.5">{{ $event->name }}</h1>
        <p class="text-purple-200 text-xs mt-0.5">
            {{ $event->date->format('l, F j') }}{{ $event->location ? ' · ' . $event->location : '' }}
        </p>
    </div>
    <span id="ed-clock" class="text-white text-sm font-bold tabular-nums">{{ now()->format('g:i A') }}</span>
</header>
@endsection

@push('styles')
<style>
.sortable-ghost  { opacity:.3; border-radius:1rem; }
.sortable-chosen { box-shadow:0 0 0 3px #9333ea; border-radius:1rem; }
.sortable-drag   { box-shadow:0 12px 32px rgba(0,0,0,.2); border-radius:1rem; }
</style>
@endpush

@section('content')
<div class="p-4 max-w-2xl mx-auto">

    {{-- Stats bar --}}
    <div class="grid grid-cols-3 gap-2 mb-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-black text-gray-800" data-stat="checked_in">—</p>
            <p class="text-xs text-gray-500 font-medium mt-0.5">Arrived</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-black text-purple-700" data-stat="queued">—</p>
            <p class="text-xs text-gray-500 font-medium mt-0.5">In Queue</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-black text-green-600" data-stat="served">—</p>
            <p class="text-xs text-gray-500 font-medium mt-0.5">Served</p>
        </div>
    </div>

    {{-- Lane filter --}}
    <div class="flex gap-2 mb-3 overflow-x-auto pb-1">
        <button data-lane="0"
                class="lane-btn shrink-0 px-4 py-2 rounded-xl text-sm font-bold transition-colors bg-purple-600 text-white shadow-sm">
            All
        </button>
        @for ($l = 1; $l <= $event->lanes; $l++)
        <button data-lane="{{ $l }}"
                class="lane-btn shrink-0 px-4 py-2 rounded-xl text-sm font-bold transition-colors bg-white text-gray-600 border border-gray-200">
            Lane {{ $l }}
        </button>
        @endfor
    </div>

    <p class="text-xs text-gray-400 font-medium mb-3">Drag cards to reorder the physical queue</p>

    <div id="scanner-list" class="space-y-2 min-h-[4rem]"></div>
    <p id="scanner-empty" class="hidden text-center text-gray-400 text-sm py-12">No active visits</p>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const EVENT_ID    = {{ $event->id }};
    const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
    // Use Blade-rendered absolute URLs so subdir deployments
    // (e.g. http://localhost/Foodbank/public/...) resolve correctly.
    const DATA_URL    = "{{ route('event-day.scanner.data', $event) }}";
    const REORDER_URL = "{{ route('event-day.reorder', $event) }}";
    const QUEUE_URL   = "{{ url('/ed/' . $event->id . '/visits') }}";

    let visits         = [];
    let stats          = {};
    let activeLane     = 0;
    let sortable       = null;
    let dragging       = false;
    // Phase 1.1.c.2: suppresses the poll while a reorder POST is in flight.
    // Without this, the 8s setInterval can land between onEnd's `dragging=false`
    // and the POST resolving, overwrite the local visits[] with the server's
    // pre-reorder updated_at tokens, and cause the next drag to 409 spuriously.
    let pendingReorder = false;

    // ── Render ────────────────────────────────────────────────────────────────
    function render() {
        const list  = document.getElementById('scanner-list');
        const empty = document.getElementById('scanner-empty');

        const shown = (activeLane === 0 ? visits : visits.filter(v => v.lane == activeLane))
            .filter(v => v.visit_status == null || ['checked_in','queued'].includes(v.visit_status));

        if (shown.length === 0) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            list.innerHTML = shown.map((v, i) => cardHtml(v, i + 1)).join('');
        }

        if (sortable) sortable.destroy();
        sortable = Sortable.create(list, {
            animation:   180,
            handle:      '.drag-handle',
            ghostClass:  'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass:   'sortable-drag',
            onStart() { dragging = true; },
            onEnd()   { dragging = false; renumber(); sendReorder(); },
        });
    }

    function cardHtml(v, pos) {
        const hh     = v.household;
        const queued = v.visit_status === 'queued';
        const border = queued ? 'border-purple-300 bg-purple-50' : 'border-gray-200 bg-white';
        const isRep  = v.is_representative_pickup;

        const action = queued
            ? `<span class="px-3 py-2 rounded-xl bg-purple-100 text-purple-700 text-xs font-bold whitespace-nowrap">Queued</span>`
            : `<button onclick="window._queueVisit(${v.id},this)"
                       class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white text-sm font-bold transition-colors whitespace-nowrap">
                   Queue
               </button>`;

        const repCount = (v.represented_households || []).length + 1;
        const repBadge = isRep
            ? `<span class="inline-flex items-center text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5 ml-1.5">★ Rep&nbsp;Pickup&nbsp;·&nbsp;${repCount}&nbsp;families</span>`
            : '';

        const repList = isRep && v.represented_households && v.represented_households.length > 0
            ? `<div class="mt-1.5 pl-2 border-l-2 border-amber-200 space-y-0.5">
                   ${v.represented_households.map(r =>
                       `<p class="text-[10px] text-amber-700">↳ ${esc(r.full_name)} &nbsp;·&nbsp; ${r.household_size} ppl${r.bags_needed != null ? ' &nbsp;·&nbsp; ' + r.bags_needed + ' bags' : ''}</p>`
                   ).join('')}
               </div>`
            : '';

        // Family tag — same hover/tap-revealed demographic chip used on intake.
        // Alpine 3 picks up the x-data on innerHTML insertion via MutationObserver,
        // so the tag works even though scanner-list is rebuilt on every poll.
        const size      = hh.household_size ?? 0;
        const kids      = hh.children_count ?? 0;
        const adults    = hh.adults_count   ?? 0;
        const seniors   = hh.seniors_count  ?? 0;
        const memberLbl = size === 1   ? 'Member'  : 'Members';
        const kidsLbl   = kids === 1   ? 'Child'   : 'Children';
        const adultsLbl = adults === 1 ? 'Adult'   : 'Adults';
        const senLbl    = seniors === 1? 'Senior'  : 'Seniors';

        const familyTag = `<span x-data="{ showDemo: false }"
              @mouseenter="showDemo = true"
              @mouseleave="showDemo = false"
              @click.stop="showDemo = !showDemo"
              class="relative inline-block cursor-help align-middle">
            <span class="font-semibold text-gray-700">1 Family</span>
            <span x-show="showDemo" style="display:none"
                  x-transition:enter="transition ease-out duration-150"
                  x-transition:enter-start="opacity-0 translate-y-1"
                  x-transition:enter-end="opacity-100 translate-y-0"
                  class="absolute left-0 top-full mt-1 z-30 min-w-32 bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                <span class="block text-sm font-semibold text-gray-900 mb-2">${size} ${memberLbl}</span>
                <span class="block text-xs text-gray-600">
                    <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span><span class="font-semibold text-gray-800">${kids}</span><span>${kidsLbl}</span></span>
                    <span class="flex items-center gap-2 mt-1"><span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span><span class="font-semibold text-gray-800">${adults}</span><span>${adultsLbl}</span></span>
                    <span class="flex items-center gap-2 mt-1"><span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span><span class="font-semibold text-gray-800">${seniors}</span><span>${senLbl}</span></span>
                </span>
            </span>
        </span>`;

        return `
        <div class="visit-card rounded-2xl border-2 ${border} p-4 select-none"
             data-id="${v.id}" data-lane="${v.lane}" data-updated-at="${esc(v.updated_at || '')}">
          <div class="flex items-start gap-3">
            <div class="drag-handle flex flex-col items-center gap-1.5 cursor-grab active:cursor-grabbing shrink-0 pt-0.5 touch-none">
              <span class="pos-num w-8 h-8 rounded-full bg-purple-600 text-white text-sm font-black flex items-center justify-center">${pos}</span>
              <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-black text-gray-900 text-base leading-tight">${esc(hh.vehicle_label || '—')}</p>
              <p class="text-sm text-gray-600 mt-0.5 flex items-center flex-wrap gap-y-0.5">
                <span class="font-mono text-gray-400">#${esc(hh.household_number)}</span>
                &nbsp;·&nbsp;${esc(hh.full_name)}
                &nbsp;·&nbsp;<span class="font-semibold">Ln ${v.lane}</span>
                ${repBadge}
              </p>
              <div class="text-xs text-gray-500 mt-1">
                ${familyTag} &nbsp;·&nbsp;
                <strong>${v.bags_needed}</strong> bags &nbsp;·&nbsp;
                ${v.waited_min} min
              </div>
              ${repList}
            </div>
            <div class="shrink-0">${action}</div>
          </div>
        </div>`;
    }

    function renumber() {
        document.querySelectorAll('#scanner-list .visit-card').forEach((el, i) => {
            const b = el.querySelector('.pos-num');
            if (b) b.textContent = i + 1;
        });
    }

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // ── Mark queued ───────────────────────────────────────────────────────────
    window._queueVisit = async function(id, btn) {
        btn.disabled = true; btn.textContent = '…';
        try {
            const r = await fetch(`${QUEUE_URL}/${id}/queued`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (r.ok) {
                const v = visits.find(x => x.id === id);
                if (v) v.visit_status = 'queued';
                render();
                updateStats(stats);
            }
        } catch { btn.disabled = false; btn.textContent = 'Queue'; }
    };

    // ── Reorder ───────────────────────────────────────────────────────────────
    async function sendReorder() {
        const moves = [];
        document.querySelectorAll('#scanner-list .visit-card').forEach((el, i) => {
            moves.push({
                id:             parseInt(el.dataset.id),
                lane:           parseInt(el.dataset.lane),
                queue_position: i + 1,
                updated_at:     el.dataset.updatedAt || '',
            });
        });
        moves.forEach(m => {
            const v = visits.find(x => x.id === m.id);
            if (v) { v.lane = m.lane; v.queue_position = m.queue_position; }
        });
        pendingReorder = true;
        try {
            const r = await fetch(REORDER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ moves }),
            });
            if (r.status === 409) {
                // Someone else moved a visit between our last poll and this drag.
                // Refetch to pick up the latest state instead of overwriting it.
                pendingReorder = false;
                fetchData();
                return;
            }
            if (!r.ok) return;
            const body = await r.json();
            // Refresh optimistic-lock tokens on each affected card so a
            // subsequent drag doesn't trip its own version check.
            (body.visits || []).forEach(fresh => {
                const card = document.querySelector(`#scanner-list .visit-card[data-id="${fresh.id}"]`);
                if (card) card.dataset.updatedAt = fresh.updated_at || '';
                const v = visits.find(x => x.id === fresh.id);
                if (v) v.updated_at = fresh.updated_at;
            });
        } catch {} finally {
            pendingReorder = false;
        }
    }

    // ── Fetch ─────────────────────────────────────────────────────────────────
    async function fetchData() {
        if (dragging || pendingReorder) return;
        try {
            const r = await fetch(DATA_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) return;
            const data = await r.json();
            stats  = data.stats;
            visits = Object.values(data.lanes).flat()
                .sort((a, b) => (a.queue_position - b.queue_position) || (a.lane - b.lane));
            render();
            updateStats(stats);
        } catch {}
    }

    function updateStats(s) {
        document.querySelectorAll('[data-stat]').forEach(el => {
            el.textContent = s[el.dataset.stat] ?? 0;
        });
    }

    // ── Lane buttons ──────────────────────────────────────────────────────────
    function setLane(l) {
        activeLane = l;
        document.querySelectorAll('.lane-btn').forEach(btn => {
            const active = parseInt(btn.dataset.lane) === l;
            btn.className = 'lane-btn shrink-0 px-4 py-2 rounded-xl text-sm font-bold transition-colors ' +
                (active ? 'bg-purple-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200');
        });
        render();
    }

    document.querySelectorAll('.lane-btn').forEach(btn =>
        btn.addEventListener('click', () => setLane(parseInt(btn.dataset.lane)))
    );

    fetchData();
    setInterval(fetchData, 8000);
})();
</script>
@endpush
