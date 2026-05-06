@php
    /** @var array $data */
    // Demographic donut: child / adult / senior breakdown.
    $demoTotal = max(1, $data['children'] + $data['adults'] + $data['seniors']);
    $childPct  = $data['children'] / $demoTotal;
    $adultPct  = $data['adults']   / $demoTotal;
    $seniorPct = $data['seniors']  / $demoTotal;
@endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Attendees</h2>
    <p class="text-sm text-gray-500 mb-5">Household-level summary. Individual names are intentionally not listed.</p>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl bg-blue-50 p-4">
            <p class="text-3xl font-black text-blue-700 tabular-nums">{{ number_format($data['pre_registered_total']) }}</p>
            <p class="text-xs font-semibold text-blue-600 mt-1">Pre-Registered</p>
            <p class="text-[10px] text-blue-500 mt-0.5">{{ $data['pre_reg_attended'] }} attended · {{ $data['pre_reg_no_show'] }} no-show</p>
        </div>
        <div class="rounded-xl bg-amber-50 p-4">
            <p class="text-3xl font-black text-amber-700 tabular-nums">{{ number_format($data['walk_ins']) }}</p>
            <p class="text-xs font-semibold text-amber-600 mt-1">Walk-Ins</p>
            <p class="text-[10px] text-amber-500 mt-0.5">No prior registration</p>
        </div>
        <div class="rounded-xl bg-emerald-50 p-4">
            <p class="text-3xl font-black text-emerald-700 tabular-nums">{{ number_format($data['total_households']) }}</p>
            <p class="text-xs font-semibold text-emerald-600 mt-1">Households Served</p>
            <p class="text-[10px] text-emerald-500 mt-0.5">Including represented</p>
        </div>
        <div class="rounded-xl bg-violet-50 p-4">
            <p class="text-3xl font-black text-violet-700 tabular-nums">{{ number_format($data['total_persons']) }}</p>
            <p class="text-xs font-semibold text-violet-600 mt-1">People Served</p>
            <p class="text-[10px] text-violet-500 mt-0.5">Avg {{ $data['avg_household_size'] }} per household</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
    {{-- Demographic donut --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Demographics</h3>
        <div class="flex items-center gap-6">
            @php
                $r  = 38;
                $c  = 2 * pi() * $r;
                $a1 = $childPct * $c;
                $a2 = $adultPct * $c;
                $a3 = $seniorPct * $c;
                $off1 = 0;
                $off2 = $a1;
                $off3 = $a1 + $a2;
            @endphp
            <svg width="120" height="120" viewBox="0 0 100 100" class="flex-shrink-0">
                <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="#f1f5f9" stroke-width="14"/>
                @if($data['children'] > 0)
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="#60a5fa" stroke-width="14"
                            stroke-dasharray="{{ $a1 }} {{ $c - $a1 }}" stroke-dashoffset="0"
                            transform="rotate(-90 50 50)"/>
                @endif
                @if($data['adults'] > 0)
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="#a78bfa" stroke-width="14"
                            stroke-dasharray="{{ $a2 }} {{ $c - $a2 }}" stroke-dashoffset="-{{ $off2 }}"
                            transform="rotate(-90 50 50)"/>
                @endif
                @if($data['seniors'] > 0)
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="#34d399" stroke-width="14"
                            stroke-dasharray="{{ $a3 }} {{ $c - $a3 }}" stroke-dashoffset="-{{ $off3 }}"
                            transform="rotate(-90 50 50)"/>
                @endif
                <text x="50" y="48" text-anchor="middle" font-size="14" font-weight="700" fill="#111">{{ $data['total_persons'] }}</text>
                <text x="50" y="62" text-anchor="middle" font-size="7" fill="#6b7280">total</text>
            </svg>
            <div class="flex-1 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-blue-400"></span>Children</span>
                    <span class="font-bold tabular-nums">{{ number_format($data['children']) }} <span class="text-gray-400 text-xs">({{ round($childPct*100) }}%)</span></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-violet-400"></span>Adults</span>
                    <span class="font-bold tabular-nums">{{ number_format($data['adults']) }} <span class="text-gray-400 text-xs">({{ round($adultPct*100) }}%)</span></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-emerald-400"></span>Seniors</span>
                    <span class="font-bold tabular-nums">{{ number_format($data['seniors']) }} <span class="text-gray-400 text-xs">({{ round($seniorPct*100) }}%)</span></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Pre-reg accuracy + visit status --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Pre-Registration Accuracy</h3>
        @if ($data['pre_reg_match_rate'] !== null)
            @php $matchPct = round($data['pre_reg_match_rate'] * 100); @endphp
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-600">Show-up rate</span>
                <span class="text-sm font-bold tabular-nums">{{ $matchPct }}%</span>
            </div>
            <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-blue-400 to-blue-500 rounded-full" style="width: {{ $matchPct }}%"></div>
            </div>
        @else
            <p class="text-sm text-gray-400">No pre-registrations to compare against.</p>
        @endif

        <div class="mt-5 border-t border-gray-100 pt-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Final visit status</p>
            <div class="space-y-1.5 text-xs">
                @foreach ($data['visit_status_counts'] as $status => $count)
                    @if($count > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">{{ ucwords(str_replace('_', ' ', $status)) }}</span>
                            <span class="font-bold tabular-nums">{{ $count }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
