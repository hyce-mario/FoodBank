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
      x-data="poForm({{ $items->toJson() }})">
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

        <div class="space-y-3">
            <template x-for="(row, idx) in rows" :key="idx">
                <div class="grid grid-cols-12 gap-2 items-start">
                    <div class="col-span-12 sm:col-span-6">
                        <select :name="`items[${idx}][inventory_item_id]`" x-model.number="row.inventory_item_id" required
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            <option value="">Select item…</option>
                            <template x-for="item in items" :key="item.id">
                                <option :value="item.id" x-text="item.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="col-span-5 sm:col-span-2">
                        <input type="number" min="1" step="1" :name="`items[${idx}][quantity]`" x-model.number="row.quantity" required
                               placeholder="Qty"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg tabular-nums">
                    </div>
                    <div class="col-span-5 sm:col-span-2">
                        <input type="number" min="0" step="0.01" :name="`items[${idx}][unit_cost]`" x-model.number="row.unit_cost" required
                               placeholder="Unit $"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg tabular-nums">
                    </div>
                    <div class="col-span-1 sm:col-span-1 text-right text-sm font-semibold tabular-nums pt-2"
                         x-text="formatMoney((row.quantity || 0) * (row.unit_cost || 0))"></div>
                    <div class="col-span-1 sm:col-span-1 text-right pt-1">
                        <button type="button" @click="removeRow(idx)"
                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
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
function poForm(items) {
    return {
        items,
        rows: [{ inventory_item_id: '', quantity: 1, unit_cost: 0 }],
        addRow() { this.rows.push({ inventory_item_id: '', quantity: 1, unit_cost: 0 }); },
        removeRow(idx) { this.rows.splice(idx, 1); },
        total() { return this.rows.reduce((s, r) => s + ((r.quantity || 0) * (r.unit_cost || 0)), 0); },
        formatMoney(v) { return '{{ $financeSettings['currency_symbol'] ?? '$' }}' + (Number(v) || 0).toFixed(2); },
    };
}
</script>
@endpush

@endsection
