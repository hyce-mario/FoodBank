<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }} — {{ $branding['app_name'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 9.5px; line-height: 1.4; margin: 0; padding: 24px; }

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 14px; }
        table.header td { vertical-align: top; padding: 0 0 10px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 44px; max-width: 180px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 14px; color: #1b2b4b; font-weight: bold; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 9px; }
        .doc-title h2 { margin: 0; font-size: 14px; color: #111; letter-spacing: 0.04em; font-weight: bold; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 3px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 12px; }
        table.stats td { border: 1px solid #e5e7eb; background: #fafafa; padding: 8px 10px; width: 33%; vertical-align: top; }
        .stat-value { font-size: 14px; font-weight: bold; color: #1b2b4b; }
        .stat-label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 2px; font-weight: bold; }
        .stat-compare { font-size: 8px; color: #6b7280; margin-top: 2px; }

        .composition { border: 1px solid #e5e7eb; padding: 10px; background: #fff; margin-bottom: 12px; }
        .composition h3 { margin: 0 0 8px; font-size: 10px; color: #1b2b4b; font-weight: bold; }
        .composition .legend { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 8px; }
        .composition .legend td { padding: 1px 4px; }
        .swatch { display: inline-block; width: 7px; height: 7px; }

        table.detail { width: 100%; border-collapse: collapse; font-size: 8.5px; margin-bottom: 12px; }
        .detail thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 6px 7px; font-weight: bold; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
        .detail thead th.right { text-align: right; }
        .detail tbody td { padding: 4px 7px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .detail tbody td.num { text-align: right; }
        .detail .group-row td { background: #f9fafb; font-weight: bold; font-size: 8px; color: #1b2b4b; padding-top: 7px; padding-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
        .detail .total-row td { border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: bold; font-size: 10px; padding-top: 8px; padding-bottom: 8px; }

        .insights { border: 1px solid #e5e7eb; border-left: 3px solid #f97316; padding: 10px 12px; background: #fffbeb; margin-bottom: 14px; }
        .insights h3 { margin: 0 0 6px; font-size: 10px; color: #92400e; font-weight: bold; }
        .insights ul { margin: 0; padding-left: 14px; }
        .insights li { margin: 2px 0; color: #4b5563; font-size: 9px; }

        .footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 7.5px; color: #9ca3af; }
        .footer .right { float: right; }
    </style>
</head>
<body>

@php
    $hasCompare = ! empty($period['compare']);
    $stackedSegments = collect($data['by_category'])->map(fn ($c) => [
        'label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color'],
    ])->values()->all();
@endphp

<table class="header">
    <tr>
        <td class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>{{ $reportTitle }}</p>
        </td>
        <td class="right doc-title">
            <h2>{{ strtoupper($reportTitle) }}</h2>
            <div class="meta">For the period: {{ $period['label'] }}</div>
            @if ($hasCompare)
                <div class="meta">Compared to: {{ $period['compare']['label'] }}</div>
            @endif
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </td>
    </tr>
</table>

{{-- KPI strip ─────────────────────────────────────────────────── --}}
<table class="stats">
    <tr>
        <td>
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['total']) }}</div>
            <div class="stat-label">{{ $totalLabel }}</div>
            @if ($hasCompare && $data['prior_total'] !== null && $data['prior_total'] > 0)
                @php $delta = ($data['total'] - $data['prior_total']) / $data['prior_total']; @endphp
                <div class="stat-compare">{{ $delta >= 0 ? '+' : '' }}{{ number_format($delta * 100, 0) }}% vs. prior</div>
            @endif
        </td>
        <td>
            <div class="stat-value">{{ $data['count'] }}</div>
            <div class="stat-label">Transactions</div>
            <div class="stat-compare">across {{ count($data['by_category']) }} {{ count($data['by_category']) === 1 ? 'category' : 'categories' }}</div>
        </td>
        <td>
            @if ($data['top_source'])
                <div class="stat-value" style="font-size:11px;">{{ $data['top_source']['name'] }}</div>
                <div class="stat-label">Top {{ strtolower($sourceLabel) }}</div>
                <div class="stat-compare">{{ \App\Services\FinanceReportService::usd($data['top_source']['amount']) }}</div>
            @else
                <div class="stat-value" style="color:#9ca3af;">—</div>
                <div class="stat-label">Top {{ strtolower($sourceLabel) }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- Composition ───────────────────────────────────────────────── --}}
<div class="composition">
    <h3>Composition — {{ $period['label'] }}</h3>
    @if (! empty($stackedSegments))
        <div style="text-align:center;">
            {!! \App\Support\SvgChart::horizontalStackedBar($stackedSegments, ['width' => 540, 'height' => 26]) !!}
        </div>
        <table class="legend">
            @foreach ($data['by_category'] as $cat)
                <tr>
                    <td style="width:12px;"><span class="swatch" style="background: {{ $cat['color'] }};"></span></td>
                    <td>{{ $cat['name'] }}</td>
                    <td style="text-align:right; color:#9ca3af;">{{ number_format($cat['share'] * 100, 0) }}%</td>
                    <td style="text-align:right; font-weight:bold;">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <div style="text-align:center; color:#9ca3af; padding:10px 0;">No data for this period.</div>
    @endif
</div>

{{-- Detail table ───────────────────────────────────────────────── --}}
<table class="detail">
    <thead>
        <tr>
            <th style="width:65px;">Date</th>
            <th>Title / {{ $sourceLabel }}</th>
            <th>Event</th>
            <th class="right" style="width:75px;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data['by_category'] as $cat)
            <tr class="group-row">
                <td colspan="4">
                    <span class="swatch" style="background: {{ $cat['color'] }};"></span>
                    {{ $cat['name'] }}
                    <span style="float:right; font-weight:normal; text-transform:none; letter-spacing:normal; color:#6b7280;">
                        {{ $cat['count'] }} {{ $cat['count'] === 1 ? 'txn' : 'txns' }} · {{ \App\Services\FinanceReportService::usd($cat['amount']) }}
                    </span>
                </td>
            </tr>
            @foreach (collect($data['rows'])->where('category', $cat['name']) as $row)
                <tr>
                    <td class="num">{{ $row['date'] }}</td>
                    <td>
                        <strong>{{ $row['title'] }}</strong>
                        @if ($row['source'])
                            <span style="color:#9ca3af;"> — {{ $row['source'] }}</span>
                        @endif
                    </td>
                    <td>{{ $row['event'] ?: '—' }}</td>
                    <td class="num">{{ \App\Services\FinanceReportService::usd($row['amount']) }}</td>
                </tr>
            @endforeach
        @endforeach

        @if (empty($data['rows']))
            <tr><td colspan="4" style="text-align:center; color:#9ca3af; padding:14px;">No transactions match the applied filters.</td></tr>
        @endif

        <tr class="total-row">
            <td colspan="3">{{ $totalLabel }}</td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
        </tr>
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
    <span>{{ $branding['app_name'] }} · {{ $reportTitle }} · {{ $period['label'] }}</span>
</div>

</body>
</html>
