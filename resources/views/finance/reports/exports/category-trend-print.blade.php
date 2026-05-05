<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Category Trend Report — {{ $branding['app_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            margin: 0; padding: 18px;
            font-size: 11px; line-height: 1.4;
        }
        .sheet { max-width: 1500px; margin: 0 auto; }

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 14px; }
        table.header td { vertical-align: top; padding: 0 0 10px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 50px; max-width: 200px; margin-bottom: 4px; display: block; }
        .org h1 { margin: 0; font-size: 18px; color: #1b2b4b; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 10px; }
        .doc-title h2 { margin: 0; font-size: 18px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 10px; color: #6b7280; margin-top: 3px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-bottom: 14px; }
        table.stats td { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; background: #fafafa; width: 33%; vertical-align: top; }
        .stat-value { font-size: 16px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; color: #1b2b4b; }
        .stat-value.green { color: #047857; }
        .stat-value.red   { color: #b91c1c; }
        .stat-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 4px; font-weight: 600; }
        .stat-sub { font-size: 9px; color: #6b7280; margin-top: 3px; }

        .chart-wrap { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 14px; }
        .chart-wrap h3 { margin: 0 0 6px; font-size: 11px; color: #1b2b4b; }
        .chart-wrap .legend { display: table; width: 100%; margin-top: 10px; font-size: 9px; }
        .chart-wrap .legend-row { display: table-row; }
        .chart-wrap .legend-cell { display: table-cell; padding: 2px 8px 2px 0; }
        .swatch { display: inline-block; width: 9px; height: 9px; border-radius: 2px; vertical-align: middle; margin-right: 3px; }

        table.detail { width: 100%; border-collapse: collapse; font-size: 9.5px; margin-bottom: 14px; }
        .detail thead th {
            background: #1b2b4b; color: #fff; text-align: right; padding: 6px 7px;
            font-weight: 600; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .detail thead th.left { text-align: left; }
        .detail tbody td { padding: 4px 7px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; text-align: right; font-variant-numeric: tabular-nums; }
        .detail tbody td.left { text-align: left; }
        .detail tbody td.zero { color: #d1d5db; }
        .detail tbody td.total-col { background: #f3f4f6; font-weight: 700; }
        .detail .total-row td { border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: 800; }

        .insights { border: 1px solid #e5e7eb; border-left: 4px solid #f97316; border-radius: 8px; padding: 12px 16px; background: #fffbeb; }
        .insights h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 18px; }
        .insights li { margin: 4px 0; color: #4b5563; font-size: 10.5px; line-height: 1.55; }

        .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
        .footer .right { float: right; }

        @media print {
            body { padding: 8mm; }
            @page { size: A4 landscape; margin: 8mm; }
            tr { page-break-inside: avoid; }
            .insights, .chart-wrap, table.detail { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">

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
            <td>
                @if (! empty($branding['logo_src']))
                    <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}" style="max-height:50px; max-width:200px; margin-bottom:4px; display:block;">
                @endif
                <div class="org">
                    <h1>{{ $branding['app_name'] }}</h1>
                    <p>Category Trend Report</p>
                </div>
            </td>
            <td class="right">
                <div class="doc-title">
                    <h2>CATEGORY TREND</h2>
                    <div class="meta">{{ $directionLabel }} · {{ $period['label'] }}</div>
                    <div class="meta">{{ count($data['months']) }} monthly buckets</div>
                    <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- KPI strip ─────────────────────────────────────────── --}}
    <table class="stats">
        <tr>
            <td>
                <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['totals']['period']) }}</div>
                <div class="stat-label">Period Total</div>
                <div class="stat-sub">{{ count($data['series']) }} categories · {{ count($data['months']) }} months</div>
            </td>
            <td>
                @if ($data['leaders']['top_grower'] && ($data['leaders']['top_grower']['delta'] ?? 0) > 0)
                    <div class="stat-value green" style="font-size:13px;">{{ $data['leaders']['top_grower']['name'] }}</div>
                    <div class="stat-label">Top Grower</div>
                    <div class="stat-sub">▲ {{ number_format($data['leaders']['top_grower']['delta'] * 100, 0) }}% first→last</div>
                @else
                    <div class="stat-value" style="color:#9ca3af;">—</div>
                    <div class="stat-label">Top Grower</div>
                @endif
            </td>
            <td>
                @if ($data['leaders']['top_shrinker'] && ($data['leaders']['top_shrinker']['delta'] ?? 0) < 0)
                    <div class="stat-value red" style="font-size:13px;">{{ $data['leaders']['top_shrinker']['name'] }}</div>
                    <div class="stat-label">Top Shrinker</div>
                    <div class="stat-sub">▼ {{ number_format(abs($data['leaders']['top_shrinker']['delta']) * 100, 0) }}% first→last</div>
                @else
                    <div class="stat-value" style="color:#9ca3af;">—</div>
                    <div class="stat-label">Top Shrinker</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Trend line chart ──────────────────────────────────── --}}
    <div class="chart-wrap">
        <h3>Monthly Trend — {{ $directionLabel }}</h3>
        @if (! empty($data['series']) && $data['totals']['period'] > 0)
            {!! \App\Support\SvgChart::line($seriesForChart, $data['month_labels'], [
                'width'  => 1100,
                'height' => 240,
                'colors' => $colorMap,
            ]) !!}
            <div class="legend">
                @foreach ($data['series'] as $s)
                    <span class="legend-cell">
                        <span class="swatch" style="background: {{ $s['color'] }};"></span>
                        {{ $s['name'] }} — {{ \App\Services\FinanceReportService::usd($s['total']) }}
                    </span>
                @endforeach
            </div>
        @else
            <div style="text-align:center; color:#9ca3af; padding:20px;">No data for this period.</div>
        @endif
    </div>

    {{-- Detail table ──────────────────────────────────────── --}}
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
                <tr><td colspan="{{ count($data['month_labels']) + 3 }}" style="text-align:center; color:#9ca3af; padding:14px;">No data for this period.</td></tr>
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

</div>

@if (! empty($autoPrint))
<script>
    window.addEventListener('load', () => { setTimeout(() => window.print(), 250); });
</script>
@endif
</body>
</html>
