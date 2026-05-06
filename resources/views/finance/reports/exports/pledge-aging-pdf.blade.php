<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pledge / AR Aging — {{ $branding['app_name'] }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 9px; line-height: 1.4; margin: 0; padding: 0; }
        h1 { margin: 0; font-size: 16px; color: #1b2b4b; }
        h2 { margin: 0; font-size: 14px; color: #111; }
        .header { width: 100%; border-bottom: 2px solid #1b2b4b; padding-bottom: 8px; margin-bottom: 12px; }
        .header td { vertical-align: top; padding: 0; }
        .header td.right { text-align: right; }
        .meta { font-size: 9px; color: #6b7280; margin-top: 3px; }
        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 12px; }
        table.stats td { border: 1px solid #e5e7eb; padding: 7px 10px; background: #fafafa; width: 33%; }
        .stat-value { font-size: 14px; font-weight: 700; }
        .stat-value.red   { color: #b91c1c; }
        .stat-value.amber { color: #b45309; }
        .stat-value.green { color: #047857; }
        .stat-value.blue  { color: #1d4ed8; }
        .stat-label { font-size: 8px; text-transform: uppercase; color: #6b7280; margin-top: 3px; font-weight: 600; }
        .stat-meta  { font-size: 8px; color: #6b7280; margin-top: 3px; }
        table.aging { width: 100%; border-collapse: collapse; font-size: 8px; }
        .aging thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 5px 6px; font-weight: 600; font-size: 7px; text-transform: uppercase; }
        .aging tbody td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; }
        .aging td.num { text-align: right; }
        .aging .bk-header td { background: #f9fafb; padding: 6px 7px; font-weight: 700; font-size: 9px; text-transform: uppercase; }
        .aging .grand-total td { background: #1b2b4b; color: #fff; font-weight: 800; font-size: 11px; padding: 7px; }
        .insights { border: 1px solid #e5e7eb; border-left: 3px solid #f97316; padding: 8px 10px; background: #fffbeb; margin-top: 10px; }
        .insights h3 { margin: 0 0 4px; font-size: 9px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 14px; }
        .insights li { margin: 2px 0; color: #4b5563; font-size: 8px; }
        .footer { margin-top: 10px; padding-top: 6px; border-top: 1px solid #e5e7eb; font-size: 7px; color: #9ca3af; }
        .footer .right { float: right; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td>
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="" style="max-height:38px; max-width:140px; margin-bottom:3px;">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
        </td>
        <td class="right">
            <h2>Pledge / AR Aging</h2>
            <div class="meta">As of {{ $asOf->format('M j, Y') }}</div>
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
            <div class="stat-label">Current</div>
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
            <th>Donor / Source</th><th>Pledged</th><th>Expected</th>
            <th class="num">Days Overdue</th><th class="num">Amount</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data['buckets'] as $bucket)
            <tr class="bk-header">
                <td colspan="6" style="color: {{ $bucket['color'] }};">{{ $bucket['label'] }} — {{ $bucket['count'] }} pledges · {{ \App\Services\FinanceReportService::usd($bucket['total']) }}</td>
            </tr>
            @if (empty($bucket['pledges']))
                <tr><td colspan="6" style="font-style:italic; color:#9ca3af;">No pledges.</td></tr>
            @else
                @foreach ($bucket['pledges'] as $p)
                    <tr>
                        <td style="padding-left:14px;">{{ $p['source_or_payee'] }}</td>
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
            <td>GRAND TOTAL</td><td colspan="3"></td>
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
</body>
</html>
