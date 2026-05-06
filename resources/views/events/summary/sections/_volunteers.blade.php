@php
    /** @var array $data */
    $sourceTotal = max(1, $data['pre_assigned_in'] + $data['walk_ins'] + $data['new_volunteers']);
@endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Volunteers</h2>
    <p class="text-sm text-gray-500 mb-5">Volunteer participation summary. Individual names are intentionally not listed.</p>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl bg-gray-50 p-4">
            <p class="text-3xl font-black text-gray-800 tabular-nums">{{ number_format($data['scheduled']) }}</p>
            <p class="text-xs font-semibold text-gray-600 mt-1">Scheduled</p>
            <p class="text-[10px] text-gray-500 mt-0.5">Pre-assigned to event</p>
        </div>
        <div class="rounded-xl bg-blue-50 p-4">
            <p class="text-3xl font-black text-blue-700 tabular-nums">{{ number_format($data['pre_assigned_in']) }}</p>
            <p class="text-xs font-semibold text-blue-600 mt-1">Showed Up</p>
            <p class="text-[10px] text-blue-500 mt-0.5">Of scheduled</p>
        </div>
        <div class="rounded-xl bg-amber-50 p-4">
            <p class="text-3xl font-black text-amber-700 tabular-nums">{{ number_format($data['walk_ins']) }}</p>
            <p class="text-xs font-semibold text-amber-600 mt-1">Walk-Ins</p>
            <p class="text-[10px] text-amber-500 mt-0.5">Existing roster</p>
        </div>
        <div class="rounded-xl bg-emerald-50 p-4">
            <p class="text-3xl font-black text-emerald-700 tabular-nums">{{ number_format($data['total_check_ins']) }}</p>
            <p class="text-xs font-semibold text-emerald-600 mt-1">Total Volunteers</p>
            <p class="text-[10px] text-emerald-500 mt-0.5">{{ $data['first_timers'] }} first-timer{{ $data['first_timers'] === 1 ? '' : 's' }}</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Volunteer Source Breakdown</h3>
        <div class="space-y-3">
            @foreach ([
                ['label' => 'Pre-assigned', 'value' => $data['pre_assigned_in'], 'color' => 'bg-blue-500'],
                ['label' => 'Walk-in',      'value' => $data['walk_ins'],        'color' => 'bg-amber-500'],
                ['label' => 'New volunteer','value' => $data['new_volunteers'],  'color' => 'bg-purple-500'],
            ] as $row)
                @php $pct = round($row['value'] / $sourceTotal * 100); @endphp
                <div>
                    <div class="flex items-center justify-between text-sm mb-1.5">
                        <span class="text-gray-600">{{ $row['label'] }}</span>
                        <span class="font-bold tabular-nums">{{ $row['value'] }} <span class="text-gray-400 text-xs">({{ $pct }}%)</span></span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full {{ $row['color'] }} rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Hours Contributed</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-3xl font-black text-gray-900 tabular-nums">{{ $data['total_hours'] }}</p>
                <p class="text-xs font-semibold text-gray-500 mt-1 uppercase tracking-wide">Total hours</p>
            </div>
            <div>
                <p class="text-3xl font-black text-gray-900 tabular-nums">{{ $data['avg_hours'] }}</p>
                <p class="text-xs font-semibold text-gray-500 mt-1 uppercase tracking-wide">Avg / volunteer</p>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-4">Calculated from check-out timestamps; volunteers who didn't check out are excluded.</p>
    </div>
</div>
