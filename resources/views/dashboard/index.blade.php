@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- ═══════════════════════════════════════════════════════
     GREETING HEADER
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex items-center justify-between shadow-sm border border-gray-100">
    <div class="flex items-center gap-2.5">
        <span class="text-2xl">👋</span>
        <div>
            <h1 class="text-lg font-bold text-gray-900">Hi, {{ auth()->user()->name }}</h1>
            <p class="text-xs text-gray-400 mt-0.5">Welcome back to your dashboard</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-1.5 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <span class="font-medium text-gray-700">{{ now()->format('d M Y') }}</span>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     STAT CARDS
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">

    {{-- 1. Total food bundles served --}}
    <x-stat-card
        label="Food Bundles Served"
        value="{{ number_format($stats['total_bundles']) }}"
        change="{{ abs($stats['bundles_change']) }}%"
        :up="$stats['bundles_up']"
        icon="gift"
        variant="white"
    />

    {{-- 2. Households served --}}
    <x-stat-card
        label="Households Served"
        value="{{ number_format($stats['households_served']) }}"
        change="of {{ number_format($stats['total_households']) }} registered"
        :plain="true"
        icon="home"
        variant="orange"
    />

    {{-- 3. People served --}}
    <x-stat-card
        label="People Served"
        value="{{ number_format($stats['people_served']) }}"
        icon="people"
        variant="navy"
    />

    {{-- 4. Volunteers --}}
    <x-stat-card
        label="Volunteers"
        value="{{ number_format($stats['volunteers']) }}"
        icon="volunteer"
        variant="light"
    />
</div>

{{-- ═══════════════════════════════════════════════════════
     TODAY'S EVENT / UPCOMING EVENT BANNER
═══════════════════════════════════════════════════════ --}}
@if($currentEvent)
<div class="bg-green-600 rounded-2xl px-5 py-4 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
        </div>
        <div>
            <p class="text-xs font-semibold text-green-100 uppercase tracking-wide">Event in Progress Today</p>
            <p class="text-base font-bold text-white">{{ $currentEvent->name }}</p>
            <p class="text-xs text-green-100 mt-0.5">
                {{ $currentEvent->active_count }} active &middot; {{ $currentEvent->exited_count }} served &middot; {{ $currentEvent->total_visits }} total check-ins
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('checkin.index') }}"
           class="inline-flex items-center gap-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
            Check-in
        </a>
        <a href="{{ route('monitor.index') }}"
           class="inline-flex items-center gap-1.5 bg-white text-green-700 hover:bg-green-50 text-xs font-semibold px-3 py-2 rounded-lg transition">
            Live Monitor
        </a>
        <a href="{{ route('events.show', $currentEvent) }}"
           class="inline-flex items-center gap-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
            View Event
        </a>
    </div>
</div>
@elseif($nextEvent)
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Next Upcoming Event</p>
            <p class="text-base font-bold text-gray-900">{{ $nextEvent->name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $nextEvent->date->format('l, M j, Y') }}
                &middot; {{ $nextEvent->assigned_volunteers_count }} {{ Str::plural('volunteer', $nextEvent->assigned_volunteers_count) }} assigned
            </p>
        </div>
    </div>
    <a href="{{ route('events.show', $nextEvent) }}"
       class="inline-flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition flex-shrink-0">
        View Event
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
    </a>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════
     CHARTS ROW
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-5">

    {{-- Monthly Distribution (area chart) --}}
    <div class="xl:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Monthly Distribution</h2>
            <div class="flex items-center gap-1.5 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                <span class="font-medium">{{ now()->year }}</span>
            </div>
        </div>
        <div class="h-56">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    {{-- Household size distribution (donut chart) --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Household Sizes</h2>
                <p class="text-xs text-gray-400 mt-0.5">All registered households</p>
            </div>
        </div>

        <div class="flex justify-center mb-4">
            <div class="relative h-44 w-44">
                <canvas id="householdChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-bold text-gray-900">{{ number_format($sizeData['total']) }}</span>
                    <span class="text-xs text-gray-400">Total</span>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                    <span class="text-gray-600 text-xs">1-2 People</span>
                </div>
                <span class="font-semibold text-gray-900 text-xs">
                    {{ $sizeData['pct']['small'] }}%
                    <span class="text-gray-400 font-normal">({{ $sizeData['small'] }})</span>
                </span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-navy-700"></div>
                    <span class="text-gray-600 text-xs">3-4 People</span>
                </div>
                <span class="font-semibold text-gray-900 text-xs">
                    {{ $sizeData['pct']['medium'] }}%
                    <span class="text-gray-400 font-normal">({{ $sizeData['medium'] }})</span>
                </span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-brand-500"></div>
                    <span class="text-gray-600 text-xs">5+ People</span>
                </div>
                <span class="font-semibold text-gray-900 text-xs">
                    {{ $sizeData['pct']['large'] }}%
                    <span class="text-gray-400 font-normal">({{ $sizeData['large'] }})</span>
                </span>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     RECENT EVENTS
═══════════════════════════════════════════════════════ --}}
@if($recentEvents->isNotEmpty())
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-5 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-bold text-gray-900">Recent Events</h2>
        <a href="{{ route('events.index') }}"
           class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">
            View all →
        </a>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($recentEvents as $event)
        <div class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors group">
            <div class="flex-shrink-0 text-center w-10">
                <p class="text-xs font-bold text-gray-800">{{ $event->date->format('d') }}</p>
                <p class="text-[10px] text-gray-400 uppercase">{{ $event->date->format('M') }}</p>
            </div>
            <div class="flex-1 min-w-0">
                <a href="{{ route('events.show', $event) }}"
                   class="text-sm font-semibold text-gray-800 group-hover:text-brand-600 transition-colors truncate block">
                    {{ $event->name }}
                </a>
                <p class="text-xs text-gray-400 truncate">{{ $event->location ?? '—' }}</p>
            </div>
            <div class="text-right flex-shrink-0">
                @if($event->exited_count > 0)
                    <p class="text-sm font-bold text-gray-800">{{ number_format($event->exited_count) }}</p>
                    <p class="text-xs text-gray-400">served</p>
                @else
                    <p class="text-xs text-gray-400">No data</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════
     INVENTORY STOCK ALERTS
═══════════════════════════════════════════════════════ --}}
@if($outOfStockItems->isNotEmpty() || $lowStockItems->isNotEmpty())
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-5 overflow-hidden">

    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-gray-900">Inventory Alerts</h2>
                <p class="text-xs text-gray-400">
                    {{ $outOfStockItems->count() }} out of stock &middot; {{ $lowStockItems->count() }} low stock
                </p>
            </div>
        </div>
        <a href="{{ route('inventory.items.index') }}"
           class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">
            View all inventory →
        </a>
    </div>

    <div class="divide-y divide-gray-100">
        @foreach($outOfStockItems as $item)
        <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors group">
            <div class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></div>
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.items.show', $item) }}"
                   class="text-sm font-semibold text-gray-800 group-hover:text-brand-600 transition-colors truncate block">
                    {{ $item->name }}
                </a>
                <p class="text-xs text-gray-400 truncate">{{ $item->category?->name ?? 'Uncategorized' }}</p>
            </div>
            <div class="text-right flex-shrink-0">
                <span class="text-sm font-bold text-red-600 tabular-nums">0</span>
                <span class="text-xs text-gray-400 ml-0.5">{{ $item->unit_type }}</span>
            </div>
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-700 flex-shrink-0">
                Out of Stock
            </span>
        </div>
        @endforeach

        @foreach($lowStockItems as $item)
        <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors group">
            <div class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></div>
            <div class="flex-1 min-w-0">
                <a href="{{ route('inventory.items.show', $item) }}"
                   class="text-sm font-semibold text-gray-800 group-hover:text-brand-600 transition-colors truncate block">
                    {{ $item->name }}
                </a>
                <p class="text-xs text-gray-400 truncate">{{ $item->category?->name ?? 'Uncategorized' }}</p>
            </div>
            <div class="text-right flex-shrink-0">
                <span class="text-sm font-bold text-amber-600 tabular-nums">{{ number_format($item->quantity_on_hand) }}</span>
                <span class="text-xs text-gray-400 ml-0.5">{{ $item->unit_type }}</span>
                <p class="text-xs text-gray-400">reorder at {{ $item->reorder_level }}</p>
            </div>
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 flex-shrink-0">
                Low Stock
            </span>
        </div>
        @endforeach
    </div>

    <div class="flex items-center gap-4 px-5 py-3 border-t border-gray-100 bg-gray-50/60">
        @if($outOfStockItems->isNotEmpty())
        <a href="{{ route('inventory.items.index', ['status' => 'out']) }}"
           class="text-xs font-semibold text-red-600 hover:text-red-700 transition-colors">
            View {{ $outOfStockItems->count() }} out of stock →
        </a>
        @endif
        @if($lowStockItems->isNotEmpty())
        <a href="{{ route('inventory.items.index', ['status' => 'low']) }}"
           class="text-xs font-semibold text-amber-600 hover:text-amber-700 transition-colors">
            View {{ $lowStockItems->count() }} low stock →
        </a>
        @endif
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════
     QUICK ACTIONS
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
    <h2 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

        <a href="{{ route('events.create') }}" class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">Create Event</span>
        </a>

        <a href="{{ route('households.create') }}" class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">Add Household</span>
        </a>

        <a href="{{ route('checkin.index') }}" class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">Check-in</span>
        </a>

        <a href="{{ route('reports.overview') }}" class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">Reports</span>
        </a>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const monthLabels = @json($monthLabels);
    const monthlyData = @json($monthlyData);

    // ── Monthly Distribution (Area Chart) ──────────────────────────────────
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Food Bundles Distributed',
                    data: monthlyData,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.08)',
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#f97316',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1b2b4b',
                        titleColor: '#fff',
                        bodyColor: '#d1d5db',
                        padding: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y.toLocaleString()} bundles`
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { color: '#9ca3af', font: { size: 11 }, maxTicksLimit: 6 },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9ca3af', font: { size: 11 } },
                        border: { display: false }
                    }
                }
            }
        });
    }

    // ── Household Size Distribution (Donut Chart) ───────────────────────────
    const householdCtx = document.getElementById('householdChart');
    if (householdCtx) {
        new Chart(householdCtx, {
            type: 'doughnut',
            data: {
                labels: ['1-2 People', '3-4 People', '5+ People'],
                datasets: [{
                    data: [
                        {{ $sizeData['small'] }},
                        {{ $sizeData['medium'] }},
                        {{ $sizeData['large'] }}
                    ],
                    backgroundColor: ['#e5e7eb', '#1b2b4b', '#f97316'],
                    borderWidth: 0,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1b2b4b',
                        titleColor: '#fff',
                        bodyColor: '#d1d5db',
                        padding: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} households`
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
