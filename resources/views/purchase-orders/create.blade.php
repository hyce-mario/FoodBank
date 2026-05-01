@extends('layouts.app')
@section('title', 'New Purchase Order')

@section('content')

<div class="flex items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">New Purchase Order</h1>
        <p class="text-xs text-gray-400 mt-0.5">Saves as a draft. Stock and the expense are posted only when you mark it received.</p>
    </div>
    <a href="{{ route('purchase-orders.index') }}"
       class="text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl px-4 py-2.5">
        Back
    </a>
</div>

<form method="POST" action="{{ route('purchase-orders.store') }}"
      x-data="poForm('{{ route('inventory.items.search') }}')">
    @csrf

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-5 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Supplier <span class="text-red-500">*</span></label>
                <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" required
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                @error('supplier_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Order Date <span class="text-red-500">*</span></label>
                <input type="date" name="order_date" value="{{ old('order_date', now()->toDateString()) }}" required
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                @error('order_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-500/20">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-800">Line Items</h2>
            <button type="button" @click="addRow()"
                    class="text-xs font-semibold bg-brand-500 hover:bg-brand-600 text-white px-3 py-1.5 rounded-lg">
                + Add Row
            </button>
        </div>

        <template x-if="rows.length === 0">
            <p class="text-sm text-gray-400 text-center py-6">No items yet. Click "Add Row" to begin.</p>
        </template>

        {{-- Column headers (hidden on mobile, visible on sm+ for clarity) --}}
        <div class="hidden sm:flex items-center gap-2 px-1 mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
            <div class="flex-1">Item</div>
            <div class="w-20 text-right">Qty</div>
            <div class="w-24 text-right">Unit Cost</div>
            <div class="w-24 text-right">Line Total</div>
            <div class="w-9"></div>
        </div>

        <div class="space-y-2">
            <template x-for="(row, idx) in rows" :key="idx">
                <div class="flex items-center gap-2">
                    {{-- Searchable item combobox (server-side typeahead) --}}
                    <div class="flex-1 min-w-0 relative" @click.away="row.dropdownOpen = false">
                        <input type="text" x-model="row.searchTerm"
                               @input.debounce.250ms="fetchItems(idx)"
                               @focus="openDropdown(idx)"
                               @keydown.escape="row.dropdownOpen = false"
                               placeholder="Search item by name…"
                               autocomplete="off"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white">
                        <input type="hidden" :name="`items[${idx}][inventory_item_id]`" :value="row.inventory_item_id">

                        {{-- Loading spinner inside the input on the right --}}
                        <div class="absolute inset-y-0 right-2 flex items-center pointer-events-none"
                             x-show="row.loading" style="display:none">
                            <svg class="w-4 h-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                        </div>

                        {{-- Dropdown panel --}}
                        <div x-show="row.dropdownOpen" style="display:none"
                             class="absolute z-20 mt-1 left-0 right-0 max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg">
                            <template x-for="item in row.results" :key="item.id">
                                <button type="button" @click="selectItem(idx, item)"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-brand-50 hover:text-brand-700 border-b border-gray-50 last:border-b-0">
                                    <span class="font-medium" x-text="item.name"></span>
                                    <span class="text-xs text-gray-400 ml-2"
                                          x-text="item.category ? '· ' + item.category.name : ''"></span>
                                </button>
                            </template>
                            <div x-show="!row.loading && row.results.length === 0"
                                 class="px-3 py-3 text-sm text-gray-400 italic" style="display:none">
                                No matching items
                            </div>
                            <div x-show="row.loading && row.results.length === 0"
                                 class="px-3 py-3 text-sm text-gray-400 italic" style="display:none">
                                Searching…
                            </div>
                        </div>
                    </div>
                    {{-- Qty --}}
                    <input type="number" min="1" step="1" :name="`items[${idx}][quantity]`" x-model.number="row.quantity" required
                           placeholder="Qty"
                           class="w-20 px-2 py-2 text-sm text-right border border-gray-300 rounded-lg tabular-nums">
                    {{-- Unit cost --}}
                    <input type="number" min="0" step="0.01" :name="`items[${idx}][unit_cost]`" x-model.number="row.unit_cost" required
                           placeholder="0.00"
                           class="w-24 px-2 py-2 text-sm text-right border border-gray-300 rounded-lg tabular-nums">
                    {{-- Line total (read-only, computed) --}}
                    <div class="w-24 text-right text-sm font-semibold tabular-nums text-gray-700"
                         x-text="formatMoney((row.quantity || 0) * (row.unit_cost || 0))"></div>
                    {{-- Remove --}}
                    <button type="button" @click="removeRow(idx)"
                            class="w-9 h-9 flex-shrink-0 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>

        <div class="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-gray-100">
            <span class="text-sm font-semibold text-gray-500">PO Total</span>
            <span class="text-xl font-black tabular-nums" x-text="formatMoney(total())"></span>
        </div>

        @error('items')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('purchase-orders.index') }}"
           class="px-6 py-3 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl">Cancel</a>
        <button type="submit"
                class="px-6 py-3 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl">
            Save as Draft
        </button>
    </div>
</form>

@push('scripts')
<script>
function poForm(searchUrl) {
    const blankRow = () => ({
        inventory_item_id: '',
        searchTerm:        '',
        dropdownOpen:      false,
        results:           [],   // most recent server response for this row
        loading:           false,
        _abort:            null, // AbortController for in-flight request
        quantity:          1,
        unit_cost:         0,
    });
    return {
        searchUrl,
        rows: [blankRow()],
        addRow() { this.rows.push(blankRow()); },
        removeRow(idx) {
            // Cancel any in-flight request before disposing the row
            const r = this.rows[idx];
            if (r?._abort) r._abort.abort();
            this.rows.splice(idx, 1);
        },
        total() { return this.rows.reduce((s, r) => s + ((r.quantity || 0) * (r.unit_cost || 0)), 0); },
        formatMoney(v) { return '{{ $financeSettings['currency_symbol'] ?? '$' }}' + (Number(v) || 0).toFixed(2); },
        selectItem(idx, item) {
            this.rows[idx].inventory_item_id = item.id;
            this.rows[idx].searchTerm        = item.name;
            this.rows[idx].dropdownOpen      = false;
        },
        // Open dropdown — if the row hasn't loaded results yet, fetch the
        // first page so the user sees options immediately on focus.
        openDropdown(idx) {
            this.rows[idx].dropdownOpen = true;
            if (this.rows[idx].results.length === 0 && !this.rows[idx].loading) {
                this.fetchItems(idx);
            }
        },
        // Debounced server fetch. Aborts any prior in-flight request for this
        // row so the freshest response always wins (no race on fast typing).
        async fetchItems(idx) {
            const row = this.rows[idx];
            if (!row) return;

            if (row._abort) row._abort.abort();
            row._abort  = new AbortController();
            row.loading = true;
            row.dropdownOpen = true;

            try {
                const url = this.searchUrl + '?q=' + encodeURIComponent(row.searchTerm || '');
                const res = await fetch(url, {
                    signal:  row._abort.signal,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('search failed');
                const data = await res.json();
                row.results = data.results || [];
            } catch (e) {
                if (e.name !== 'AbortError') {
                    row.results = [];
                }
            } finally {
                row.loading = false;
                row._abort  = null;
            }
        },
    };
}
</script>
@endpush

@endsection
