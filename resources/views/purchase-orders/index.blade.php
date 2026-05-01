@extends('layouts.app')
@section('title', 'Purchase Orders')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Purchase Orders</h1>
        <p class="text-xs text-gray-400 mt-0.5">Inventory acquisitions linked to finance transactions</p>
    </div>
    <a href="{{ route('purchase-orders.create') }}"
       class="inline-flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        New Purchase Order
    </a>
</div>

@if (session('success'))
<div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3 mb-4">
    {{ session('success') }}
</div>
@endif
@if (session('error'))
<div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
    {{ session('error') }}
</div>
@endif

<form method="GET" class="bg-white rounded-2xl border border-gray-100 px-4 py-3 mb-5 flex flex-wrap items-center gap-2">
    <input type="search" name="search" value="{{ request('search') }}"
           placeholder="PO number or supplier"
           class="flex-1 min-w-[200px] text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
    <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50">
        <option value="">All statuses</option>
        @foreach (\App\Models\PurchaseOrder::STATUSES as $s)
        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button type="submit" class="text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white px-4 py-2 rounded-lg">Filter</button>
    @if (request('search') || request('status'))
        <a href="{{ route('purchase-orders.index') }}" class="text-xs text-gray-500 hover:text-gray-700 px-2 py-2">Clear</a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    @if ($orders->isEmpty())
    <div class="px-5 py-14 text-center text-sm text-gray-400">No purchase orders yet.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">PO</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Supplier</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Order Date</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Items</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($orders as $po)
                <tr class="hover:bg-gray-50/70">
                    <td class="px-5 py-3 font-mono font-semibold text-gray-900">{{ $po->po_number }}</td>
                    <td class="px-5 py-3 text-gray-700">{{ $po->supplier_name }}</td>
                    <td class="px-5 py-3 text-gray-500">{{ $po->order_date?->format('M j, Y') }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ $po->items_count }}</td>
                    <td class="px-5 py-3 text-right tabular-nums font-semibold">{{ fmt_currency((float) $po->total_amount) }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $po->statusBadgeClasses() }}">
                            {{ ucfirst($po->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('purchase-orders.show', $po) }}"
                           class="text-xs font-semibold text-brand-600 hover:text-brand-700">View →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())
    <div class="px-5 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
    @endif
    @endif
</div>

@endsection
