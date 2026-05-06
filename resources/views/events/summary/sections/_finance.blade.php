@php
    /** @var array|null $data */
    if (! $data || ! empty($data['gated'])) {
        $gated = true;
    } else {
        $gated = false;
    }
@endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Finance</h2>
    <p class="text-sm text-gray-500 mb-5">Income and expense recorded against this event ("completed" status only).</p>

    @if ($gated)
        <div class="bg-gray-50 rounded-xl p-8 text-center">
            <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
            <p class="text-sm text-gray-500">Finance data requires the <code class="bg-gray-200 px-1.5 py-0.5 rounded text-xs">finance.view</code> permission.</p>
        </div>
    @else
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-emerald-50 p-4">
                <p class="text-2xl font-black text-emerald-700 tabular-nums">${{ number_format($data['income']['total'], 2) }}</p>
                <p class="text-xs font-semibold text-emerald-600 mt-1">Income</p>
            </div>
            <div class="rounded-xl bg-rose-50 p-4">
                <p class="text-2xl font-black text-rose-700 tabular-nums">${{ number_format($data['expense']['total'], 2) }}</p>
                <p class="text-xs font-semibold text-rose-600 mt-1">Expense</p>
            </div>
            <div class="rounded-xl {{ $data['net'] >= 0 ? 'bg-blue-50' : 'bg-amber-50' }} p-4">
                <p class="text-2xl font-black {{ $data['net'] >= 0 ? 'text-blue-700' : 'text-amber-700' }} tabular-nums">
                    {{ $data['net'] >= 0 ? '+' : '−' }}${{ number_format(abs($data['net']), 2) }}
                </p>
                <p class="text-xs font-semibold {{ $data['net'] >= 0 ? 'text-blue-600' : 'text-amber-600' }} mt-1">Net Result</p>
            </div>
        </div>
    @endif
</div>

@if (! $gated && ($data['income']['total'] > 0 || $data['expense']['total'] > 0))
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
    @foreach ([
        ['kind' => 'income',  'title' => 'Top Income Sources',  'bar' => 'bg-emerald-500'],
        ['kind' => 'expense', 'title' => 'Top Expense Sources', 'bar' => 'bg-rose-500'],
    ] as $meta)
        @php $kind = $meta['kind']; @endphp
        <div class="bg-white border border-gray-200 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-gray-800 mb-4">{{ $meta['title'] }}</h3>
            @if ($data[$kind]['total'] <= 0)
                <p class="text-sm text-gray-400">No {{ $kind }} recorded for this event.</p>
            @else
                <div class="space-y-3">
                    @foreach ($data[$kind]['top_sources'] as $src)
                        @php $pct = round($src['pct'] * 100); @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1.5">
                                <span class="text-gray-700">{{ $src['name'] }}</span>
                                <span class="font-bold tabular-nums">${{ number_format($src['amount'], 2) }} <span class="text-gray-400 text-xs">({{ $pct }}%)</span></span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $meta['bar'] }} rounded-full" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
@endif
