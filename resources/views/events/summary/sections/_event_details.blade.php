@php /** @var array $data */ @endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Event Details</h2>
    <p class="text-sm text-gray-500 mb-5">Configuration of the event when it ran.</p>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
        <div>
            <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Event Name</dt>
            <dd class="text-sm text-gray-900 mt-1 font-medium">{{ $data['name'] }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Date</dt>
            <dd class="text-sm text-gray-900 mt-1 font-medium">{{ $data['date']?->format('D, M j, Y') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Location</dt>
            <dd class="text-sm text-gray-900 mt-1">{{ $data['location'] ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Lanes</dt>
            <dd class="text-sm text-gray-900 mt-1">{{ $data['lanes'] ?? '—' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Description / Notes</dt>
            <dd class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $data['description'] ?: '— No notes recorded.' }}</dd>
        </div>
    </dl>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
    {{-- Volunteer group --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Volunteer Group Assigned</h3>
        @if ($data['group'])
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-base font-semibold text-gray-900">{{ $data['group']['name'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $data['group']['roster_count'] }} on roster</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-black text-brand-600 tabular-nums">{{ $data['assigned_count'] }}</p>
                    <p class="text-xs text-gray-500">Assigned to event</p>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400">No volunteer group was assigned to this event.</p>
        @endif
    </div>

    {{-- Allocation ruleset --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Allocation Ruleset</h3>
        @if ($data['ruleset'])
            <p class="text-base font-semibold text-gray-900">{{ $data['ruleset']['name'] }}</p>
            <p class="text-xs text-gray-500 mt-1">
                Type: <span class="font-medium text-gray-700">{{ ucwords(str_replace('_', ' ', $data['ruleset']['allocation_type'])) }}</span>
                · Max household size: <span class="font-medium text-gray-700">{{ $data['ruleset']['max_household_size'] }}</span>
            </p>
            @if (! empty($data['ruleset']['rules']))
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Bag allocations by household size</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($data['ruleset']['rules'] as $rule)
                            <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-xs">
                                <span class="text-gray-600">
                                    Size {{ $rule['min'] ?? '?' }}@if(! empty($rule['max'])) – {{ $rule['max'] }}@else+@endif
                                </span>
                                <span class="font-bold text-gray-900 tabular-nums">{{ $rule['bags'] ?? '?' }} bags</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <p class="text-sm text-gray-400">No allocation ruleset was set for this event.</p>
        @endif
    </div>
</div>
