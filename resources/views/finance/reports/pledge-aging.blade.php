@extends('layouts.app')
@section('title', 'Pledge / AR Aging')

@section('content')

@php
    $reportTitle  = 'Pledge / AR Aging';
    // The aging report has no period filter — it's "as of today" by default.
    // Stub out period payload for the shared _shell.
    $period = ['label' => 'As of ' . $asOf->format('M j, Y')];
    $exportRoutes = [
        'print' => 'finance.reports.pledge-aging.print',
        'pdf'   => 'finance.reports.pledge-aging.pdf',
        'csv'   => 'finance.reports.pledge-aging.csv',
    ];
@endphp

{{-- Light header (skip _shell since it expects period filter UI we don't need) --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">{{ $reportTitle }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">As of {{ $asOf->format('M j, Y') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('finance.pledges.index') }}" class="text-xs text-brand-600 hover:underline">Manage pledges →</a>
        <button onclick="window.print()" class="text-sm bg-white border border-gray-200 hover:bg-gray-50 rounded-lg px-3 py-1.5">Print</button>
        <a href="{{ route('finance.reports.pledge-aging.pdf', ['as_of' => $asOf->format('Y-m-d')]) }}"
           class="text-sm bg-white border border-gray-200 hover:bg-gray-50 rounded-lg px-3 py-1.5">PDF</a>
        <a href="{{ route('finance.reports.pledge-aging.csv', ['as_of' => $asOf->format('Y-m-d')]) }}"
           class="text-sm bg-navy-700 hover:bg-navy-600 text-white rounded-lg px-3 py-1.5">CSV</a>
    </div>
</div>

{{-- ── KPI strip ───────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Total Outstanding</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['total']) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">{{ $data['count'] }} {{ $data['count'] === 1 ? 'pledge' : 'pledges' }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Current (not yet due)</p>
        <p class="text-2xl font-bold text-blue-700 tabular-nums">{{ \App\Services\FinanceReportService::usd($data['buckets']['current']['total']) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">{{ $data['buckets']['current']['count'] }} pledges</p>
    </div>
    @php
        $over90 = $data['buckets']['over_90'];
        $share  = $data['total'] > 0 ? ($over90['total'] / $data['total']) * 100 : 0;
        $cls    = $share >= 25 ? 'text-red-700' : ($share > 0 ? 'text-amber-700' : 'text-green-700');
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">90+ Days Overdue</p>
        <p class="text-2xl font-bold tabular-nums {{ $cls }}">{{ \App\Services\FinanceReportService::usd($over90['total']) }}</p>
        <p class="text-xs text-gray-500 mt-1.5">{{ number_format($share, 1) }}% of outstanding · {{ $over90['count'] }} pledges</p>
    </div>
</div>

{{-- ── Insights + Top donors ───────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Insights</h2>
        <ul class="space-y-1.5 text-sm text-gray-700 list-disc list-inside">
            @foreach ($data['insights'] as $b) <li>{{ $b }}</li> @endforeach
        </ul>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Top Outstanding Donors</h2>
        @if (empty($data['top_donors']))
            <p class="text-sm text-gray-400">No outstanding pledges.</p>
        @else
            <ul class="space-y-2">
                @foreach ($data['top_donors'] as $d)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-gray-700">{{ $d['name'] }}
                            <span class="text-xs text-gray-400">({{ $d['count'] }} pledge{{ $d['count'] === 1 ? '' : 's' }})</span>
                        </span>
                        <span class="text-gray-900 tabular-nums font-semibold">{{ \App\Services\FinanceReportService::usd($d['total']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

{{-- ── Detail by bucket ────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900">Aging Detail by Bucket</h2>
        <p class="text-xs text-gray-400 mt-0.5">Buckets use 30/60/90/90+ AR-aging thresholds. Only open + partial pledges shown.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-widest text-gray-400 border-b border-gray-100">
                    <th class="px-5 py-3">Donor / Source</th>
                    <th class="px-3 py-3">Pledged</th>
                    <th class="px-3 py-3">Expected</th>
                    <th class="px-3 py-3 text-right">Days Overdue</th>
                    <th class="px-3 py-3 text-right">Amount</th>
                    <th class="px-3 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($data['buckets'] as $key => $bucket)
                    <tr class="bg-gray-50">
                        <td colspan="6" class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider"
                            style="color: {{ $bucket['color'] }};">
                            <span class="inline-block w-2 h-2 rounded-sm mr-2 align-middle" style="background: {{ $bucket['color'] }};"></span>
                            {{ $bucket['label'] }}
                            <span class="text-gray-400 normal-case font-normal ml-2">{{ $bucket['count'] }} pledges · {{ \App\Services\FinanceReportService::usd($bucket['total']) }}</span>
                        </td>
                    </tr>
                    @if (empty($bucket['pledges']))
                        <tr><td colspan="6" class="px-5 py-3 text-xs text-gray-400 italic">No pledges in this bucket.</td></tr>
                    @else
                        @foreach ($bucket['pledges'] as $p)
                            <tr>
                                <td class="px-5 py-2.5 pl-9 text-gray-700">{{ $p['source_or_payee'] }}</td>
                                <td class="px-3 py-2.5 text-gray-500">{{ $p['pledged_at'] }}</td>
                                <td class="px-3 py-2.5 text-gray-500">{{ $p['expected_at'] }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums">{{ $p['days_overdue'] > 0 ? $p['days_overdue'] : '—' }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ \App\Services\FinanceReportService::usd($p['amount']) }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $p['status'] === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ ucfirst($p['status']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
                <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold">
                    <td class="px-5 py-3 text-gray-900">GRAND TOTAL</td>
                    <td colspan="3"></td>
                    <td class="px-3 py-3 text-right tabular-nums">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection
