@extends('layouts.event-day')

@section('title', 'Loader — ' . $event->name)

@section('header')
<header class="bg-orange-500 shrink-0 px-4 py-4 flex items-center justify-between shadow-md">
    <div>
        <p class="text-xs text-orange-100 uppercase tracking-widest font-semibold">Loader Station</p>
        <h1 class="text-white text-xl font-black leading-tight mt-0.5">{{ $event->name }}</h1>
        <p class="text-orange-100 text-xs mt-0.5">
            {{ $event->date->format('l, F j') }}{{ $event->location ? ' · ' . $event->location : '' }}
        </p>
    </div>
    <span id="ed-clock" class="text-white text-sm font-bold tabular-nums">{{ now()->format('g:i A') }}</span>
</header>
@endsection

@push('styles')
<style>
.sortable-ghost  { opacity:.3; border-radius:1rem; }
.sortable-chosen { box-shadow:0 0 0 3px #f97316; border-radius:1rem; }
.sortable-drag   { box-shadow:0 12px 32px rgba(0,0,0,.2); border-radius:1rem; }
</style>
@endpush

@section('content')
<div class="p-4 max-w-2xl mx-auto">

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-2 mb-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-black text-orange-600" data-stat="queued">—</p>
            <p class="text-xs text-gray-500 font-medium mt-0.5">To Load</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-black text-blue-600" data-stat="loaded">—</p>
            <p class="text-xs text-gray-500 font-medium mt-0.5">Loaded</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-black text-green-600" data-stat="served">—</p>
            <p class="text-xs text-gray-500 font-medium mt-0.5">Served</p>
        </div>
    </div>

    {{-- Lane filter --}}
    <div class="flex gap-2 mb-3 overflow-x-auto pb-1">
        <button data-lane="0"
                class="lane-btn shrink-0 px-4 py-2 rounded-xl text-sm font-bold transition-colors bg-orange-500 text-white shadow-sm">
            All Lanes
        </button>
        @for ($l = 1; $l <= $event->lanes; $l++)
        <button data-lane="{{ $l }}"
                class="lane-btn shrink-0 px-4 py-2 rounded-xl text-sm font-bold transition-colors bg-white text-gray-600 border border-gray-200">
            Lane {{ $l }}
        </button>
        @endfor
    </div>

    <p class="text-xs text-gray-400 font-medium mb-3">Drag to match the physical loading order</p>

    <div id="loader-list" class="space-y-3 min-h-[4rem]"></div>
    <p id="loader-empty" class="hidden text-center text-gray-400 text-sm py-12">No vehicles in queue</p>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
    // Use Blade-rendered absolute URLs so subdir deployments
    // (e.g. http://localhost/Foodbank/public/...) resolve correctly.
    const DATA_URL    = "{{ route('event-day.loader.data', $event) }}";
    const REORDER_URL = "{{ route('event-day.reorder', $event) }}";
    const LOADED_URL  = "{{ url('/ed/' . $event->id . '/visits') }}";

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
        const list  = document.getElementById('loader-list');
        const empty = document.getElementById('loader-empty');

        const shown = (activeLane === 0 ? visits : visits.filter(v => v.lane == activeLane))
            .filter(v => v.visit_status === 'queued');

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
        const hh           = v.household;
        const isRep        = v.is_representative_pickup;
        const reps         = v.represented_households || [];
        const repBagsSum   = reps.reduce((s, r) => s + (r.bags_needed || 0), 0);
        const primaryBags  = v.bags_needed - repBagsSum;
        const familyCount  = isRep ? reps.length + 1 : 1;

        // Rep pickup breakdown: primary row (★) + each represented row (↳)
        const repRows = isRep && reps.length > 0
            ? `<div class="mt-2 pt-2 border-t border-amber-100 space-y-1">
                   <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wide mb-1">
                       ★ Rep Pickup — ${reps.length + 1} households
                   </p>
                   <div class="flex items-center gap-1.5 text-xs text-gray-600">
                       <span class="text-amber-400 font-bold">★</span>
                       <span>${hh.household_size} ppl</span>
                       <span class="text-orange-600 font-bold ml-auto">${primaryBags} bags</span>
                   </div>
                   ${reps.map(r =>
                       `<div class="flex items-center gap-1.5 text-xs text-gray-600">
                           <span class="text-amber-300 font-bold">↳</span>
                           <span>${r.household_size} ppl</span>
                           ${r.bags_needed != null ? `<span class="text-orange-600 font-bold ml-auto">${r.bags_needed} bags</span>` : ''}
                        </div>`
                   ).join('')}
               </div>`
            : '';

        return `
        <div class="visit-card rounded-2xl border-2 border-gray-200 bg-white p-4 select-none"
             data-id="${v.id}" data-lane="${v.lane}" data-updated-at="${esc(v.updated_at || '')}">
          <div class="flex items-start gap-3">
            <div class="drag-handle flex flex-col items-center gap-1.5 cursor-grab active:cursor-grabbing shrink-0 pt-0.5 touch-none">
              <span class="pos-num w-8 h-8 rounded-full bg-orange-500 text-white text-sm font-black flex items-center justify-center">${pos}</span>
              <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
              </svg>
            </div>

            <div class="flex-1 min-w-0">
              <p class="font-black text-gray-900 text-lg leading-tight">${esc(hh.vehicle_label || '—')}</p>
              <p class="text-sm font-mono text-gray-400 mt-0.5">#${esc(hh.household_number)} · Ln ${v.lane}</p>
              <div class="flex gap-3 mt-2.5">
                <div class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-center min-w-[4rem]">
                  <p class="text-xl font-black text-gray-900">${familyCount}</p>
                  <p class="text-xs text-gray-500">${familyCount === 1 ? 'Family' : 'Families'}</p>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-xl px-3 py-2 text-center min-w-[4rem] flex-1">
                  <p class="text-3xl font-black text-orange-700">${v.bags_needed}</p>
                  <p class="text-xs text-orange-600 font-semibold">Total Bags</p>
                </div>
              </div>
              ${repRows}
            </div>
          </div>

          <button onclick="window._markLoaded(${v.id}, this)"
                  class="mt-3 w-full py-4 rounded-2xl bg-green-600 hover:bg-green-700 active:bg-green-800 text-white font-black text-base transition-colors">
              Loading Complete
          </button>
        </div>`;
    }

    function renumber() {
        document.querySelectorAll('#loader-list .visit-card').forEach((el, i) => {
            const b = el.querySelector('.pos-num');
            if (b) b.textContent = i + 1;
        });
    }

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // ── Mark loaded ───────────────────────────────────────────────────────────
    window._markLoaded = async function(id, btn) {
        btn.disabled = true; btn.textContent = 'Saving…';
        try {
            const r = await fetch(`${LOADED_URL}/${id}/loaded`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (r.ok) {
                visits = visits.filter(v => v.id !== id);
                render();
                if (stats.queued > 0) stats.queued--;
                if (typeof stats.loaded === 'number') stats.loaded++;
                updateStats(stats);
            } else {
                btn.disabled = false; btn.textContent = 'Loading Complete';
            }
        } catch { btn.disabled = false; btn.textContent = 'Loading Complete'; }
    };

    // ── Reorder ───────────────────────────────────────────────────────────────
    async function sendReorder() {
        const moves = [];
        document.querySelectorAll('#loader-list .visit-card').forEach((el, i) => {
            moves.push({
                id:             parseInt(el.dataset.id),
                lane:           parseInt(el.dataset.lane),
                queue_position: i + 1,
                updated_at:     el.dataset.updatedAt || '',
            });
        });
        moves.forEach(m => {
            const v = visits.find(x => x.id === m.id);
            if (v) { v.queue_position = m.queue_position; }
        });
        pendingReorder = true;
        try {
            const r = await fetch(REORDER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ moves }),
            });
            if (r.status === 409) {
                // Stale optimistic-lock token: pull fresh data instead of fighting.
                pendingReorder = false;
                fetchData();
                return;
            }
            if (!r.ok) return;
            const body = await r.json();
            (body.visits || []).forEach(fresh => {
                const card = document.querySelector(`#loader-list .visit-card[data-id="${fresh.id}"]`);
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
                (active ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200');
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
