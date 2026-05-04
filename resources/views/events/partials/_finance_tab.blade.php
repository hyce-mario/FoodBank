{{-- Finance Tab — injected into events/show.blade.php --}}

{{-- KPI Row --}}
<div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Income</p>
        <p class="text-xl font-bold text-green-600 tabular-nums">${{ number_format($eventFinanceKpis['income'], 2) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Expenses</p>
        <p class="text-xl font-bold text-red-600 tabular-nums">${{ number_format($eventFinanceKpis['expenses'], 2) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Net</p>
        @php $net = $eventFinanceKpis['net']; @endphp
        <p class="text-xl font-bold tabular-nums {{ $net >= 0 ? 'text-gray-900' : 'text-red-600' }}">
            ${{ number_format(abs($net), 2) }}
        </p>
    </div>
</div>

{{-- Transactions Card --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">
            Transactions
            <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $eventTransactions->count() }})</span>
        </h3>
        <a href="{{ route('finance.transactions.create', ['event_id' => $event->id, 'type' => 'expense']) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-lg px-3 py-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add
        </a>
    </div>

    @if($eventTransactions->isEmpty())
    <div class="py-12 text-center text-gray-400 text-sm">
        No transactions linked to this event.
        <a href="{{ route('finance.transactions.create', ['event_id' => $event->id, 'type' => 'expense']) }}"
           class="text-brand-600 hover:underline ml-1">Add one.</a>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Title</th>
                    <th class="px-3 py-3">Type</th>
                    <th class="px-3 py-3">Category</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($eventTransactions as $tx)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ $tx->transaction_date->format('M j, Y') }}</td>
                    <td class="px-5 py-3">
                        <a href="{{ route('finance.transactions.show', $tx) }}"
                           class="font-medium text-gray-900 hover:text-brand-600 transition-colors">
                            {{ $tx->title }}
                        </a>
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $tx->isIncome() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ ucfirst($tx->transaction_type) }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-gray-500">{{ $tx->category?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums
                        {{ $tx->isIncome() ? 'text-green-600' : 'text-red-600' }}">
                        {{ $tx->isIncome() ? '+' : '-' }}{{ $tx->formattedAmount() }}
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tx->statusBadgeClasses() }}">
                            {{ ucfirst($tx->status ?? 'completed') }}
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <a href="{{ route('finance.transactions.edit', $tx) }}"
                           class="text-xs text-gray-500 hover:text-gray-800 font-medium">Edit</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
