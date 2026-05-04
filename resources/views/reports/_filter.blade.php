{{--
    Global Reports Filter Bar
    Variables expected:
    - $filter: array with keys: preset, date_from, date_to
    - $formAction: route to submit to (default: current URL)
    - $extraFields: optional blade slot for additional filters (e.g. event_id)
--}}
@php
    $formAction = $formAction ?? request()->url();
    $preset     = $filter['preset'] ?? 'last_30';
    $dateFrom   = $filter['date_from'] ?? '';
    $dateTo     = $filter['date_to'] ?? '';

    $presets = [
        'today'      => 'Today',
        'last_7'     => 'Last 7 Days',
        'last_30'    => 'Last 30 Days',
        'this_month' => 'This Month',
        'this_year'  => 'This Year',
        'custom'     => 'Custom Range',
    ];
@endphp

<div x-data="{
    preset:   '{{ $preset }}',
    dateFrom: '{{ $dateFrom }}',
    dateTo:   '{{ $dateTo }}',
    get isCustom() { return this.preset === 'custom'; }
}" class="bg-white border border-gray-100 rounded-2xl shadow-sm px-5 py-3.5 mb-5">

    <form method="GET" action="{{ $formAction }}" id="report-filter-form"
          x-on:submit.prevent="$el.submit()">

        <div class="flex flex-wrap items-center gap-3">

            {{-- Label --}}
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide hidden sm:inline">Period</span>

            {{-- Preset pills --}}
            <div class="flex flex-wrap gap-1.5">
                @foreach ($presets as $key => $label)
                    <button type="button"
                            @click="preset = '{{ $key }}'; if ('{{ $key }}' !== 'custom') { $nextTick(() => document.getElementById('report-filter-form').submit()) }"
                            :class="preset === '{{ $key }}'
                                ? 'bg-navy-700 text-white border-navy-700'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:text-gray-800'"
                            class="px-3 py-1.5 text-xs font-semibold border rounded-lg transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Hidden preset input --}}
            <input type="hidden" name="preset" :value="preset">

            {{-- Custom date range --}}
            <div x-show="isCustom"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="flex items-center gap-2 flex-wrap"
                 style="display:none;">
                <input type="date"
                       name="date_from"
                       x-model="dateFrom"
                       class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">
                <span class="text-gray-400 text-xs">to</span>
                <input type="date"
                       name="date_to"
                       x-model="dateTo"
                       class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">
                <button type="submit"
                        class="px-4 py-1.5 bg-navy-700 text-white text-sm font-semibold rounded-xl hover:bg-navy-800 transition-colors">
                    Apply
                </button>
            </div>

            {{-- Extra filters (event selector, etc.) injected by parent --}}
            @isset($extraFilters)
                <div class="flex items-center gap-2 ml-auto flex-wrap">
                    {!! $extraFilters !!}
                </div>
            @endisset

        </div>

        {{-- Display current range --}}
        <p class="text-xs text-gray-400 mt-2">
            Showing:
            <span class="font-medium text-gray-600">
                {{ $filter['from']->format('M j, Y') }} — {{ $filter['to']->format('M j, Y') }}
            </span>
        </p>

    </form>
</div>
