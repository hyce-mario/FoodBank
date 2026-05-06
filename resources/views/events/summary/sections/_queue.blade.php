@php
    /** @var array $data */
    $hm = fn ($m) => \App\Services\EventSummaryService::formatHm($m);
@endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Queue Summary</h2>
    <p class="text-sm text-gray-500 mb-5">Average time per car at each event-day stage. Times shown as <strong>HH:mm</strong>.</p>

    @if ($data['total_visits'] === 0)
        <div class="bg-gray-50 rounded-xl p-8 text-center">
            <p class="text-sm text-gray-500">No visits recorded for this event.</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl bg-blue-50 p-4">
                <p class="text-3xl font-black text-blue-700 tabular-nums">{{ $hm($data['avg_total_time']) }}</p>
                <p class="text-xs font-semibold text-blue-600 mt-1">Total Visit</p>
                <p class="text-[10px] text-blue-500 mt-0.5">Check-in → Exit</p>
            </div>
            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-3xl font-black text-gray-700 tabular-nums">{{ $hm($data['avg_checkin_to_queue']) }}</p>
                <p class="text-xs font-semibold text-gray-600 mt-1">Check-in → Queue</p>
            </div>
            <div class="rounded-xl bg-amber-50 p-4">
                <p class="text-3xl font-black text-amber-700 tabular-nums">{{ $hm($data['avg_queue_to_loaded']) }}</p>
                <p class="text-xs font-semibold text-amber-600 mt-1">Queue → Loaded</p>
            </div>
            <div class="rounded-xl bg-emerald-50 p-4">
                <p class="text-3xl font-black text-emerald-700 tabular-nums">{{ $hm($data['avg_loaded_to_exited']) }}</p>
                <p class="text-xs font-semibold text-emerald-600 mt-1">Loaded → Exit</p>
            </div>
        </div>
    @endif
</div>

@if ($data['total_visits'] > 0)
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Throughput</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-600">Total visits</dt>
                <dd class="font-bold tabular-nums">{{ number_format($data['total_visits']) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Completed (exited)</dt>
                <dd class="font-bold tabular-nums">{{ number_format($data['completed_visits']) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Lanes</dt>
                <dd class="font-bold tabular-nums">{{ $data['lanes'] }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Bags distributed</dt>
                <dd class="font-bold tabular-nums">{{ number_format($data['bags_distributed']) }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Stage Time Distribution</h3>
        @php
            $stages = [
                ['Check-in → Queue', $data['avg_checkin_to_queue'], 'bg-gray-400'],
                ['Queue → Loaded',   $data['avg_queue_to_loaded'],  'bg-amber-400'],
                ['Loaded → Exit',    $data['avg_loaded_to_exited'], 'bg-emerald-400'],
            ];
            $stageTotal = max(0.1, array_sum(array_column($stages, 1)));
        @endphp
        <div class="flex h-6 rounded-lg overflow-hidden">
            @foreach ($stages as [$label, $value, $color])
                @if ($value > 0)
                    <div class="{{ $color }}" style="width: {{ ($value / $stageTotal) * 100 }}%"
                         title="{{ $label }}: {{ $hm($value) }}"></div>
                @endif
            @endforeach
        </div>
        <div class="mt-3 space-y-1 text-xs">
            @foreach ($stages as [$label, $value, $color])
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm {{ $color }}"></span><span class="text-gray-600">{{ $label }}</span></span>
                    <span class="font-bold tabular-nums">{{ $hm($value) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
