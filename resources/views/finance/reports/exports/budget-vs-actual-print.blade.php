<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Budget vs. Actual — {{ $branding['app_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; font-size: 11px; line-height: 1.5; }
        .sheet { max-width: 1200px; margin: 0 auto; }
        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 18px; }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }
        table.stats { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 18px; }
        table.stats td { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #fafafa; width: 33%; vertical-align: top; }
        .stat-value { font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .stat-value.green { color: #047857; } .stat-value.red { color: #b91c1c; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 6px; font-weight: 600; }
        .stat-meta { font-size: 10px; color: #6b7280; margin-top: 4px; }
        table.bv { width: 100%; border-collapse: collapse; font-size: 10px; }
        .bv thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 7px 8px; font-weight: 600; font-size: 9px; text-transform: uppercase; }
        .bv thead th.right { text-align: right; }
        .bv tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .bv tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .bv .total-row td { border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: 800; padding: 10px 8px; }
        .pill { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 9px; font-weight: 700; }
        .pill.over  { background: #fee2e2; color: #991b1b; }
        .pill.under { background: #d1fae5; color: #065f46; }
        .pill.on_target { background: #f3f4f6; color: #4b5563; }
        .var-pos.expense { color: #b91c1c; } .var-neg.expense { color: #047857; }
        .var-pos.income  { color: #047857; } .var-neg.income  { color: #b91c1c; }
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
                    <h2>Budget vs. Actual</h2>
                    <p class="meta">{{ $period['label'] }} · {{ ucfirst($data['direction']) }}</p>
                </div>
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
            <td>
                <div class="stat-value {{ $vCls }}">{{ $v >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($v) }}</div>
                <div class="stat-label">Variance</div>
                @if ($data['totals']['variance_pct'] !== null)
                    <div class="stat-meta">{{ $data['totals']['variance_pct'] >= 0 ? '+' : '' }}{{ number_format($data['totals']['variance_pct'] * 100, 1) }}% vs budget</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="bv">
        <thead>
            <tr>
                <th>Category</th>
                <th>Type</th>
                <th class="right">Budget</th>
                <th class="right">Actual</th>
                <th class="right">Variance</th>
                <th class="right">% Var</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rows'] as $r)
                @php
                    $sign = $r['variance'] >= 0 ? 'pos' : 'neg';
                    $cls  = "var-{$sign} {$r['type']}";
                @endphp
                <tr>
                    <td>{{ $r['category_name'] }}</td>
                    <td>{{ ucfirst($r['type']) }}</td>
                    <td class="num">{{ \App\Services\FinanceReportService::usd($r['budget']) }}</td>
                    <td class="num">{{ \App\Services\FinanceReportService::usd($r['actual']) }}</td>
                    <td class="num {{ $cls }}">{{ $r['variance'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($r['variance']) }}</td>
                    <td class="num">{{ $r['variance_pct'] !== null ? ($r['variance_pct'] >= 0 ? '+' : '') . number_format($r['variance_pct'] * 100, 1) . '%' : '—' }}</td>
                    <td><span class="pill {{ $r['status'] }}">{{ str_replace('_', ' ', ucfirst($r['status'])) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; color:#9ca3af; padding:20px;">No budgets seeded for this period and no actuals to show.</td></tr>
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
</div>

@if (! empty($autoPrint))
<script>setTimeout(function () { window.print(); }, 250);</script>
@endif
</body>
</html>
