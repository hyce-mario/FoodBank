@extends('layouts.app')
@section('title', 'Reports — Inventory')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Inventory usage, distribution, and waste tracking</p>
    </div>
    <div class="flex items-center gap-2">
        @can('reports.export')
        <a href="{{ route('reports.download', array_merge(request()->only(['preset','date_from','date_to']), ['type' => 'inventory'])) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200
                  rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors"
           title="Download per-event item usage as CSV">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            Export CSV
        </a>
        @endcan
        <a href="{{ route('inventory.items.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200
                  rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
            </svg>
            View Inventory
        </a>
    </div>
</div>

{{-- Sub-nav --}}
@include('reports._nav')

{{-- Filter --}}
@include('reports._filter', ['formAction' => route('reports.inventory')])

@php
    $hasData = $summary['items_active'] > 0
        || $summary['total_allocated'] > 0
        || $summary['total_stock_in'] > 0;
@endphp

@if (!$hasData)
{{-- ── Empty state ─────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
    </svg>
    <p class="text-sm font-medium text-gray-500">No inventory movements for this period.</p>
    <p class="text-xs text-gray-400 mt-1">Try a wider date range, or <a href="{{ route('inventory.items.index') }}" class="text-brand-500 hover:underline">add stock movements</a> first.</p>
</div>
@else

{{-- ── KPI Cards ───────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-5">
    <div class="bg-navy-700 text-white rounded-2xl px-4 py-4 col-span-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Items Active</p>
        <p class="text-2xl font-bold tabular-nums">{{ number_format($summary['items_active']) }}</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-4 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Stock In</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($summary['total_stock_in']) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">units received</p>
    </div>
    <div class="bg-purple-50 border border-purple-100 rounded-2xl px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-purple-600 mb-1">Allocated</p>
        <p class="text-2xl font-bold text-purple-900 tabular-nums">{{ number_format($summary['total_allocated']) }}</p>
        <p class="text-xs text-purple-500 mt-0.5">to events</p>
    </div>
    <div class="bg-green-50 border border-green-100 rounded-2xl px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-green-600 mb-1">Distributed</p>
        <p class="text-2xl font-bold text-green-900 tabular-nums">{{ number_format($summary['total_distributed']) }}</p>
        <p class="text-xs text-green-500 mt-0.5">to households</p>
    </div>
    <div class="bg-blue-50 border border-blue-100 rounded-2xl px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 mb-1">Returned</p>
        <p class="text-2xl font-bold text-blue-900 tabular-nums">{{ number_format($summary['total_returned']) }}</p>
        <p class="text-xs text-blue-500 mt-0.5">back to shelf</p>
    </div>
    <div class="bg-red-50 border border-red-100 rounded-2xl px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-red-600 mb-1">Waste &amp; Loss</p>
        <p class="text-2xl font-bold text-red-900 tabular-nums">{{ number_format($summary['total_waste']) }}</p>
        <p class="text-xs text-red-500 mt-0.5">damaged + expired + removed</p>
    </div>
</div>

{{-- ── Charts Row ───────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 xl:grid-cols-5 gap-5 mb-5">

    {{-- Distribution over time (line chart) ──────────────────────────── --}}
    <div class="xl:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-0.5">Distribution Over Time</h3>
        <p class="text-xs text-gray-400 mb-4">Units allocated vs returned per period</p>
        @if (count($timeChart['labels']) > 0)
            <div class="h-56">
                <canvas id="timeChart"></canvas>
            </div>
        @else
            <div class="h-56 flex items-center justify-center text-sm text-gray-400">No movement data for this period.</div>
        @endif
    </div>

    {{-- Top Items (horizontal bar chart) ────────────────────────────── --}}
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-0.5">Top Items Distributed</h3>
        <p class="text-xs text-gray-400 mb-4">By units distributed to households</p>
        @if ($topItems->isNotEmpty())
            <div class="h-56">
                <canvas id="topItemsChart"></canvas>
            </div>
        @else
            <div class="h-56 flex items-center justify-center text-sm text-gray-400">No allocations in this period.</div>
        @endif
    </div>
</div>

{{-- ── Most Distributed Items Table ────────────────────────────────────── --}}
@if ($topItems->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold text-gray-800">Most Distributed Items</h3>
            <p class="text-xs text-gray-400 mt-0.5">Top {{ $topItems->count() }} items by units distributed</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Category</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Allocated</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Distributed</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Returned</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Utilisation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($topItems as $i => $item)
                @php
                    $util = $item->total_allocated > 0
                        ? round(($item->total_distributed / $item->total_allocated) * 100)
                        : 0;
                    $utilColor = $util >= 80 ? 'text-green-600' : ($util >= 50 ? 'text-amber-600' : 'text-red-600');
                @endphp
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3 text-xs font-bold text-gray-400 tabular-nums">{{ $i + 1 }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('inventory.items.show', $item->id) }}"
                           class="font-semibold text-gray-800 hover:text-brand-600 transition-colors">
                            {{ $item->name }}
                        </a>
                        <p class="text-xs text-gray-400">{{ $item->unit_type }}</p>
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <span class="text-xs text-gray-600">{{ $item->category_name ?? '—' }}</span>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-700">{{ number_format($item->total_allocated) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold text-green-700">{{ number_format($item->total_distributed) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-blue-600 hidden md:table-cell">{{ number_format($item->total_returned) }}</td>
                    <td class="px-4 py-3 text-right hidden lg:table-cell">
                        <span class="text-sm font-bold {{ $utilColor }}">{{ $util }}%</span>
                        <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden w-16 ml-auto">
                            <div class="h-full rounded-full {{ $util >= 80 ? 'bg-green-500' : ($util >= 50 ? 'bg-amber-400' : 'bg-red-400') }}"
                                 style="width: {{ $util }}%"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Event Inventory Usage Table ──────────────────────────────────────── --}}
@if ($eventUsage->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Inventory Usage Per Event</h3>
        <p class="text-xs text-gray-400 mt-0.5">{{ $eventUsage->count() }} events with inventory allocations</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Event</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Date</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Items</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Allocated</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Distributed</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Returned</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Remaining</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($eventUsage as $row)
                @php
                    $remaining = max(0, (int) $row->total_remaining);
                    $statusBadge = match ($row->event_status) {
                        'current'  => 'bg-green-100 text-green-700',
                        'past'     => 'bg-gray-100 text-gray-500',
                        default    => 'bg-blue-100 text-blue-700',
                    };
                    $statusLabel = match ($row->event_status) {
                        'current'  => 'Active',
                        'past'     => 'Completed',
                        default    => 'Upcoming',
                    };
                @endphp
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3.5">
                        <a href="{{ route('events.show', $row->event_id) }}"
                           class="font-semibold text-gray-800 hover:text-brand-600 transition-colors">
                            {{ $row->event_name }}
                        </a>
                    </td>
                    <td class="px-4 py-3.5 hidden sm:table-cell">
                        <span class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($row->event_date)->format('M j, Y') }}</span>
                    </td>
                    <td class="px-4 py-3.5 text-right tabular-nums text-gray-700 font-medium">{{ $row->item_count }}</td>
                    <td class="px-4 py-3.5 text-right tabular-nums text-gray-700">{{ number_format($row->total_allocated) }}</td>
                    <td class="px-4 py-3.5 text-right tabular-nums font-bold text-green-700">{{ number_format($row->total_distributed) }}</td>
                    <td class="px-4 py-3.5 text-right tabular-nums text-blue-600 hidden md:table-cell">{{ number_format($row->total_returned) }}</td>
                    <td class="px-4 py-3.5 text-right hidden lg:table-cell">
                        <span class="tabular-nums {{ $remaining > 0 ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">
                            {{ number_format($remaining) }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 hidden sm:table-cell">
                        <span class="inline-flex text-xs font-semibold px-2 py-0.5 rounded-full {{ $statusBadge }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Waste / Loss Breakdown ───────────────────────────────────────────── --}}
@if ($wasteItems->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
        </div>
        <div>
            <h3 class="text-sm font-bold text-gray-800">Waste &amp; Loss Breakdown</h3>
            <p class="text-xs text-gray-400 mt-0.5">Damaged, expired, and manually removed stock</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Category</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Damaged</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Expired</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Removed</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Lost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($wasteItems as $row)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3">
                        <a href="{{ route('inventory.items.show', $row->id) }}"
                           class="font-semibold text-gray-800 hover:text-brand-600 transition-colors">
                            {{ $row->name }}
                        </a>
                        <p class="text-xs text-gray-400">{{ $row->unit_type }}</p>
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell text-xs text-gray-600">{{ $row->category_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-orange-600 font-medium">{{ number_format($row->damaged_qty) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 font-medium">{{ number_format($row->expired_qty) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 hidden md:table-cell">{{ number_format($row->stock_out_qty) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold text-red-700">{{ number_format($row->total_waste) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-200">
                <tr class="bg-gray-50/60">
                    <td colspan="2" class="px-5 py-3 text-xs font-bold text-gray-600 uppercase tracking-wide hidden sm:table-cell">Total</td>
                    <td class="px-5 py-3 text-xs font-bold text-gray-600 uppercase tracking-wide sm:hidden">Total</td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold text-orange-600">{{ number_format($wasteItems->sum('damaged_qty')) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-700">{{ number_format($wasteItems->sum('expired_qty')) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-700 hidden md:table-cell">{{ number_format($wasteItems->sum('stock_out_qty')) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold text-red-700">{{ number_format($wasteItems->sum('total_waste')) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

@endif {{-- end hasData --}}

@endsection

@push('scripts')
@if ($hasData ?? false)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const navy   = '#1e3a5f';
    const green  = '#16a34a';
    const blue   = '#2563eb';
    const orange = '#f97316';
    const grid   = '#F3F4F6';
    const textC  = '#6B7280';
    const tooltip = {
        backgroundColor: '#1F2937', padding: 8, cornerRadius: 8,
        titleFont: { size: 12 }, bodyFont: { size: 12 },
    };
    const baseScales = {
        x: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 } } },
        y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, precision: 0 }, beginAtZero: true },
    };

    // ── Distribution Over Time ────────────────────────────────────────────────
    const T = @json($timeChart);
    const timeEl = document.getElementById('timeChart');
    if (timeEl && T.labels.length > 0) {
        new Chart(timeEl, {
            type: 'line',
            data: {
                labels: T.labels,
                datasets: [
                    {
                        label: 'Allocated',
                        data: T.allocated,
                        borderColor: navy, backgroundColor: navy + '18',
                        fill: true, tension: 0.35,
                        pointRadius: T.labels.length < 25 ? 3 : 1,
                        pointBackgroundColor: navy, borderWidth: 2,
                    },
                    {
                        label: 'Returned',
                        data: T.returned,
                        borderColor: blue, backgroundColor: blue + '12',
                        fill: true, tension: 0.35,
                        pointRadius: T.labels.length < 25 ? 3 : 1,
                        pointBackgroundColor: blue, borderWidth: 2,
                        borderDash: [4, 3],
                    },
                    {
                        label: 'Stock In',
                        data: T.stockIn,
                        borderColor: green, backgroundColor: green + '12',
                        fill: false, tension: 0.35,
                        pointRadius: T.labels.length < 25 ? 3 : 1,
                        pointBackgroundColor: green, borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: textC } },
                    tooltip: { ...tooltip, mode: 'index', intersect: false },
                },
                scales: baseScales,
            },
        });
    }

    // ── Top Items Horizontal Bar ──────────────────────────────────────────────
    const C = @json($chartData);
    const topEl = document.getElementById('topItemsChart');
    if (topEl && C.labels.length > 0) {
        new Chart(topEl, {
            type: 'bar',
            data: {
                labels: C.labels,
                datasets: [
                    {
                        label: 'Distributed',
                        data: C.distributed,
                        backgroundColor: green,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Allocated',
                        data: C.allocated,
                        backgroundColor: navy + '40',
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 11 }, color: textC } },
                    tooltip: { ...tooltip },
                },
                scales: {
                    x: { ...baseScales.x, stacked: false },
                    y: { grid: { display: false }, ticks: { color: textC, font: { size: 10 } } },
                },
            },
        });
    }
});
</script>
@endif
@endpush
