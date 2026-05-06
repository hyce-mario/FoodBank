<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Budget vs. Actual — {{ $branding['app_name'] }}</title>
    <style>
        @page { margin: 12mm; }
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
        .stat-value.green { color: #047857; } .stat-value.red { color: #b91c1c; }
        .stat-label { font-size: 8px; text-transform: uppercase; color: #6b7280; margin-top: 3px; font-weight: 600; }
        table.bv { width: 100%; border-collapse: collapse; font-size: 8px; }
        .bv thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 5px 6px; font-weight: 600; font-size: 7px; text-transform: uppercase; }
        .bv tbody td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; }
        .bv td.num { text-align: right; }
        .bv .total-row td { border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: 700; padding: 6px; }
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
            <h2>Budget vs. Actual</h2>
            <div class="meta">{{ $period['label'] }} · {{ ucfirst($data['direction']) }}</div>
        </td>
    </tr>
</table>

@php
    $v = $data['totals']['variance'];
    $vCls = $data['direction'] === 'income' ? ($v >= 0 ? 'green' : 'red') : ($v <= 0 ? 'green' : 'red');
@endphp

<table class="stats">
    <tr>
        <td><div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['totals']['budget']) }}</div><div class="stat-label">Total Budget</div></td>
        <td><div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['totals']['actual']) }}</div><div class="stat-label">Total Actual</div></td>
        <td><div class="stat-value {{ $vCls }}">{{ $v >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($v) }}</div><div class="stat-label">Variance</div></td>
    </tr>
</table>

<table class="bv">
    <thead>
        <tr>
            <th>Category</th><th>Type</th>
            <th class="num">Budget</th><th class="num">Actual</th>
            <th class="num">Variance</th><th class="num">% Var</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data['rows'] as $r)
        <tr>
            <td>{{ $r['category_name'] }}</td>
            <td>{{ ucfirst($r['type']) }}</td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($r['budget']) }}</td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($r['actual']) }}</td>
            <td class="num">{{ $r['variance'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($r['variance']) }}</td>
            <td class="num">{{ $r['variance_pct'] !== null ? ($r['variance_pct'] >= 0 ? '+' : '') . number_format($r['variance_pct'] * 100, 1) . '%' : '—' }}</td>
            <td>{{ str_replace('_', ' ', ucfirst($r['status'])) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center; color:#9ca3af; padding:14px;">No budgets seeded for this period and no actuals to show.</td></tr>
        @endforelse
        <tr class="total-row">
            <td>TOTAL</td><td></td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($data['totals']['budget']) }}</td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($data['totals']['actual']) }}</td>
            <td class="num">{{ $data['totals']['variance'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['totals']['variance']) }}</td>
            <td class="num">{{ $data['totals']['variance_pct'] !== null ? ($data['totals']['variance_pct'] >= 0 ? '+' : '') . number_format($data['totals']['variance_pct'] * 100, 1) . '%' : '—' }}</td>
            <td></td>
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
