<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pledge / AR Aging — {{ $branding['app_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; font-size: 11px; line-height: 1.5; }
        .sheet { max-width: 1100px; margin: 0 auto; }
        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 18px; }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .doc-title h2 { margin: 0; font-size: 22px; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }
        table.stats { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 18px; }
        table.stats td { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #fafafa; width: 33%; vertical-align: top; }
        .stat-value { font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 6px; font-weight: 600; }
        .stat-meta { font-size: 10px; color: #6b7280; margin-top: 4px; }
        .red { color: #b91c1c; } .amber { color: #b45309; } .green { color: #047857; } .blue { color: #1d4ed8; }
        table.aging { width: 100%; border-collapse: collapse; font-size: 10px; }
        .aging thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 7px 8px; font-weight: 600; font-size: 9px; text-transform: uppercase; }
        .aging thead th.right { text-align: right; }
        .aging tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .aging td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .aging .bk-header td { background: #f9fafb; padding: 8px; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
        .aging .grand-total td { background: #1b2b4b; color: #fff; font-weight: 800; font-size: 12px; padding: 10px 8px; }
        .insights { border: 1px solid #e5e7eb; border-left: 4px solid #f97316; border-radius: 8px; padding: 12px 16px; background: #fffbeb; margin-top: 16px; }
        .insights h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 18px; }
        .insights li { margin: 4px 0; color: #4b5563; font-size: 11px; }
        .footer { margin-top: 22px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
        .footer .right { float: right; }
        @media print { body { padding: 12px; } tr { page-break-inside: avoid; } thead { display: table-header-group; } }
    </style>
</head>
<body>
<div class="sheet">

<table class="header">
    <tr>
        <td>
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="" style="max-height:56px; max-width:220px; margin-bottom:6px; display:block;">
            @endif
            <div class="org"><h1>{{ $branding['app_name'] }}</h1></div>
        </td>
        <td class="right">
            <div class="doc-title">
                <h2>Pledge / AR Aging</h2>
                <p class="meta">As of {{ $asOf->format('M j, Y') }}</p>
            </div>
        </td>
    </tr>
</table>

@php
    $over90 = $data['buckets']['over_90'];
    $share  = $data['total'] > 0 ? ($over90['total'] / $data['total']) * 100 : 0;
    $cls    = $share >= 25 ? 'red' : ($share > 0 ? 'amber' : 'green');
@endphp

<table class="stats">
    <tr>
        <td>
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['total']) }}</div>
            <div class="stat-label">Total Outstanding</div>
            <div class="stat-meta">{{ $data['count'] }} pledges</div>
        </td>
        <td>
            <div class="stat-value blue">{{ \App\Services\FinanceReportService::usd($data['buckets']['current']['total']) }}</div>
            <div class="stat-label">Current (not yet due)</div>
            <div class="stat-meta">{{ $data['buckets']['current']['count'] }} pledges</div>
        </td>
        <td>
            <div class="stat-value {{ $cls }}">{{ \App\Services\FinanceReportService::usd($over90['total']) }}</div>
            <div class="stat-label">90+ Days Overdue</div>
            <div class="stat-meta">{{ number_format($share, 1) }}% of outstanding</div>
        </td>
    </tr>
</table>

<table class="aging">
    <thead>
        <tr>
            <th>Donor / Source</th>
            <th>Pledged</th>
            <th>Expected</th>
            <th class="right">Days Overdue</th>
            <th class="right">Amount</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data['buckets'] as $bucket)
            <tr class="bk-header">
                <td colspan="6" style="color: {{ $bucket['color'] }};">
                    {{ $bucket['label'] }}
                    <span style="color:#6b7280; font-weight:normal; text-transform:none; margin-left:8px;">{{ $bucket['count'] }} pledges · {{ \App\Services\FinanceReportService::usd($bucket['total']) }}</span>
                </td>
            </tr>
            @if (empty($bucket['pledges']))
                <tr><td colspan="6" style="font-style:italic; color:#9ca3af;">No pledges.</td></tr>
            @else
                @foreach ($bucket['pledges'] as $p)
                    <tr>
                        <td style="padding-left:18px;">{{ $p['source_or_payee'] }}</td>
                        <td>{{ $p['pledged_at'] }}</td>
                        <td>{{ $p['expected_at'] }}</td>
                        <td class="num">{{ $p['days_overdue'] > 0 ? $p['days_overdue'] : '—' }}</td>
                        <td class="num">{{ \App\Services\FinanceReportService::usd($p['amount']) }}</td>
                        <td>{{ ucfirst($p['status']) }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
        <tr class="grand-total">
            <td>GRAND TOTAL</td>
            <td colspan="3"></td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
            <td>{{ $data['count'] }} pledges</td>
        </tr>
    </tbody>
</table>

@if (! empty($data['insights']))
<div class="insights">
    <h3>Insights</h3>
    <ul>@foreach ($data['insights'] as $b) <li>{{ $b }}</li> @endforeach</ul>
</div>
@endif

<div class="footer">
    Generated {{ now()->format('M j, Y g:i A') }}
    <span class="right">{{ $branding['app_name'] }}</span>
</div>

</div>

@if (! empty($autoPrint))
<script>setTimeout(function () { window.print(); }, 250);</script>
@endif
</body>
</html>
