{{--
    Phase 7.1.a — Universal period filter for finance reports.

    Used by the common report shell so every report shares the same
    period-selection contract:
      • 7 presets + custom range
      • optional "compare to prior period" toggle
      • Submits via GET so URLs stay bookmarkable

    Required vars:
      $period           — array from FinanceReportService::resolvePeriod()
      $compareEnabled   — bool, whether this report supports compare
                          (defaults to true)
--}}
@php
    $compareEnabled = $compareEnabled ?? true;
    $isCustom = ($period['preset'] ?? 'this_month') === 'custom';
    $compareOn = ! empty($period['compare_from']);
@endphp

<form method="GET" action="{{ url()->current() }}"
      x-data="{
          preset: @js($period['preset']),
          fromVal: @js(optional(request('from'))),
          toVal: @js(optional(request('to'))),
          compareOn: @js($compareOn),
      }"
      class="flex flex-wrap items-center gap-2 px-4 py-3 border border-gray-200 rounded-xl bg-white shadow-sm">

    <label class="text-sm font-semibold text-gray-700 mr-1">Period</label>

    <select name="period" x-model="preset"
            x-on:change="$event.target.form.requestSubmit()"
            class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="this_month">This Month</option>
        <option value="last_month">Last Month</option>
        <option value="this_quarter">This Quarter</option>
        <option value="last_quarter">Last Quarter</option>
        <option value="ytd">Year to Date</option>
        <option value="last_year">Last Year</option>
        <option value="last_12_months">Last 12 Months</option>
        <option value="custom">Custom Range…</option>
    </select>

    {{-- Custom range pickers — only visible when preset=custom --}}
    <div x-show="preset === 'custom'" class="flex items-center gap-2"
         x-cloak>
        <input type="date" name="from"
               value="{{ request('from', $period['from']->toDateString()) }}"
               class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white
                      focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <span class="text-gray-400 text-sm">to</span>
        <input type="date" name="to"
               value="{{ request('to', $period['to']->toDateString()) }}"
               class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white
                      focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <button type="submit"
                class="px-3 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
            Apply
        </button>
    </div>

    @if ($compareEnabled)
        <label class="ml-2 inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
            <input type="checkbox" name="compare" value="prior"
                   x-model="compareOn"
                   x-on:change="$event.target.form.requestSubmit()"
                   class="rounded border-gray-300 text-navy-700 focus:ring-navy-600">
            Compare to prior period
        </label>
    @endif

    {{-- Echo the resolved label on the right so the user can see what's
         actually being shown — especially helpful for "This Quarter"
         when the user wants to know the dates without doing math. --}}
    <span class="ml-auto text-sm text-gray-500">
        Showing <strong class="text-gray-800">{{ $period['label'] }}</strong>
        @if (! empty($period['compare']))
            <span class="text-gray-400">vs.</span> <strong class="text-gray-700">{{ $period['compare']['label'] }}</strong>
        @endif
    </span>
</form>
