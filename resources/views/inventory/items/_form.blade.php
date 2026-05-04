{{--
    Shared form partial for create & edit.
    Expects: $item (InventoryItem|null), $categories (Collection), $action (string), $method ('POST'|'PUT')
--}}
@php
    $unitOptions = ['Each', 'Box', 'Case', 'Bag', 'Pound', 'Ounce', 'Gallon', 'Liter', 'Pallet', 'Bundle'];
@endphp

<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($method === 'PUT') @method('PUT') @endif

    {{-- ── Basic Information ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-6 py-4 border-b border-gray-100">
            <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                </svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-800">Item Details</h2>
        </div>

        <div class="px-6 py-6 space-y-5">

            {{-- Name --}}
            <div>
                <label for="name" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                    Item Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="name" name="name"
                       value="{{ old('name', $item->name ?? '') }}"
                       placeholder="e.g. White Rice 10lb"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              @error('name') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                @error('name')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- SKU + Category (2-col on sm+) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- SKU --}}
                <div>
                    <label for="sku" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        SKU <span class="text-gray-400 font-normal normal-case">(optional)</span>
                    </label>
                    <input type="text" id="sku" name="sku"
                           value="{{ old('sku', $item->sku ?? '') }}"
                           placeholder="e.g. GR-RICE-10"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  @error('sku') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                    @error('sku')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label for="category_id" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Category
                    </label>
                    <select id="category_id" name="category_id"
                            class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50 cursor-pointer
                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                   @error('category_id') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                        <option value="">— No category —</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}"
                                @selected(old('category_id', $item->category_id ?? '') == $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Unit type --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="unit_type" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Unit Type <span class="text-red-500">*</span>
                    </label>
                    <select id="unit_type" name="unit_type"
                            class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50 cursor-pointer
                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                   @error('unit_type') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                        <option value="">— Select unit —</option>
                        @foreach ($unitOptions as $unit)
                            <option value="{{ $unit }}"
                                @selected(old('unit_type', $item->unit_type ?? '') === $unit)>
                                {{ $unit }}
                            </option>
                        @endforeach
                    </select>
                    @error('unit_type')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                    Description <span class="text-gray-400 font-normal normal-case">(optional)</span>
                </label>
                <textarea id="description" name="description" rows="3"
                          placeholder="Brief notes about this item..."
                          class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50 resize-y
                                 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                 @error('description') border-red-400 bg-red-50 @else border-gray-200 @enderror">{{ old('description', $item->description ?? '') }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- ── Dates ────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-5">
        <div class="flex items-center gap-2.5 px-6 py-4 border-b border-gray-100">
            <div class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-800">Manufacturing &amp; Expiry Dates</h2>
            <span class="text-xs text-gray-400 font-normal">(optional)</span>
        </div>

        <div class="px-6 py-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- Manufacturing Date --}}
                <div>
                    <label for="manufacturing_date" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Manufacturing Date
                    </label>
                    <input type="date" id="manufacturing_date" name="manufacturing_date"
                           value="{{ old('manufacturing_date', isset($item->manufacturing_date) ? $item->manufacturing_date->format('Y-m-d') : '') }}"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  @error('manufacturing_date') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                    @error('manufacturing_date')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Expiry Date --}}
                <div>
                    <label for="expiry_date" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Expiry Date
                    </label>
                    <input type="date" id="expiry_date" name="expiry_date"
                           value="{{ old('expiry_date', isset($item->expiry_date) ? $item->expiry_date->format('Y-m-d') : '') }}"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  @error('expiry_date') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                    <p class="mt-1 text-xs text-gray-400">Must not be earlier than the manufacturing date if both are set.</p>
                    @error('expiry_date')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>
    </div>

    {{-- ── Stock & Status ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-5">
        <div class="flex items-center gap-2.5 px-6 py-4 border-b border-gray-100">
            <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                </svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-800">Stock & Status</h2>
        </div>

        <div class="px-6 py-6 space-y-5">

            {{-- Qty on hand + Reorder level --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="quantity_on_hand" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Quantity on Hand <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="quantity_on_hand" name="quantity_on_hand"
                           value="{{ old('quantity_on_hand', $item->quantity_on_hand ?? 0) }}"
                           min="0" step="1"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  @error('quantity_on_hand') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                    @error('quantity_on_hand')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="reorder_level" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Reorder Level <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="reorder_level" name="reorder_level"
                           value="{{ old('reorder_level', $item->reorder_level ?? 0) }}"
                           min="0" step="1"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  @error('reorder_level') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                    <p class="mt-1 text-xs text-gray-400">Alert triggers when quantity falls to or below this value. Set 0 to disable.</p>
                    @error('reorder_level')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Active toggle --}}
            <div class="flex items-center gap-3 pt-1">
                <button type="button" role="switch"
                        x-data="{ on: {{ old('is_active', ($item->is_active ?? true) ? 'true' : 'false') }} }"
                        @click="on = !on"
                        :aria-checked="on.toString()"
                        :class="on ? 'bg-brand-500' : 'bg-gray-300'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full
                               border-2 border-transparent transition-colors duration-200 ease-in-out
                               focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                    <span :class="on ? 'translate-x-5' : 'translate-x-0'"
                          class="pointer-events-none inline-block h-5 w-5 transform rounded-full
                                 bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    <input type="hidden" name="is_active" :value="on ? '1' : '0'">
                </button>
                <span class="text-sm text-gray-700 font-medium">Active item</span>
                <span class="text-xs text-gray-400">(Inactive items are hidden from lists by default)</span>
            </div>

        </div>
    </div>

    {{-- ── Actions ───────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-end gap-3 mt-6">
        <a href="{{ route('inventory.items.index') }}"
           class="px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200
                  rounded-lg hover:bg-gray-50 hover:text-gray-800 transition-colors">
            Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
                       font-semibold text-sm rounded-lg px-5 py-2.5 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            {{ $submitLabel ?? 'Save Item' }}
        </button>
    </div>

</form>
