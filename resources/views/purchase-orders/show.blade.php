@extends('layouts.app')
@section('title', $po->po_number)

@section('content')

<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-gray-900">{{ $po->po_number }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $po->statusBadgeClasses() }}">
                {{ ucfirst($po->status) }}
            </span>
        </div>
        <p class="text-sm text-gray-500 mt-0.5">{{ $po->supplier_name }} · ordered {{ $po->order_date?->format('M j, Y') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('purchase-orders.print', $po) }}" target="_blank"
           class="inline-flex items-center gap-1.5 text-sm font-semibold border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 rounded-xl px-4 py-2.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
            </svg>
            Print
        </a>
        <a href="{{ route('purchase-orders.index') }}"
           class="text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl px-4 py-2.5">Back</a>
    </div>
</div>

@if (session('success'))
<div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3 mb-4">{{ session('success') }}</div>
@endif
@if (session('error'))
<div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">{{ session('error') }}</div>
@endif

{{-- Items --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/40 text-sm font-semibold text-gray-700">Line Items</div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                <th class="px-5 py-2.5 text-left">Item</th>
                <th class="px-5 py-2.5 text-right">Qty</th>
                <th class="px-5 py-2.5 text-right">Unit Cost</th>
                <th class="px-5 py-2.5 text-right">Line Total</th>
                <th class="px-5 py-2.5 text-right">Stock Movement</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($po->items as $line)
            <tr>
                <td class="px-5 py-3">
                    <span class="font-medium text-gray-900">{{ $line->item?->name ?? '—' }}</span>
                    @if ($line->item?->category)<span class="text-xs text-gray-400 ml-2">({{ $line->item->category->name }})</span>@endif
                </td>
                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($line->quantity) }}</td>
                <td class="px-5 py-3 text-right tabular-nums">{{ fmt_currency((float) $line->unit_cost) }}</td>
                <td class="px-5 py-3 text-right tabular-nums font-semibold">{{ fmt_currency((float) $line->line_total) }}</td>
                <td class="px-5 py-3 text-right">
                    @if ($line->inventory_movement_id)
                        <span class="inline-flex items-center gap-1 text-xs text-green-700">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            Posted #{{ $line->inventory_movement_id }}
                        </span>
                    @else
                        <span class="text-xs text-gray-400">Pending</span>
                    @endif
                </td>
            </tr>
            @endforeach
            <tr class="bg-gray-50">
                <td colspan="3" class="px-5 py-3 text-right font-semibold text-gray-700">PO Total</td>
                <td class="px-5 py-3 text-right tabular-nums text-lg font-black">{{ fmt_currency((float) $po->total_amount) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Linked finance transaction --}}
@if ($po->financeTransaction)
<div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-5">
    <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Linked Expense</p>
    <p class="text-sm text-blue-900">
        {{ $po->financeTransaction->title }} —
        <strong>{{ fmt_currency((float) $po->financeTransaction->amount) }}</strong>
        on {{ $po->financeTransaction->transaction_date?->format('M j, Y') }}
        @if ($po->financeTransaction->category)
            · {{ $po->financeTransaction->category->name }}
        @endif
    </p>
</div>
@endif

@if ($po->notes)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</p>
    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $po->notes }}</p>
</div>
@endif

{{-- Actions --}}
@if ($po->isDraft())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <h2 class="text-sm font-semibold text-gray-800 mb-2">Mark as Received</h2>
    <p class="text-xs text-gray-500 mb-4">
        Posts <strong>{{ $po->items->count() }}</strong> stock_in inventory movement{{ $po->items->count() === 1 ? '' : 's' }}
        and creates a single expense transaction for {{ fmt_currency((float) $po->total_amount) }}. Atomic — both land or neither.
    </p>
    <form method="POST" action="{{ route('purchase-orders.receive', $po) }}" class="flex flex-wrap items-center gap-3">
        @csrf
        <select name="finance_category_id" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
            <option value="">Default (Inventory Purchases)</option>
            @foreach ($expenseCategories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="text-sm font-semibold bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
            Mark Received
        </button>
    </form>

    <form method="POST" action="{{ route('purchase-orders.cancel', $po) }}" class="mt-3"
          onsubmit="return confirm('Cancel this draft purchase order?')">
        @csrf
        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700 underline">Cancel this PO</button>
    </form>
</div>
@elseif ($po->status === 'received')
<div class="bg-green-50 border border-green-200 rounded-2xl p-4">
    <p class="text-sm text-green-800">
        Received {{ $po->received_date?->format('M j, Y') }} — stock and expense have been posted.
    </p>
</div>
@elseif ($po->status === 'cancelled')
<div class="bg-gray-50 border border-gray-200 rounded-2xl p-4">
    <p class="text-sm text-gray-700">This purchase order was cancelled. No inventory or finance records were created.</p>
</div>
@endif

@endsection
