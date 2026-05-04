{{--
    Phase 7.1.d — Common report shell. Used by every individual report
    page so the layout, period filter, KPI strip, chart slot, detail
    table, insights panel, and export bar all stay consistent.

    Required vars when including this partial:
      $reportTitle  string — "Statement of Activities"
      $period       array  — output of FinanceReportService::resolvePeriod()
      $exportRoutes array  — ['print' => 'route.name', 'pdf' => '...', 'csv' => '...']

    Slots populated by the calling view via @section/@yield style:
      kpi-strip, chart, detail-table, insights

    The shell does NOT @extends layouts.app — the calling view does
    that. This file is @included in the calling view's content section.
--}}

{{-- Header ─────────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a href="{{ route('finance.reports') }}"
               class="text-xs text-gray-400 hover:text-navy-700 inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                All reports
            </a>
        </div>
        <h1 class="text-xl font-bold text-gray-900">{{ $reportTitle }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">For period: <strong class="text-gray-700">{{ $period['label'] }}</strong></p>
    </div>

    {{-- Export icon buttons — Print / PDF / CSV. The link
         carries the entire current query string so the export matches
         what's on screen (period preset, custom dates, compare flag,
         and any future filters). --}}
    @php
        $exportQuery = array_filter([
            'period' => request('period'),
            'from'   => request('from'),
            'to'     => request('to'),
            'compare' => request('compare'),
        ]);
    @endphp
    <div class="flex items-center gap-2">
        <a href="{{ route($exportRoutes['print'], $exportQuery) }}"
           target="_blank"
           title="Print"
           aria-label="Print"
           class="w-9 h-9 inline-flex items-center justify-center border border-gray-300 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
        </a>
        <a href="{{ route($exportRoutes['pdf'], $exportQuery) }}"
           title="Download PDF"
           aria-label="Download PDF"
           class="w-9 h-9 inline-flex items-center justify-center border border-red-200 text-red-700 hover:bg-red-50 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
        </a>
        <a href="{{ route($exportRoutes['csv'], $exportQuery) }}"
           title="Download CSV"
           aria-label="Download CSV"
           class="w-9 h-9 inline-flex items-center justify-center border border-green-200 text-green-700 hover:bg-green-50 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        </a>
    </div>
</div>

@include('finance._nav')

{{-- Period filter ──────────────────────────────────────────────────── --}}
<div class="mb-5">
    @include('finance.reports._period_filter', ['period' => $period])
</div>
