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

            {{-- Print — fires browser print on the current page. @media print
                 rules below hide the chrome (sidebar, topbar, action buttons)
                 and let Chart.js charts render via the browser. --}}
            <button type="button"
                    onclick="window.print()"
                    class="no-print inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold border border-gray-200 rounded-lg bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors {{ isset($extraFilters) ? '' : 'ml-auto' }}"
                    title="Print this report">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                </svg>
                Print
            </button>

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

{{-- Print-only header — appears at the top of the printed page only.
     Gives the printout context (org name, report period) since the
     sidebar + top app header are hidden during print. --}}
<div class="print-only print-header" aria-hidden="true">
    @php
        $orgName    = \App\Services\SettingService::get('organization.name',    config('app.name', 'Food Bank'));
        $orgEmail   = \App\Services\SettingService::get('organization.email',   '');
        $orgPhone   = \App\Services\SettingService::get('organization.phone',   '');
        $orgWebsite = \App\Services\SettingService::get('organization.website', '');
    @endphp
    <div class="ph-row">
        <div class="ph-org">
            <strong>{{ $orgName }}</strong>
            @if ($orgEmail || $orgPhone || $orgWebsite)
                <span class="ph-meta">
                    @if ($orgEmail) {{ $orgEmail }} @endif
                    @if ($orgPhone) · {{ $orgPhone }} @endif
                    @if ($orgWebsite) · {{ $orgWebsite }} @endif
                </span>
            @endif
        </div>
        <div class="ph-period">
            Period: <strong>{{ $filter['from']->format('M j, Y') }} — {{ $filter['to']->format('M j, Y') }}</strong>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
    /* Print-only header is hidden on screen; only renders during print. */
    .print-only { display: none; }

    @media print {
        /* Hide app chrome */
        aside.fixed,
        header.bg-white.border-b,
        header.sticky,
        .no-print { display: none !important; }

        /* Hide action links that don't make sense in print */
        a[href*="reports.download"],
        a[href*="export.csv"],
        a[href*="/print"] { display: none !important; }

        /* Show print-only blocks */
        .print-only { display: block !important; }

        /* Full-bleed main */
        body, html { background: white !important; }
        main { margin: 0 !important; padding: 0 !important; max-width: none !important; }

        /* Force print-color-fidelity so brand cards keep their fills */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Cards / tables: keep them whole if possible */
        .bg-white.rounded-2xl,
        .bg-navy-700,
        .bg-brand-500,
        .grid > div { page-break-inside: avoid; }
        table { page-break-inside: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }

        /* Make canvases scale down rather than overflow */
        canvas { max-width: 100% !important; height: auto !important; }

        /* Page setup */
        @page { margin: 12mm; size: A4 portrait; }

        /* Print-header styling */
        .print-header {
            border-bottom: 2px solid #111;
            padding-bottom: 8mm;
            margin-bottom: 6mm;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            color: #1f2937;
        }
        .print-header .ph-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 8mm;
        }
        .print-header .ph-org strong { font-size: 12pt; display: block; }
        .print-header .ph-org .ph-meta { font-size: 9pt; color: #6b7280; }
        .print-header .ph-period { font-size: 9pt; color: #6b7280; text-align: right; white-space: nowrap; }
    }
</style>
@endpush
@endonce
