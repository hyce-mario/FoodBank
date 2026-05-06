@php
    /** @var array $data */
    $rate = round($data['distribution_rate'] * 100);
@endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Inventory</h2>
    <p class="text-sm text-gray-500 mb-5">Allocation and distribution summary across {{ $data['total_items'] }} item{{ $data['total_items'] === 1 ? '' : 's' }}.</p>

    @if ($data['total_items'] === 0)
        <div class="bg-gray-50 rounded-xl p-8 text-center">
            <p class="text-sm text-gray-500">No inventory was allocated to this event.</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl bg-blue-50 p-4">
                <p class="text-3xl font-black text-blue-700 tabular-nums">{{ number_format($data['total_allocated']) }}</p>
                <p class="text-xs font-semibold text-blue-600 mt-1">Allocated</p>
                <p class="text-[10px] text-blue-500 mt-0.5">Units assigned</p>
            </div>
            <div class="rounded-xl bg-emerald-50 p-4">
                <p class="text-3xl font-black text-emerald-700 tabular-nums">{{ number_format($data['total_distributed']) }}</p>
                <p class="text-xs font-semibold text-emerald-600 mt-1">Distributed</p>
                <p class="text-[10px] text-emerald-500 mt-0.5">Units given out</p>
            </div>
            <div class="rounded-xl bg-amber-50 p-4">
                <p class="text-3xl font-black text-amber-700 tabular-nums">{{ number_format($data['total_returned']) }}</p>
                <p class="text-xs font-semibold text-amber-600 mt-1">Returned</p>
                <p class="text-[10px] text-amber-500 mt-0.5">Back to stock</p>
            </div>
            <div class="rounded-xl bg-violet-50 p-4">
                <p class="text-3xl font-black text-violet-700 tabular-nums">{{ $rate }}<span class="text-lg">%</span></p>
                <p class="text-xs font-semibold text-violet-600 mt-1">Distribution Rate</p>
                <p class="text-[10px] text-violet-500 mt-0.5">Distributed ÷ allocated</p>
            </div>
        </div>
    @endif
</div>

@if ($data['total_items'] > 0)
<div class="bg-white border border-gray-200 rounded-2xl p-6 mt-5">
    <h3 class="text-sm font-bold text-gray-800 mb-4">Per-Item Distribution</h3>
    <div class="space-y-3">
        @foreach ($data['rows'] as $row)
            @php
                $itemRate = round($row['rate'] * 100);
                $color = $itemRate >= 85 ? 'bg-emerald-500' : ($itemRate >= 60 ? 'bg-amber-500' : 'bg-rose-500');
            @endphp
            <div>
                <div class="flex items-center justify-between text-sm mb-1.5">
                    <span class="font-medium text-gray-800 truncate pr-2">{{ $row['name'] }}</span>
                    <span class="text-xs text-gray-500 tabular-nums">
                        {{ number_format($row['distributed']) }} / {{ number_format($row['allocated']) }} {{ $row['unit'] }}
                        <span class="font-bold text-gray-800 ml-1">({{ $itemRate }}%)</span>
                    </span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $color }} rounded-full" style="width: {{ $itemRate }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
