<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Category Trend Report — {{ $branding['app_name'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 8.5px;
            line-height: 1.35;
            margin: 0;
            padding: 14px;
        }

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 10px; }
        table.header td { vertical-align: top; padding: 0 0 8px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 36px; max-width: 150px; margin-bottom: 3px; }
        .org h1 { margin: 0; font-size: 12px; color: #1b2b4b; font-weight: bold; }
        .org p { margin: 1px 0 0; color: #6b7280; font-size: 8px; }
        .doc-title h2 { margin: 0; font-size: 12px; color: #111; letter-spacing: 0.04em; font-weight: bold; }
        .doc-title .meta { font-size: 8px; color: #6b7280; margin-top: 2px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 8px; }
        table.stats td { border: 1px solid #e5e7eb; background: #fafafa; padding: 6px 8px; width: 33%; vertical-align: top; }
        .stat-value { font-size: 11px; font-weight: bold; color: #1b2b4b; }
        .stat-value.green { color: #047857; }
        .stat-value.red   { color: #b91c1c; }
        .stat-label { font-size: 7px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 2px; font-weight: bold; }
        .stat-sub { font-size: 7px; color: #6b7280; margin-top: 2px; }

        .chart-wrap { border: 1px solid #e5e7eb; padding: 8px; margin-bottom: 8px; }
        .chart-wrap h3 { margin: 0 0 4px; font-size: 9px; color: #1b2b4b; font-weight: bold; }
        .swatch { display: inline-block; width: 7px; height: 7px; vertical-align: middle; margin-right: 2px; }
        .legend { font-size: 7.5px; margin-top: 6px; }
        .legend span { display: inline-block; padding: 1px 6px 1px 0; }

        table.detail { width: 100%; border-collapse: collapse; font-size: 7.5px; margin-bottom: 8px; }
        .detail thead th {
            background: #1b2b4b; color: #fff; text-align: right; padding: 4px 5px;
            font-weight: bold; font-size: 6.5px; text-transform: uppercase; letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .detail thead th.left { text-align: left; }
        .detail tbody td { padding: 3px 5px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; text-align: right; white-space: nowrap; }
        .detail tbody td.left { text-align: left; }
        .detail tbody td.zero { color: #d1d5db; }
        .detail tbody td.total-col { background: #f3f4f6; font-weight: bold; }
        .detail .total-row td { border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: bold; }

        .insights { border: 1px solid #e5e7eb; border-left: 3px solid #f97316; padding: 8px 10px; background: #fffbeb; margin-bottom: 8px; }
        .insights h3 { margin: 0 0 4px; font-size: 9px; color: #92400e; font-weight: bold; }
        .insights ul { margin: 0; padding-left: 12px; }
        .insights li { margin: 1px 0; color: #4b5563; font-size: 8px; }

        .footer { margin-top: 10px; padding-top: 6px; border-top: 1px solid #e5e7eb; font-size: 7px; color: #9ca3af; }
        .footer .right { float: right; }
    </style>
</head>
<body>

@php
    $directionLabel = match ($data['direction']) {
        'income'  => 'Income',
        'expense' => 'Expense',
        'both'    => 'Income + Expense',
    };

    $seriesForChart = [];
    $colorMap       = [];
    foreach ($data['series'] as $s) {
        $seriesForChart[$s['name']] = $s['monthly'];
        $colorMap[$s['name']]       = $s['color'];
    }
@endphp

<table class="header">
    <tr>
        <td class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>Category Trend Report</p>
        </td>
        <td class="right doc-title">
            <h2>CATEGORY TREND</h2>
            <div class="meta">{{ $directionLabel }} · {{ $period['label'] }}</div>
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </td>
    </tr>
</table>

<table class="stats">
    <tr>
        <td>
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['totals']['period']) }}</div>
            <div class="stat-label">Period Total</div>
            <div class="stat-sub">{{ count($data['series']) }} categories · {{ count($data['months']) }} months</div>
        </td>
        <td>
            @if ($data['leaders']['top_grower'] && ($data['leaders']['top_grower']['delta'] ?? 0) > 0)
                <div class="stat-value green" style="font-size:9px;">{{ $data['leaders']['top_grower']['name'] }}</div>
                <div class="stat-label">Top Grower</div>
                <div class="stat-sub">+{{ number_format($data['leaders']['top_grower']['delta'] * 100, 0) }}% first→last</div>
            @else
                <div class="stat-value" style="color:#9ca3af;">—</div>
                <div class="stat-label">Top Grower</div>
            @endif
        </td>
        <td>
            @if ($data['leaders']['top_shrinker'] && ($data['leaders']['top_shrinker']['delta'] ?? 0) < 0)
                <div class="stat-value red" style="font-size:9px;">{{ $data['leaders']['top_shrinker']['name'] }}</div>
                <div class="stat-label">Top Shrinker</div>
                <div class="stat-sub">-{{ number_format(abs($data['leaders']['top_shrinker']['delta']) * 100, 0) }}% first→last</div>
            @else
                <div class="stat-value" style="color:#9ca3af;">—</div>
                <div class="stat-label">Top Shrinker</div>
            @endif
        </td>
    </tr>
</table>

<div class="chart-wrap">
    <h3>Monthly Trend</h3>
    @if (! empty($data['series']) && $data['totals']['period'] > 0)
        {!! \App\Support\SvgChart::line($seriesForChart, $data['month_labels'], [
            'width'  => 760,
            'height' => 200,
            'colors' => $colorMap,
        ]) !!}
        <div class="legend">
            @foreach ($data['series'] as $s)
                <span><span class="swatch" style="background: {{ $s['color'] }};"></span>{{ $s['name'] }}</span>
            @endforeach
        </div>
    @else
        <div style="text-align:center; color:#9ca3af; padding:14px;">No data for this period.</div>
    @endif
</div>

<table class="detail">
    <thead>
        <tr>
            <th class="left">Category</th>
            @foreach ($data['month_labels'] as $label)
                <th>{{ $label }}</th>
            @endforeach
            <th>Total</th>
            <th>Δ</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data['series'] as $s)
            <tr>
                <td class="left">
                    <span class="swatch" style="background: {{ $s['color'] }};"></span>
                    {{ $s['name'] }}
                </td>
                @foreach ($s['monthly'] as $v)
                    <td class="{{ $v == 0 ? 'zero' : '' }}">
                        {{ $v == 0 ? '—' : \App\Services\FinanceReportService::usd($v) }}
                    </td>
                @endforeach
                <td class="total-col">{{ \App\Services\FinanceReportService::usd($s['total']) }}</td>
                <td>
                    @if ($s['delta'] !== null)
                        {{ $s['delta'] >= 0 ? '+' : '' }}{{ number_format($s['delta'] * 100, 0) }}%
                    @else
                        <span style="color:#9ca3af;">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($data['month_labels']) + 3 }}" style="text-align:center; color:#9ca3af; padding:10px;">No data for this period.</td></tr>
        @endforelse
        @if (! empty($data['series']))
            <tr class="total-row">
                <td class="left">TOTAL</td>
                @foreach ($data['totals']['months'] as $v)
                    <td>{{ \App\Services\FinanceReportService::usd($v) }}</td>
                @endforeach
                <td>{{ \App\Services\FinanceReportService::usd($data['totals']['period']) }}</td>
                <td></td>
            </tr>
        @endif
    </tbody>
</table>

<div class="insights">
    <h3>Insights</h3>
    <ul>
        @foreach ($data['insights'] as $bullet)
            <li>{{ $bullet }}</li>
        @endforeach
    </ul>
</div>

<div class="footer">
    <span class="right">{{ now()->format('Y-m-d H:i') }}</span>
    <span>{{ $branding['app_name'] }} · Category Trend · {{ $directionLabel }} · {{ $period['label'] }}</span>
</div>

</body>
</html>
