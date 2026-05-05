@extends('layouts.app')
@section('title', 'Inventory')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Inventory</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Inventory</span>
        </nav>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('inventory.categories.index') }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900
                  bg-white border border-gray-200 rounded-lg px-4 py-2 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
            </svg>
            Manage Categories
        </a>
        <a href="{{ route('inventory.items.create') }}"
           class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
                  font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add Item
        </a>
    </div>
</div>

{{-- Flash --}}
@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- ── Summary Stats ─────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-5">
    <a href="{{ route('inventory.items.index') }}"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 hover:border-brand-300 transition-colors group">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Active Items</p>
        <p class="text-2xl font-bold text-gray-900 group-hover:text-brand-600 transition-colors">{{ $totalActive }}</p>
    </a>
    <a href="{{ route('inventory.items.index', ['status' => 'low']) }}"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 hover:border-amber-300 transition-colors group">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Low Stock</p>
        <p class="text-2xl font-bold {{ $lowStockCount > 0 ? 'text-amber-600' : 'text-gray-900' }} group-hover:text-amber-600 transition-colors">{{ $lowStockCount }}</p>
    </a>
    <a href="{{ route('inventory.items.index', ['status' => 'out']) }}"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 hover:border-red-300 transition-colors group">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Out of Stock</p>
        <p class="text-2xl font-bold {{ $outOfStock > 0 ? 'text-red-600' : 'text-gray-900' }} group-hover:text-red-600 transition-colors">{{ $outOfStock }}</p>
    </a>
    <a href="{{ route('inventory.items.index', ['status' => 'expiring_soon']) }}"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 hover:border-amber-300 transition-colors group">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Expiring Soon</p>
        <p class="text-2xl font-bold {{ $expiringSoon > 0 ? 'text-amber-600' : 'text-gray-900' }} group-hover:text-amber-600 transition-colors">{{ $expiringSoon }}</p>
    </a>
    <a href="{{ route('inventory.items.index', ['status' => 'expired']) }}"
       class="bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-4 hover:border-red-300 transition-colors group">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Expired</p>
        <p class="text-2xl font-bold {{ $expiredCount > 0 ? 'text-red-600' : 'text-gray-900' }} group-hover:text-red-600 transition-colors">{{ $expiredCount }}</p>
    </a>
</div>

{{-- ── Main Table Card ───────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Toolbar --}}
    <form method="GET" action="{{ route('inventory.items.index') }}"
          class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">

        {{-- Search --}}
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search items..."
                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                          placeholder:text-gray-400">
        </div>

        {{-- Category filter --}}
        <select name="category"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50
                       focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600 cursor-pointer min-w-[140px]">
            <option value="">All Categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>

        {{-- Status filter --}}
        <select name="status"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50
                       focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600 cursor-pointer min-w-[150px]">
            <option value="">Active Items</option>
            <option value="low"           @selected(request('status') === 'low')>Low Stock</option>
            <option value="out"           @selected(request('status') === 'out')>Out of Stock</option>
            <option value="expiring_soon" @selected(request('status') === 'expiring_soon')>Expiring Soon</option>
            <option value="expired"       @selected(request('status') === 'expired')>Expired</option>
            <option value="inactive"      @selected(request('status') === 'inactive')>Inactive</option>
        </select>

        @if (request()->hasAny(['search', 'category', 'status']))
            <a href="{{ route('inventory.items.index') }}"
               class="text-xs text-gray-500 hover:text-gray-700 px-2 py-2 hover:bg-gray-100 rounded-lg transition-colors">
                Clear
            </a>
        @endif

        {{-- Apply current filter inputs --}}
        <button type="submit"
                class="inline-flex items-center gap-1.5 text-sm font-semibold bg-brand-500 hover:bg-brand-600
                       text-white rounded-lg px-4 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
            </svg>
            Filter
        </button>

        {{-- formaction submits the live form inputs to print/export, so they pick up unsaved typing. --}}
        <button type="submit"
                formaction="{{ route('inventory.items.print') }}"
                formtarget="_blank"
                aria-label="Print current view"
                title="Print"
                class="inline-flex items-center justify-center w-9 h-9 text-gray-600 hover:text-gray-900
                       bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
            </svg>
        </button>

        <button type="submit"
                formaction="{{ route('inventory.items.export') }}"
                aria-label="Download CSV of current view"
                title="Export CSV"
                class="inline-flex items-center justify-center w-9 h-9 text-gray-600 hover:text-gray-900
                       bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
        </button>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Category</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Unit</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Mfg Date</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Expiry Date</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($items as $item)
                <tr class="hover:bg-gray-50/70 transition-colors group">
                    {{-- Name + SKU --}}
                    <td class="px-5 py-3.5">
                        <a href="{{ route('inventory.items.show', $item) }}"
                           class="font-semibold text-gray-800 group-hover:text-brand-600 transition-colors">
                            {{ $item->name }}
                        </a>
                        @if ($item->sku)
                            <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $item->sku }}</p>
                        @endif
                    </td>
                    {{-- Category --}}
                    <td class="px-4 py-3.5 hidden sm:table-cell">
                        <span class="text-sm text-gray-600">{{ $item->category?->name ?? '—' }}</span>
                    </td>
                    {{-- Unit --}}
                    <td class="px-4 py-3.5 hidden md:table-cell">
                        <span class="text-sm text-gray-600">{{ $item->unit_type }}</span>
                    </td>
                    {{-- Qty --}}
                    <td class="px-4 py-3.5 text-right">
                        <span class="font-semibold text-gray-800 tabular-nums">{{ number_format($item->quantity_on_hand) }}</span>
                    </td>
                    {{-- Mfg Date --}}
                    <td class="px-4 py-3.5 hidden lg:table-cell">
                        <span class="text-sm text-gray-600">
                            {{ $item->manufacturing_date?->format('M j, Y') ?? '—' }}
                        </span>
                    </td>
                    {{-- Expiry Date + badge --}}
                    <td class="px-4 py-3.5 hidden lg:table-cell">
                        @if ($item->expiry_date)
                            <span class="text-sm text-gray-600">{{ $item->expiry_date->format('M j, Y') }}</span>
                            @if ($item->expiryLabel())
                                <span class="ml-1.5 inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $item->expiryBadgeClasses() }}">
                                    {{ $item->expiryLabel() }}
                                </span>
                            @endif
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </td>
                    {{-- Stock badge --}}
                    <td class="px-4 py-3.5">
                        <div class="flex flex-wrap gap-1">
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full {{ $item->stockBadgeClasses() }}">
                                {{ $item->stockLabel() }}
                            </span>
                            @if ($item->expiryLabel())
                                <span class="lg:hidden inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full {{ $item->expiryBadgeClasses() }}">
                                    {{ $item->expiryLabel() }}
                                </span>
                            @endif
                        </div>
                    </td>
                    {{-- Actions --}}
                    <td class="px-4 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="{{ route('inventory.items.edit', $item) }}"
                               class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400
                                      hover:text-brand-600 hover:bg-brand-50 transition-colors"
                               title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('inventory.items.destroy', $item) }}"
                                  onsubmit="return confirm('Delete \'{{ addslashes($item->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400
                                               hover:text-red-600 hover:bg-red-50 transition-colors"
                                        title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-2 text-gray-400">
                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-500">No items found</p>
                            <p class="text-xs">
                                @if (request()->hasAny(['search', 'category', 'status']))
                                    Try adjusting your filters.
                                @else
                                    <a href="{{ route('inventory.items.create') }}" class="text-brand-500 hover:underline">Add your first item</a>
                                @endif
                            </p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($items->hasPages())
    <div class="px-5 py-4 border-t border-gray-100">
        {{ $items->links() }}
    </div>
    @endif
</div>

@endsection
