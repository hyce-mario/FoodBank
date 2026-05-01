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
      x-data="poForm({{ $items->toJson() }}, {{ $categories->toJson() }})">
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
            <div class="w-9"></div>{{-- spacer for the quick-create + button --}}
            <div class="w-20 text-right">Qty</div>
            <div class="w-24 text-right">Unit Cost</div>
            <div class="w-24 text-right">Line Total</div>
            <div class="w-9"></div>{{-- spacer for the remove button --}}
        </div>

        <div class="space-y-2">
            <template x-for="(row, idx) in rows" :key="idx">
                <div class="flex items-center gap-2">
                    {{-- Searchable item combobox --}}
                    <div class="flex-1 min-w-0 relative" @click.away="row.dropdownOpen = false">
                        <input type="text" x-model="row.searchTerm"
                               @focus="row.dropdownOpen = true"
                               @keydown.escape="row.dropdownOpen = false"
                               placeholder="Search item by name…"
                               autocomplete="off"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white">
                        <input type="hidden" :name="`items[${idx}][inventory_item_id]`" :value="row.inventory_item_id">

                        {{-- Dropdown panel --}}
                        <div x-show="row.dropdownOpen" style="display:none"
                             class="absolute z-20 mt-1 left-0 right-0 max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg">
                            <template x-for="item in filteredItems(row.searchTerm)" :key="item.id">
                                <button type="button" @click="selectItem(idx, item)"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-brand-50 hover:text-brand-700 border-b border-gray-50 last:border-b-0">
                                    <span class="font-medium" x-text="item.name"></span>
                                    <span class="text-xs text-gray-400 ml-2"
                                          x-text="item.category ? '· ' + item.category.name : ''"></span>
                                </button>
                            </template>
                            <template x-if="filteredItems(row.searchTerm).length === 0">
                                <div class="px-3 py-3 text-sm text-gray-400 italic">No matching items</div>
                            </template>
                            {{-- Always-visible "Create new" footer — pre-fills name with the
                                 current search term so a missed search converts straight into
                                 a new item without retyping. --}}
                            <button type="button"
                                    @click="openQuickCreate(idx, row.searchTerm)"
                                    class="w-full text-left px-3 py-2.5 text-sm font-semibold text-brand-700 bg-brand-50 hover:bg-brand-100 border-t border-brand-100 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                <span>Create new item</span>
                                <span x-show="row.searchTerm.trim().length > 0" style="display:none"
                                      class="text-xs font-normal text-brand-500 ml-auto truncate"
                                      x-text="'“' + row.searchTerm + '”'"></span>
                            </button>
                        </div>
                    </div>
                    {{-- Quick-create icon button (always visible alongside search) --}}
                    <button type="button" @click="openQuickCreate(idx, row.searchTerm)"
                            title="Create new inventory item"
                            class="w-9 h-9 shrink-0 inline-flex items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 hover:bg-brand-100 hover:border-brand-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </button>
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

    {{-- ── Quick-Create Inventory Item Modal ─────────────────────────────────
         Sits inside the form's Alpine scope so it can read/write state and
         push the new item back into the line-item picker without a page
         reload. The form itself never submits when the modal is active —
         the modal POSTs to /inventory/items/quick-create separately. --}}
    <div x-show="showQuickCreate" style="display:none"
         x-transition.opacity
         @keydown.escape.window="closeQuickCreate()"
         class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 px-4 pt-16 pb-8 overflow-y-auto">
        <div @click.away="closeQuickCreate()"
             class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-gray-900">New Inventory Item</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Stock starts at 0 — this PO will fill it on receipt.</p>
                </div>
                <button type="button" @click="closeQuickCreate()"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" x-model="quickCreateForm.name"
                       :class="quickCreateErrors.name ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                       class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                       maxlength="150"
                       autocomplete="off"
                       x-ref="quickCreateName">
                <p x-show="quickCreateErrors.name" style="display:none" class="text-xs text-red-600 mt-1" x-text="quickCreateErrors.name"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Unit Type <span class="text-red-500">*</span></label>
                <select x-model="quickCreateForm.unit_type"
                        :class="quickCreateErrors.unit_type ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                        class="w-full px-3 py-2 text-sm border rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    <option value="">— Select unit —</option>
                    <template x-for="u in unitOptions" :key="u">
                        <option :value="u" x-text="u"></option>
                    </template>
                </select>
                <p x-show="quickCreateErrors.unit_type" style="display:none" class="text-xs text-red-600 mt-1" x-text="quickCreateErrors.unit_type"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Category</label>
                <select x-model="quickCreateForm.category_id"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    <option value="">— None —</option>
                    <template x-for="c in categories" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Description</label>
                <textarea x-model="quickCreateForm.description" rows="2" maxlength="5000"
                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/20"></textarea>
            </div>

            <p x-show="quickCreateErrors._form" style="display:none"
               class="text-xs text-red-600 px-3 py-2 bg-red-50 border border-red-200 rounded-lg"
               x-text="quickCreateErrors._form"></p>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="closeQuickCreate()"
                        :disabled="quickCreateSaving"
                        class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg disabled:opacity-40">Cancel</button>
                <button type="button" @click="submitQuickCreate()"
                        :disabled="quickCreateSaving || !quickCreateForm.name.trim() || !quickCreateForm.unit_type"
                        class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-lg disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!quickCreateSaving">Create &amp; add to PO</span>
                    <span x-show="quickCreateSaving" style="display:none">Saving…</span>
                </button>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
function poForm(items, categories) {
    const blankRow = () => ({
        inventory_item_id: '',
        searchTerm:        '',
        dropdownOpen:      false,
        quantity:          1,
        unit_cost:         0,
    });
    const blankQuickCreateForm = () => ({
        name:        '',
        unit_type:   '',
        category_id: '',
        description: '',
    });
    return {
        items,
        categories,
        rows: [blankRow()],

        // ── Quick-create modal state ─────────────────────────────────────
        showQuickCreate:    false,
        quickCreateRowIdx:  null,
        quickCreateForm:    blankQuickCreateForm(),
        quickCreateErrors:  {},
        quickCreateSaving:  false,
        // Mirrors the inventory item form's unit list verbatim — keep these
        // in sync with resources/views/inventory/items/_form.blade.php.
        unitOptions: ['Each', 'Box', 'Case', 'Bag', 'Pound', 'Ounce', 'Gallon', 'Liter', 'Pallet', 'Bundle'],

        addRow() { this.rows.push(blankRow()); },
        removeRow(idx) { this.rows.splice(idx, 1); },
        total() { return this.rows.reduce((s, r) => s + ((r.quantity || 0) * (r.unit_cost || 0)), 0); },
        formatMoney(v) { return '{{ $financeSettings['currency_symbol'] ?? '$' }}' + (Number(v) || 0).toFixed(2); },
        selectItem(idx, item) {
            this.rows[idx].inventory_item_id = item.id;
            this.rows[idx].searchTerm        = item.name;
            this.rows[idx].dropdownOpen      = false;
        },
        filteredItems(term) {
            const q = (term || '').trim().toLowerCase();
            if (!q) return this.items;
            return this.items.filter(i => {
                const haystack = (i.name + ' ' + (i.category?.name || '')).toLowerCase();
                return haystack.includes(q);
            });
        },

        // ── Quick-create flow ────────────────────────────────────────────
        openQuickCreate(idx, prefilledName) {
            this.quickCreateRowIdx = idx;
            this.quickCreateForm   = blankQuickCreateForm();
            this.quickCreateForm.name = (prefilledName || '').trim();
            this.quickCreateErrors = {};
            this.showQuickCreate   = true;
            // Close any open dropdown so the modal isn't fighting for focus.
            this.rows[idx].dropdownOpen = false;
            this.$nextTick(() => this.$refs.quickCreateName?.focus());
        },
        closeQuickCreate() {
            if (this.quickCreateSaving) return; // don't dismiss mid-save
            this.showQuickCreate   = false;
            this.quickCreateRowIdx = null;
            this.quickCreateErrors = {};
        },
        async submitQuickCreate() {
            if (this.quickCreateSaving) return;
            this.quickCreateErrors = {};
            this.quickCreateSaving = true;
            try {
                const res = await fetch('{{ route('inventory.items.quick-create') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.quickCreateForm),
                });
                if (res.status === 422) {
                    const body = await res.json();
                    // Flatten Laravel's {field: [msg]} into {field: msg}.
                    const errs = body.errors || {};
                    const flat = {};
                    Object.keys(errs).forEach(k => { flat[k] = Array.isArray(errs[k]) ? errs[k][0] : String(errs[k]); });
                    this.quickCreateErrors = flat;
                    return;
                }
                if (!res.ok) {
                    this.quickCreateErrors = { _form: 'Could not create the item. Please try again.' };
                    return;
                }
                const body = await res.json();
                const newItem = body.item;
                // Push into the local items list (sorted by name to match server).
                this.items.push(newItem);
                this.items.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                // Auto-select for the row that opened the modal, if it's still around.
                if (this.quickCreateRowIdx != null && this.rows[this.quickCreateRowIdx]) {
                    this.selectItem(this.quickCreateRowIdx, newItem);
                }
                this.showQuickCreate   = false;
                this.quickCreateRowIdx = null;
            } catch (e) {
                this.quickCreateErrors = { _form: 'Network error. Please check your connection and try again.' };
            } finally {
                this.quickCreateSaving = false;
            }
        },
    };
}
</script>
@endpush

@endsection
