<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pledge / AR Aging — {{ $branding['app_name'] }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937; font-size: 8.5px; line-height: 1.45;
            margin: 0; padding: 0;
        }

        /* ── Header ───────────────────────────────────────────────────────── */
        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 12px; }
        table.header td { vertical-align: top; padding: 0 0 8px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 42px; max-width: 170px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 13px; color: #1b2b4b; font-weight: bold; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 8.5px; }
        .doc-title h2 { margin: 0; font-size: 13px; color: #111; letter-spacing: 0.04em; font-weight: bold; }
        .doc-title .meta { font-size: 8.5px; color: #6b7280; margin-top: 3px; }

        /* ── KPI cards ────────────────────────────────────────────────────── */
        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 10px; }
        table.stats td { border: 1px solid #e5e7eb; background: #fafafa; padding: 6px 8px; width: 33%; vertical-align: top; }
        .stat-value { font-size: 13px; font-weight: bold; }
        .stat-value.green { color: #047857; } .stat-value.amber { color: #b45309; } .stat-value.red { color: #b91c1c; } .stat-value.blue { color: #1d4ed8; } .stat-value.navy { color: #1b2b4b; }
        .stat-label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 2px; font-weight: bold; }
        .stat-meta { font-size: 7px; color: #6b7280; margin-top: 1px; }

        /* ── Two-column block (summary + top donors) ──────────────────────── */
        table.split { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 10px; }
        table.split > tbody > tr > td { vertical-align: top; padding: 0; width: 50%; }
        .panel { border: 1px solid #e5e7eb; padding: 7px 9px; background: #fff; }
        .panel h3 { margin: 0 0 5px; font-size: 8.5px; color: #1b2b4b; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; }

        table.summary { width: 100%; border-collapse: collapse; font-size: 8px; }
        .summary th, .summary td { padding: 3px 4px; text-align: left; }
        .summary th { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; font-weight: bold; }
        .summary td.num { text-align: right; }
        .summary tr.total td { border-top: 1.5px solid #1b2b4b; font-weight: bold; padding-top: 5px; }
        .summary .swatch { display: inline-block; width: 6px; height: 6px; margin-right: 4px; }

        ul.donors { list-style: none; padding: 0; margin: 0; }
        ul.donors li { padding: 3px 0; border-bottom: 1px dashed #f3f4f6; font-size: 8px; }
        ul.donors li:last-child { border-bottom: none; }
        ul.donors .name { color: #374151; }
        ul.donors .count { color: #9ca3af; font-size: 7px; }
        ul.donors .amount { font-weight: bold; color: #111; float: right; }

        /* ── Detail table ─────────────────────────────────────────────────── */
        table.aging { width: 100%; border-collapse: collapse; font-size: 7.5px; }
        .aging thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 5px 6px; font-weight: bold; font-size: 7px; text-transform: uppercase; letter-spacing: 0.04em; }
        .aging thead th.right { text-align: right; }
        .aging tbody td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .aging td.num { text-align: right; }
        .aging .bk-header td { background: #f3f4f6; padding: 5px 6px; font-weight: bold; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; border-top: 1px solid #d1d5db; }
        .aging .bk-header .meta { font-weight: normal; text-transform: none; letter-spacing: 0; color: #6b7280; }
        .aging .bk-empty td { font-style: italic; color: #9ca3af; padding: 5px 18px; }
        .aging .grand-total td { background: #1b2b4b; color: #fff; font-weight: bold; font-size: 9px; padding: 7px 6px; border-top: 2px solid #1b2b4b; }
        .pill { display: inline-block; padding: 1px 4px; font-size: 6.5px; font-weight: bold; }
        .pill.open    { background: #dbeafe; color: #1e40af; }
        .pill.partial { background: #fef3c7; color: #92400e; }
        .od-overdue { color: #b91c1c; font-weight: bold; }

        /* ── Insights ─────────────────────────────────────────────────────── */
        .insights { border: 1px solid #e5e7eb; border-left: 3px solid #f97316; padding: 7px 10px; background: #fffbeb; margin-top: 10px; }
        .insights h3 { margin: 0 0 4px; font-size: 8.5px; color: #92400e; font-weight: bold; }
        .insights ul { margin: 0; padding-left: 12px; }
        .insights li { margin: 1px 0; color: #4b5563; font-size: 8px; }

        /* ── Footer ───────────────────────────────────────────────────────── */
        .footer { margin-top: 10px; padding-top: 6px; border-top: 1px solid #e5e7eb; font-size: 7px; color: #9ca3af; }
        .footer .right { float: right; }
    </style>
</head>
<body>

{{-- ── Branded header ──────────────────────────────────────────────── --}}
<table class="header">
    <tr>
        <td class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>Pledge Receivables &amp; AR Aging Statement</p>
        </td>
        <td class="right doc-title">
            <h2>PLEDGE / AR AGING</h2>
            <div class="meta">As of {{ $asOf->format('F j, Y') }}</div>
            <div class="meta">{{ $data['count'] }} outstanding pledges</div>
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </td>
    </tr>
</table>

{{-- ── Headline KPIs ──────────────────────────────────────────────── --}}
@php
    $over90  = $data['buckets']['over_90'];
    $current = $data['buckets']['current'];
    $share90 = $data['total'] > 0 ? ($over90['total'] / $data['total']) * 100 : 0;
    if ($share90 >= 25)      $cls90 = 'red';
    elseif ($share90 > 0)    $cls90 = 'amber';
    else                     $cls90 = 'green';

    $overdueTotal  = $data['total'] - $current['total'];
    $sharePastDue  = $data['total'] > 0 ? ($overdueTotal / $data['total']) * 100 : 0;
@endphp
<table class="stats">
    <tr>
        <td>
            <div class="stat-value navy">{{ \App\Services\FinanceReportService::usd($data['total']) }}</div>
            <div class="stat-label">Total Outstanding</div>
            <div class="stat-meta">{{ $data['count'] }} pledges</div>
        </td>
        <td>
            <div class="stat-value blue">{{ \App\Services\FinanceReportService::usd($current['total']) }}</div>
            <div class="stat-label">Current (not yet due)</div>
            <div class="stat-meta">{{ $current['count'] }} pledges &middot; {{ number_format(100 - $sharePastDue, 1) }}%</div>
        </td>
        <td>
            <div class="stat-value {{ $cls90 }}">{{ \App\Services\FinanceReportService::usd($over90['total']) }}</div>
            <div class="stat-label">90+ Days Overdue</div>
            <div class="stat-meta">{{ $over90['count'] }} pledges &middot; {{ number_format($share90, 1) }}%</div>
        </td>
    </tr>
</table>

{{-- ── Aging summary + Top donors ──────────────────────────────── --}}
<table class="split">
    <tr>
        <td>
            <div class="panel">
                <h3>Aging Summary</h3>
                <table class="summary">
                    <thead>
                        <tr>
                            <th>Bucket</th>
                            <th class="num">Pledges</th>
                            <th class="num">Total</th>
                            <th class="num">% of Out.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['buckets'] as $key => $bucket)
                            @php $share = $data['total'] > 0 ? ($bucket['total'] / $data['total']) * 100 : 0; @endphp
                            <tr>
                                <td><span class="swatch" style="background: {{ $bucket['color'] }};"></span>{{ $bucket['label'] }}</td>
                                <td class="num">{{ $bucket['count'] }}</td>
                                <td class="num">{{ \App\Services\FinanceReportService::usd($bucket['total']) }}</td>
                                <td class="num">{{ number_format($share, 1) }}%</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>TOTAL</td>
                            <td class="num">{{ $data['count'] }}</td>
                            <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
                            <td class="num">100.0%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
        <td>
            <div class="panel">
                <h3>Top 5 Outstanding Donors</h3>
                @if (empty($data['top_donors']))
                    <p style="color:#9ca3af; font-size:8px; margin:4px 0 0;">No outstanding pledges.</p>
                @else
                    <ul class="donors">
                        @foreach ($data['top_donors'] as $d)
                            @php $share = $data['total'] > 0 ? ($d['total'] / $data['total']) * 100 : 0; @endphp
                            <li>
                                <span class="amount">{{ \App\Services\FinanceReportService::usd($d['total']) }}</span>
                                <span class="name">{{ $d['name'] }}<br><span class="count">{{ $d['count'] }} pledge{{ $d['count'] === 1 ? '' : 's' }} &middot; {{ number_format($share, 1) }}%</span></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ── Per-pledge detail by bucket ───────────────────────────── --}}
<table class="aging">
    <thead>
        <tr>
            <th>Donor / Source</th>
            <th>Pledged</th>
            <th>Expected</th>
            <th class="right">Days OD</th>
            <th class="right">Amount</th>
            <th>Status</th>
            <th>Linked Event</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data['buckets'] as $bucket)
            <tr class="bk-header">
                <td colspan="7" style="color: {{ $bucket['color'] }};">
                    {{ $bucket['label'] }}
                    <span class="meta">— {{ $bucket['count'] }} pledges &middot; {{ \App\Services\FinanceReportService::usd($bucket['total']) }}</span>
                </td>
            </tr>
            @if (empty($bucket['pledges']))
                <tr class="bk-empty"><td colspan="7">No pledges in this bucket.</td></tr>
            @else
                @foreach ($bucket['pledges'] as $p)
                    <tr>
                        <td style="padding-left:14px;">{{ $p['source_or_payee'] }}</td>
                        <td>{{ $p['pledged_at'] }}</td>
                        <td>{{ $p['expected_at'] }}</td>
                        <td class="num">
                            @if ($p['days_overdue'] > 0)
                                <span class="od-overdue">{{ $p['days_overdue'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">{{ \App\Services\FinanceReportService::usd($p['amount']) }}</td>
                        <td><span class="pill {{ $p['status'] }}">{{ ucfirst($p['status']) }}</span></td>
                        <td style="color:#6b7280;">{{ $p['event_name'] ?? '—' }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
        <tr class="grand-total">
            <td>GRAND TOTAL</td>
            <td colspan="3"></td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
            <td colspan="2">{{ $data['count'] }} pledges</td>
        </tr>
    </tbody>
</table>

{{-- ── Insights ──────────────────────────────────────────────── --}}
@if (! empty($data['insights']))
<div class="insights">
    <h3>Insights</h3>
    <ul>
        @foreach ($data['insights'] as $b) <li>{{ $b }}</li> @endforeach
    </ul>
</div>
@endif

{{-- ── Footer ────────────────────────────────────────────────── --}}
<div class="footer">
    Generated {{ now()->format('M j, Y g:i A') }}
    <span class="right">{{ $branding['app_name'] }} &middot; Pledge / AR Aging</span>
</div>

</body>
</html>
