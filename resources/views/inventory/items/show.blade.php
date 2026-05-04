@extends('layouts.app')
@section('title', $item->name)

@section('content')

{{-- ═══ Alpine root — manages all three modals ════════════════════════════ --}}
<div x-data="{
        modal: null,
        open(m) { this.modal = m; },
        close() { this.modal = null; }
    }"
     @keydown.escape.window="close()">

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">{{ $item->name }}</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('inventory.items.index') }}" class="hover:text-brand-500">Inventory</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">{{ $item->name }}</span>
        </nav>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        {{-- Stock action buttons --}}
        <button @click="open('add')"
                class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white
                       font-semibold text-sm rounded-lg px-3.5 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add Stock
        </button>
        <button @click="open('remove')"
                class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white
                       font-semibold text-sm rounded-lg px-3.5 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
            </svg>
            Remove Stock
        </button>
        <button @click="open('adjust')"
                class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white
                       font-semibold text-sm rounded-lg px-3.5 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
            </svg>
            Adjust Stock
        </button>
        {{-- Edit / Delete --}}
        <a href="{{ route('inventory.items.edit', $item) }}"
           class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:border-gray-300
                  text-gray-700 font-medium text-sm rounded-lg px-3.5 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
            </svg>
            Edit
        </a>
        <form method="POST" action="{{ route('inventory.items.destroy', $item) }}"
              onsubmit="return confirm('Delete \'{{ addslashes($item->name) }}\'? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-1.5 bg-white border border-red-200 hover:border-red-400
                           text-red-600 font-medium text-sm rounded-lg px-3.5 py-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                </svg>
                Delete
            </button>
        </form>
    </div>
</div>

{{-- Flash --}}
@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif
@if (session('movement_error'))
<div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
    {{ session('movement_error') }}
</div>
@endif

{{-- ── Detail section ───────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Left: Stock level + Status ───────────────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-4">

        {{-- Stock level card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Stock Level</p>
            <div class="flex items-end gap-3">
                <span class="text-4xl font-bold text-gray-900">{{ number_format($item->quantity_on_hand) }}</span>
                <span class="text-base text-gray-500 mb-1">{{ $item->unit_type }}</span>
            </div>
            <div class="mt-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full {{ $item->stockBadgeClasses() }}">
                    @if ($item->stockStatus() === 'out')
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    @elseif ($item->stockStatus() === 'low')
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    @else
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    @endif
                    {{ $item->stockLabel() }}
                </span>
            </div>
            @if ($item->reorder_level > 0)
                <p class="mt-3 text-xs text-gray-400">Reorder at: <span class="font-semibold text-gray-600">{{ $item->reorder_level }} {{ $item->unit_type }}</span></p>
            @else
                <p class="mt-3 text-xs text-gray-400">No reorder threshold set.</p>
            @endif
        </div>

        {{-- Status card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Status</p>
            @if ($item->is_active)
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-green-100 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                </span>
            @endif
        </div>

        {{-- Expiry card (shown when expiry_date is set) --}}
        @if ($item->expiry_date)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 {{ $item->expiryStatus() === 'expired' ? 'border-red-200' : ($item->expiryStatus() === 'expiring_soon' ? 'border-amber-200' : '') }}">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Expiry</p>
            <p class="text-sm font-semibold text-gray-800 mb-2">{{ $item->expiry_date->format('M j, Y') }}</p>
            @if ($item->expiryStatus() === 'expired')
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-700">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Expired
                </span>
                <p class="mt-2 text-xs text-red-500">Expired {{ now()->diffInDays($item->expiry_date) }} day(s) ago.</p>
            @elseif ($item->expiryStatus() === 'expiring_soon')
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                    Expiring Soon
                </span>
                <p class="mt-2 text-xs text-amber-600">Expires in {{ now()->diffInDays($item->expiry_date) }} day(s).</p>
            @else
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-green-100 text-green-700">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Good
                </span>
                <p class="mt-2 text-xs text-gray-400">Expires in {{ now()->diffInDays($item->expiry_date) }} day(s).</p>
            @endif
        </div>
        @endif
    </div>

    {{-- Right: Item details ───────────────────────────────────────────────── --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Item Details</h2>
        </div>
        <dl class="divide-y divide-gray-100">
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Name</dt>
                <dd class="col-span-2 text-sm text-gray-800 font-medium">{{ $item->name }}</dd>
            </div>
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">SKU</dt>
                <dd class="col-span-2 text-sm text-gray-800 font-mono">{{ $item->sku ?? '—' }}</dd>
            </div>
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Category</dt>
                <dd class="col-span-2 text-sm text-gray-800">{{ $item->category?->name ?? '—' }}</dd>
            </div>
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Unit Type</dt>
                <dd class="col-span-2 text-sm text-gray-800">{{ $item->unit_type }}</dd>
            </div>
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Qty on Hand</dt>
                <dd class="col-span-2 text-sm text-gray-800 font-semibold">{{ number_format($item->quantity_on_hand) }} {{ $item->unit_type }}</dd>
            </div>
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Reorder Level</dt>
                <dd class="col-span-2 text-sm text-gray-800">{{ $item->reorder_level > 0 ? $item->reorder_level . ' ' . $item->unit_type : 'Not set' }}</dd>
            </div>
            @if ($item->description)
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</dt>
                <dd class="col-span-2 text-sm text-gray-700 leading-relaxed">{{ $item->description }}</dd>
            </div>
            @endif
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Mfg Date</dt>
                <dd class="col-span-2 text-sm text-gray-800">
                    {{ $item->manufacturing_date?->format('M j, Y') ?? '—' }}
                </dd>
            </div>
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Expiry Date</dt>
                <dd class="col-span-2 text-sm flex items-center gap-2 flex-wrap">
                    @if ($item->expiry_date)
                        <span class="text-gray-800">{{ $item->expiry_date->format('M j, Y') }}</span>
                        @if ($item->expiryLabel())
                            <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full {{ $item->expiryBadgeClasses() }}">
                                {{ $item->expiryLabel() }}
                            </span>
                        @endif
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </dd>
            </div>
            <div class="px-6 py-3.5 grid grid-cols-3 gap-4">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide self-center">Added</dt>
                <dd class="col-span-2 text-sm text-gray-500">{{ $item->created_at->format('M j, Y') }}</dd>
            </div>
        </dl>
    </div>
</div>

{{-- ── Movement History ─────────────────────────────────────────────────── --}}
<div class="mt-6 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Header + filters --}}
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800 mb-3">Movement History</h2>
        <form method="GET" action="{{ route('inventory.items.show', $item) }}"
              class="flex flex-wrap items-center gap-2">

            <select name="movement_type"
                    class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 cursor-pointer
                           focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600 min-w-[160px]">
                <option value="">All Types</option>
                @foreach ($movementTypes as $value => $label)
                    <option value="{{ $value }}" @selected(request('movement_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600">

            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 text-gray-600">

            <button type="submit"
                    class="px-3.5 py-2 text-sm font-medium bg-gray-100 hover:bg-gray-200
                           text-gray-700 rounded-lg transition-colors">
                Filter
            </button>

            @if (request()->hasAny(['movement_type', 'date_from', 'date_to']))
                <a href="{{ route('inventory.items.show', $item) }}"
                   class="text-xs text-gray-500 hover:text-gray-700 px-2 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date & Time</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Event</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">By</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($movements as $movement)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3">
                        <span class="text-sm text-gray-800">{{ $movement->created_at->format('M j, Y') }}</span>
                        <span class="block text-xs text-gray-400">{{ $movement->created_at->format('g:i A') }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full {{ $movement->typeBadgeClasses() }}">
                            {{ $movement->typeLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        <span class="{{ $movement->quantityClasses() }}">
                            {{ $movement->quantityDisplay() }}
                        </span>
                        <span class="text-xs text-gray-400 ml-0.5">{{ $item->unit_type }}</span>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                        @if ($movement->event)
                            <a href="{{ route('events.show', $movement->event) }}"
                               class="text-xs text-brand-600 hover:underline font-medium">
                                {{ $movement->event->name }}
                            </a>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <span class="text-xs text-gray-600">{{ $movement->user?->name ?? 'System' }}</span>
                    </td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                        <span class="text-xs text-gray-500">{{ $movement->notes ?? '—' }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-gray-400">
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-500">No movements recorded yet</p>
                            <p class="text-xs text-gray-400">Use Add Stock to record the first entry.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($movements->hasPages())
    <div class="px-5 py-4 border-t border-gray-100">
        {{ $movements->links() }}
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════════════════ --}}

{{-- Shared backdrop --}}
<div x-show="modal !== null"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-40"
     @click="close()"
     style="display:none">
</div>

{{-- ── ADD STOCK modal ───────────────────────────────────────────────── --}}
<div x-show="modal === 'add'"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none"
     @click.self="close()">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl" @click.stop>
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                </div>
                <h2 class="text-base font-bold text-gray-900">Add Stock</h2>
            </div>
            <button @click="close()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        {{-- Body --}}
        <form method="POST" action="{{ route('inventory.movements.store', $item) }}">
            @csrf
            <input type="hidden" name="action" value="add">
            <div class="px-6 py-5 space-y-4">
                <div>
                    <p class="text-sm text-gray-500 mb-4">
                        Current stock: <span class="font-semibold text-gray-800">{{ number_format($item->quantity_on_hand) }} {{ $item->unit_type }}</span>
                    </p>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Quantity to Add <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="quantity" min="1" step="1"
                               value="{{ old('quantity') }}"
                               placeholder="0"
                               class="flex-1 px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-green-500/20 focus:border-green-400"
                               required>
                        <span class="text-sm text-gray-500 font-medium">{{ $item->unit_type }}</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Notes</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Received from food drive..."
                              class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 resize-none
                                     focus:outline-none focus:ring-2 focus:ring-green-500/20 focus:border-green-400"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" @click="close()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 hover:bg-green-700
                               text-white text-sm font-semibold rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Confirm Add
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── REMOVE STOCK modal ────────────────────────────────────────────── --}}
<div x-show="modal === 'remove'"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none"
     @click.self="close()">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl" @click.stop>
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                    </svg>
                </div>
                <h2 class="text-base font-bold text-gray-900">Remove Stock</h2>
            </div>
            <button @click="close()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        {{-- Body --}}
        <form method="POST" action="{{ route('inventory.movements.store', $item) }}">
            @csrf
            <input type="hidden" name="action" value="remove">
            <div class="px-6 py-5 space-y-4">
                <p class="text-sm text-gray-500">
                    Current stock: <span class="font-semibold text-gray-800">{{ number_format($item->quantity_on_hand) }} {{ $item->unit_type }}</span>
                </p>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-2" x-data="{ reason: 'stock_out' }">
                        @foreach (['stock_out' => 'Stock Out', 'damaged' => 'Damaged', 'expired' => 'Expired'] as $val => $lbl)
                        <label class="flex flex-col items-center gap-1.5 p-3 border-2 rounded-xl cursor-pointer transition-colors"
                               x-data
                               :class="$root.querySelector('input[name=movement_type]:checked')?.value === '{{ $val }}'
                                       ? 'border-red-400 bg-red-50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="movement_type" value="{{ $val }}"
                                   {{ $val === 'stock_out' ? 'checked' : '' }}
                                   class="sr-only">
                            <span class="text-xs font-semibold text-gray-700">{{ $lbl }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Quantity to Remove <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="quantity" min="1" max="{{ $item->quantity_on_hand }}" step="1"
                               placeholder="0"
                               class="flex-1 px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-400"
                               required>
                        <span class="text-sm text-gray-500 font-medium">{{ $item->unit_type }}</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Notes</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Found damaged in storage..."
                              class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 resize-none
                                     focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-400"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" @click="close()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-red-600 hover:bg-red-700
                               text-white text-sm font-semibold rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Confirm Remove
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── ADJUST STOCK modal ────────────────────────────────────────────── --}}
<div x-show="modal === 'adjust'"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none"
     @click.self="close()">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl" @click.stop>
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
                    </svg>
                </div>
                <h2 class="text-base font-bold text-gray-900">Adjust Stock</h2>
            </div>
            <button @click="close()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        {{-- Body --}}
        <form method="POST" action="{{ route('inventory.movements.store', $item) }}"
              x-data="{ newQty: {{ $item->quantity_on_hand }} }">
            @csrf
            <input type="hidden" name="action" value="adjust">
            <div class="px-6 py-5 space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-700">
                    Use this to correct stock after a physical count. The difference will be recorded as an adjustment.
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Current Stock
                    </label>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($item->quantity_on_hand) }} <span class="text-base text-gray-500 font-normal">{{ $item->unit_type }}</span></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        New (Correct) Quantity <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="quantity" min="0" step="1"
                               x-model.number="newQty"
                               class="flex-1 px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400"
                               required>
                        <span class="text-sm text-gray-500 font-medium">{{ $item->unit_type }}</span>
                    </div>
                    <p class="mt-1.5 text-xs"
                       :class="newQty - {{ $item->quantity_on_hand }} > 0 ? 'text-green-600' :
                               (newQty - {{ $item->quantity_on_hand }} < 0 ? 'text-red-600' : 'text-gray-400')">
                        <span x-text="newQty - {{ $item->quantity_on_hand }} > 0
                            ? 'Will add ' + (newQty - {{ $item->quantity_on_hand }}) + ' {{ $item->unit_type }}'
                            : (newQty - {{ $item->quantity_on_hand }} < 0
                                ? 'Will remove ' + Math.abs(newQty - {{ $item->quantity_on_hand }}) + ' {{ $item->unit_type }}'
                                : 'No change')">
                        </span>
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        Reason / Notes <span class="text-red-500">*</span>
                    </label>
                    <textarea name="notes" rows="2" placeholder="e.g. Physical count on Apr 15 — correcting discrepancy..."
                              required
                              class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 resize-none
                                     focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" @click="close()"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700
                               text-white text-sm font-semibold rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Confirm Adjustment
                </button>
            </div>
        </form>
    </div>
</div>

</div>{{-- end x-data root --}}
@endsection
