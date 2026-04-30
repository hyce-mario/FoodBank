@extends('layouts.event-day')

@section('title', 'Exit — ' . $event->name)

@section('header')
<header class="bg-green-600 shrink-0 px-4 py-4 flex items-center justify-between shadow-md">
    <div>
        <p class="text-xs text-green-200 uppercase tracking-widest font-semibold">Exit Station</p>
        <h1 class="text-white text-xl font-black leading-tight mt-0.5">{{ $event->name }}</h1>
        <p class="text-green-200 text-xs mt-0.5">
            {{ $event->date->format('l, F j') }}{{ $event->location ? ' · ' . $event->location : '' }}
        </p>
    </div>
    <span id="ed-clock" class="text-white text-sm font-bold tabular-nums">{{ now()->format('g:i A') }}</span>
</header>
@endsection

@section('content')
<div class="p-4 max-w-lg mx-auto">

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
            <p class="text-3xl font-black text-orange-600" data-stat="loaded">—</p>
            <p class="text-xs text-gray-500 font-medium mt-1">Ready to Exit</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
            <p class="text-3xl font-black text-green-600" data-stat="served">—</p>
            <p class="text-xs text-gray-500 font-medium mt-1">Served Today</p>
        </div>
    </div>

    <div id="exit-list" class="space-y-3"></div>
    <p id="exit-empty" class="hidden text-center text-gray-400 text-sm py-14">
        No vehicles ready to exit
    </p>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const CSRF      = document.querySelector('meta[name="csrf-token"]').content;
    // Use Blade-rendered absolute URLs so subdir deployments
    // (e.g. http://localhost/Foodbank/public/...) resolve correctly.
    const DATA_URL  = "{{ route('event-day.exit.data', $event) }}";
    const EXIT_URL  = "{{ url('/ed/' . $event->id . '/visits') }}";

    let visits = [];
    let stats  = {};

    function render() {
        const list  = document.getElementById('exit-list');
        const empty = document.getElementById('exit-empty');

        const loaded = visits.filter(v => v.visit_status === 'loaded');

        if (loaded.length === 0) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            list.innerHTML = loaded.map(v => cardHtml(v)).join('');
        }
    }

    function cardHtml(v) {
        const hh    = v.household;
        const isRep = v.is_representative_pickup;
        const reps  = v.represented_households || [];

        const repBadge = isRep
            ? `<p class="text-xs font-bold text-amber-700 mt-1">★ Rep Pickup · ${reps.length + 1} households</p>`
            : '';

        // Compact per-household bag breakdown for rep pickups
        const repBagsSum  = reps.reduce((s, r) => s + (r.bags_needed || 0), 0);
        const primaryBags = v.bags_needed - repBagsSum;
        const familyCount = isRep ? reps.length + 1 : 1;
        const repDetails = isRep && reps.length > 0
            ? `<div class="mt-2 pt-2 border-t border-green-100 space-y-1">
                   <div class="flex items-center gap-1.5 text-xs text-gray-500">
                       <span class="text-amber-400 font-bold">★</span>
                       <span>${hh.household_size} ppl</span>
                       <span class="font-bold ml-auto">${primaryBags} bags</span>
                   </div>
                   ${reps.map(r =>
                       `<div class="flex items-center gap-1.5 text-xs text-gray-500">
                           <span class="text-amber-300 font-bold">↳</span>
                           <span>${r.household_size} ppl</span>
                           ${r.bags_needed != null ? `<span class="font-bold ml-auto">${r.bags_needed} bags</span>` : ''}
                        </div>`
                   ).join('')}
               </div>`
            : '';

        return `
        <div class="rounded-2xl border-2 border-green-300 bg-white p-4" data-id="${v.id}">
          <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0 flex-1">
              <p class="font-black text-gray-900 text-2xl leading-tight">${esc(hh.vehicle_label || '—')}</p>
              <p class="text-sm font-mono text-gray-400 mt-1">#${esc(hh.household_number)} · Lane ${v.lane}</p>
              ${repBadge}
            </div>
            <div class="text-right shrink-0">
              <p class="text-4xl font-black text-green-700">${v.bags_needed}</p>
              <p class="text-xs text-green-600 font-semibold uppercase tracking-wide">bags total</p>
            </div>
          </div>

          <div class="flex gap-3 mb-3">
            <div class="flex-1 bg-gray-50 border border-gray-200 rounded-xl py-3 text-center">
              <p class="text-2xl font-black text-gray-900">${familyCount}</p>
              <p class="text-xs text-gray-500 font-medium">${familyCount === 1 ? 'Family' : 'Families'}</p>
            </div>
            <div class="flex-1 bg-gray-50 border border-gray-200 rounded-xl py-3 text-center">
              <p class="text-lg font-black text-gray-700">Ln ${v.lane}</p>
              <p class="text-xs text-gray-500 font-medium">Lane</p>
            </div>
          </div>

          ${repDetails}

          <button onclick="window._markExited(${v.id}, this)"
                  class="mt-3 w-full py-5 rounded-2xl bg-green-600 hover:bg-green-700 active:bg-green-800 text-white font-black text-xl transition-colors">
              Served &amp; Exit
          </button>
        </div>`;
    }

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    window._markExited = async function(id, btn) {
        btn.disabled = true; btn.textContent = 'Recording…';
        try {
            const r = await fetch(`${EXIT_URL}/${id}/exited`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (r.ok) {
                visits = visits.filter(v => v.id !== id);
                if (stats.loaded > 0) stats.loaded--;
                stats.served = (stats.served || 0) + 1;
                render();
                updateStats(stats);
                // Brief flash
                document.getElementById('exit-empty').textContent = 'Vehicle served!';
                setTimeout(() => {
                    document.getElementById('exit-empty').textContent = 'No vehicles ready to exit';
                }, 1500);
            } else {
                btn.disabled = false; btn.textContent = 'Served & Exit';
            }
        } catch { btn.disabled = false; btn.textContent = 'Served & Exit'; }
    };

    async function fetchData() {
        try {
            const r = await fetch(DATA_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) return;
            const data = await r.json();
            stats  = data.stats;
            visits = Object.values(data.lanes).flat();
            render();
            updateStats(stats);
        } catch {}
    }

    function updateStats(s) {
        document.querySelectorAll('[data-stat]').forEach(el => {
            el.textContent = s[el.dataset.stat] ?? 0;
        });
    }

    fetchData();
    setInterval(fetchData, 8000);
})();
</script>
@endpush
