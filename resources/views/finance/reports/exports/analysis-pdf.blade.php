<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }} — {{ $branding['app_name'] }}</title>
    <style>
        /* dompdf-tuned analysis report. DejaVu Sans (bundled with dompdf),
           tables only (no Grid), no fixed-position footer. Sparklines work
           in dompdf because they're built from <path> + <circle>, not
           path arcs — same primitives that render the donut PDF
           horizontalStackedBar fallback faithfully. */
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9.5px;
            line-height: 1.4;
            margin: 0;
            padding: 24px;
        }

        table.header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #1b2b4b;
            margin-bottom: 14px;
        }
        table.header td { vertical-align: top; padding: 0 0 10px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 44px; max-width: 180px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 14px; color: #1b2b4b; font-weight: bold; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 9px; }
        .doc-title h2 { margin: 0; font-size: 14px; color: #111; letter-spacing: 0.04em; font-weight: bold; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 3px; }

        table.stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 12px;
        }
        table.stats td {
            border: 1px solid #e5e7eb;
            background: #fafafa;
            padding: 8px 10px;
            width: 25%;
            vertical-align: top;
        }
        .stat-value { font-size: 13px; font-weight: bold; color: #1b2b4b; }
        .stat-label {
            font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.05em;
            color: #6b7280; margin-top: 2px; font-weight: bold;
        }
        .stat-compare { font-size: 8px; color: #6b7280; margin-top: 2px; }

        table.donor-table { width: 100%; border-collapse: collapse; font-size: 8.5px; margin-bottom: 12px; }
        .donor-table thead th {
            background: #1b2b4b; color: #fff; text-align: left; padding: 5px 6px;
            font-weight: bold; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .donor-table thead th.right  { text-align: right; }
        .donor-table thead th.center { text-align: center; }
        .donor-table tbody td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .donor-table tbody td.num    { text-align: right; }
        .donor-table tbody td.center { text-align: center; }
        .donor-table .rank-col { width: 18px; color: #9ca3af; }
        .donor-table .swatch {
            display: inline-block; width: 7px; height: 7px; margin-right: 3px;
        }
        .donor-table .new-tag {
            display: inline-block; padding: 0 3px;
            font-size: 6.5px; font-weight: bold;
            background: #fef3c7; color: #92400e; margin-left: 3px;
        }
        .donor-table .total-row td {
            border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: bold;
            font-size: 9px; padding-top: 7px; padding-bottom: 7px;
        }

        .lapsed-section {
            border: 1px solid #fde68a;
            border-left: 3px solid #f59e0b;
            padding: 10px 12px;
            background: #fffbeb;
            margin-bottom: 12px;
        }
        .lapsed-section h3 { margin: 0 0 6px; font-size: 9px; color: #92400e; font-weight: bold; }
        .lapsed-section table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
        .lapsed-section td { padding: 1px 0; }
        .lapsed-section td.right { text-align: right; color: #92400e; font-weight: bold; }

        .insights {
            border: 1px solid #e5e7eb;
            border-left: 3px solid #f97316;
            padding: 10px 12px;
            background: #fffbeb;
            margin-bottom: 12px;
        }
        .insights h3 { margin: 0 0 6px; font-size: 10px; color: #92400e; font-weight: bold; }
        .insights ul { margin: 0; padding-left: 14px; }
        .insights li { margin: 2px 0; color: #4b5563; font-size: 9px; }

        .footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #9ca3af;
        }
        .footer .right { float: right; }
    </style>
</head>
<body>

@php
    $hasCompare = ! empty($period['compare']);
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

{{-- KPI strip ──────────────────────────────────────────────────── --}}
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
            <div class="stat-value">{{ $data['donor_total_count'] }}</div>
            <div class="stat-label">Unique {{ $entityLabelPlural }}</div>
            <div class="stat-compare">{{ $data['gift_count'] }} {{ $entityLabel === 'donor' ? 'gifts' : 'payments' }}</div>
        </td>
        <td>
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['avg_gift']) }}</div>
            <div class="stat-label">Avg {{ $entityLabel === 'donor' ? 'Gift' : 'Payment' }}</div>
        </td>
        <td>
            @if ($data['retention_rate'] !== null)
                <div class="stat-value">{{ number_format($data['retention_rate'] * 100, 0) }}%</div>
                <div class="stat-label">Retention</div>
            @else
                <div class="stat-value" style="color:#9ca3af;">—</div>
                <div class="stat-label">Retention</div>
            @endif
        </td>
    </tr>
</table>

{{-- Top donors / vendors ─────────────────────────────────────── --}}
<table class="donor-table">
    <thead>
        <tr>
            <th class="rank-col">#</th>
            <th>{{ ucfirst($entityLabel) }}</th>
            <th class="right">Total</th>
            <th class="center">Gifts</th>
            <th class="right">Avg</th>
            <th class="center">12-mo</th>
            @if ($hasCompare)
                <th class="right">Δ</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($data['donors'] as $i => $donor)
            <tr>
                <td class="rank-col">{{ $i + 1 }}</td>
                <td>
                    <span class="swatch" style="background: {{ $donor['color'] }};"></span>
                    <strong>{{ $donor['name'] }}</strong>
                    @if ($donor['is_new'])
                        <span class="new-tag">NEW</span>
                    @endif
                </td>
                <td class="num">
                    {{ \App\Services\FinanceReportService::usd($donor['total']) }}
                    <div style="color:#9ca3af; font-size:7px; font-weight:normal;">{{ number_format($donor['share'] * 100, 0) }}%</div>
                </td>
                <td class="center">{{ $donor['count'] }}</td>
                <td class="num">{{ \App\Services\FinanceReportService::usd($donor['avg_gift']) }}</td>
                <td class="center">
                    {!! \App\Support\SvgChart::sparkline($donor['sparkline'], ['color' => $donor['color'], 'width' => 60, 'height' => 16]) !!}
                </td>
                @if ($hasCompare)
                    <td class="num">
                        @if ($donor['delta'] !== null)
                            {{ $donor['delta'] >= 0 ? '+' : '' }}{{ number_format($donor['delta'] * 100, 0) }}%
                        @elseif ($donor['is_new'])
                            <span style="color:#92400e; font-weight:bold;">new</span>
                        @else
                            <span style="color:#9ca3af;">—</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $hasCompare ? 7 : 6 }}" style="text-align:center; color:#9ca3af; padding:14px;">No {{ $entityLabel }} activity in this period.</td></tr>
        @endforelse
        @if (! empty($data['donors']))
            <tr class="total-row">
                <td></td>
                <td>{{ $totalLabel }} (top {{ count($data['donors']) }})</td>
                <td class="num">{{ \App\Services\FinanceReportService::usd((float) array_sum(array_column($data['donors'], 'total'))) }}</td>
                <td class="center">{{ (int) array_sum(array_column($data['donors'], 'count')) }}</td>
                <td></td>
                <td></td>
                @if ($hasCompare)<td></td>@endif
            </tr>
        @endif
    </tbody>
</table>

{{-- Lapsed (compare only) ─────────────────────────────────── --}}
@if ($hasCompare && ! empty($data['lapsed']))
    <div class="lapsed-section">
        <h3>{{ count($data['lapsed']) }} Lapsed {{ count($data['lapsed']) === 1 ? ucfirst($entityLabel) : ucfirst($entityLabelPlural) }}</h3>
        <table>
            @foreach ($data['lapsed'] as $l)
                <tr>
                    <td>{{ $l['name'] }}</td>
                    <td class="right">{{ \App\Services\FinanceReportService::usd($l['prior_total']) }} prior</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

{{-- Insights ───────────────────────────────────────────────── --}}
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
