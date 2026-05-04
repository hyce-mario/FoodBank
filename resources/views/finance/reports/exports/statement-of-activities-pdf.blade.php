<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of Activities — {{ $branding['app_name'] }}</title>
    <style>
        @page { margin: 30px 24px 40px 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9.5px;
            line-height: 1.4;
        }

        /* Header — table layout (dompdf v3 has limited flex support) */
        table.header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #1b2b4b;
            margin-bottom: 12px;
        }
        table.header td { vertical-align: top; padding: 0 0 10px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 48px; max-width: 200px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 15px; color: #1b2b4b; font-weight: bold; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 9px; }
        .doc-title h2 { margin: 0; font-size: 16px; color: #111; letter-spacing: 0.04em; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 3px; }

        /* Stat strip — 3 cells in a row */
        table.stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 12px;
        }
        table.stats td {
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            background: #fafafa;
            padding: 8px 10px;
            width: 33%;
        }
        .stat-value { font-size: 14px; font-weight: bold; }
        .stat-value.income { color: #047857; }
        .stat-value.expense { color: #b91c1c; }
        .stat-value.net-positive { color: #047857; }
        .stat-value.net-negative { color: #b91c1c; }
        .stat-label {
            font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.05em;
            color: #6b7280; margin-top: 2px; font-weight: bold;
        }
        .stat-compare { font-size: 8px; color: #6b7280; margin-top: 2px; }

        /* Charts — 2 cells side-by-side */
        table.charts {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 12px;
        }
        table.charts td {
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            padding: 10px;
            background: #fff;
            width: 50%;
            vertical-align: top;
        }
        table.charts h3 { margin: 0 0 4px; font-size: 10px; color: #1b2b4b; }
        table.charts .sub { font-size: 8px; color: #9ca3af; margin: 0 0 8px; }
        table.legend {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 8px;
        }
        table.legend td {
            padding: 1px 4px;
            border: none;
            background: transparent;
        }
        table.legend .swatch {
            display: inline-block; width: 7px; height: 7px;
            border-radius: 2px;
        }

        /* Detail table */
        table.detail {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 14px;
        }
        .detail thead th {
            background: #1b2b4b; color: #fff;
            text-align: left; padding: 6px 7px;
            font-weight: bold; font-size: 8px;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .detail thead th.right { text-align: right; }
        .detail tbody td {
            padding: 5px 7px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        .detail tbody td.num { text-align: right; }
        .detail .section-row td {
            background: #f9fafb;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 8px;
            color: #1b2b4b;
            padding-top: 8px;
            padding-bottom: 4px;
        }
        .detail .total-row td {
            border-top: 1px solid #d1d5db;
            background: #f3f4f6;
            font-weight: bold;
        }
        .detail .net-row td {
            border-top: 2px solid #1b2b4b;
            background: #f9fafb;
            font-weight: bold;
            font-size: 11px;
            padding-top: 9px;
            padding-bottom: 9px;
        }
        .net-positive { color: #047857; }
        .net-negative { color: #b91c1c; }

        /* Insights */
        .insights {
            border: 1px solid #e5e7eb;
            border-left: 3px solid #f97316;
            border-radius: 5px;
            padding: 10px 12px;
            background: #fffbeb;
        }
        .insights h3 { margin: 0 0 6px; font-size: 10px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 14px; }
        .insights li { margin: 2px 0; color: #4b5563; font-size: 9px; }

        /* Page footer */
        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
        .footer .left  { float: left; }
        .footer .right { float: right; }
        .page-num:after { content: counter(page); }
        .page-total:after { content: counter(pages); }
    </style>
</head>
<body>

@php
    $hasCompare = ! empty($period['compare']);
    $netChange  = $data['net_change'];

    $incomeSegments = collect($data['income']['categories'])->map(fn ($c) => [
        'label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color'],
    ])->values()->all();
    $expenseSegments = collect($data['expense']['categories'])->map(fn ($c) => [
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
            <p>Statement of Activities</p>
        </td>
        <td class="right doc-title">
            <h2>STATEMENT OF ACTIVITIES</h2>
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
            <div class="stat-value income">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</div>
            <div class="stat-label">Income</div>
            @if ($hasCompare && isset($data['income']['prior_total']) && $data['income']['prior_total'] > 0)
                @php $delta = ($data['income']['total'] - $data['income']['prior_total']) / $data['income']['prior_total']; @endphp
                <div class="stat-compare">{{ $delta >= 0 ? '+' : '' }}{{ number_format($delta * 100, 0) }}% vs. prior</div>
            @endif
        </td>
        <td>
            <div class="stat-value expense">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</div>
            <div class="stat-label">Expenses</div>
            @if ($hasCompare && isset($data['expense']['prior_total']) && $data['expense']['prior_total'] > 0)
                @php $delta = ($data['expense']['total'] - $data['expense']['prior_total']) / $data['expense']['prior_total']; @endphp
                <div class="stat-compare">{{ $delta >= 0 ? '+' : '' }}{{ number_format($delta * 100, 0) }}% vs. prior</div>
            @endif
        </td>
        <td>
            <div class="stat-value {{ $netChange >= 0 ? 'net-positive' : 'net-negative' }}">{{ $netChange >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($netChange) }}</div>
            <div class="stat-label">Change in Net Assets</div>
        </td>
    </tr>
</table>

{{-- Dual donut ────────────────────────────────────────────────── --}}
<table class="charts">
    <tr>
        <td>
            <h3>Income by Category</h3>
            <p class="sub">{{ $period['label'] }}</p>
            {!! \App\Support\SvgChart::donut($incomeSegments, [
                'width' => 200, 'height' => 180,
                'center_label' => \App\Services\FinanceReportService::usd($data['income']['total']),
                'center_sub' => 'Total',
            ]) !!}
            @if (! empty($incomeSegments))
                <table class="legend">
                    @foreach ($data['income']['categories'] as $cat)
                        <tr>
                            <td style="width:14px;"><span class="swatch" style="background: {{ $cat['color'] }};"></span></td>
                            <td>{{ $cat['name'] }}</td>
                            <td style="text-align:right; color:#9ca3af;">{{ number_format($cat['share'] * 100, 0) }}%</td>
                            <td style="text-align:right; font-weight:bold;">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>
        <td>
            <h3>Expenses by Category</h3>
            <p class="sub">{{ $period['label'] }}</p>
            {!! \App\Support\SvgChart::donut($expenseSegments, [
                'width' => 200, 'height' => 180,
                'center_label' => \App\Services\FinanceReportService::usd($data['expense']['total']),
                'center_sub' => 'Total',
            ]) !!}
            @if (! empty($expenseSegments))
                <table class="legend">
                    @foreach ($data['expense']['categories'] as $cat)
                        <tr>
                            <td style="width:14px;"><span class="swatch" style="background: {{ $cat['color'] }};"></span></td>
                            <td>{{ $cat['name'] }}</td>
                            <td style="text-align:right; color:#9ca3af;">{{ number_format($cat['share'] * 100, 0) }}%</td>
                            <td style="text-align:right; font-weight:bold;">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>
    </tr>
</table>

{{-- Detail ──────────────────────────────────────────────────── --}}
<table class="detail">
    <thead>
        <tr>
            <th>Category</th>
            <th class="right">Amount</th>
            @if ($hasCompare)
                <th class="right">Prior Period</th>
                <th class="right">Δ %</th>
            @endif
        </tr>
    </thead>
    <tbody>
        <tr class="section-row"><td colspan="{{ $hasCompare ? 4 : 2 }}">Revenue</td></tr>
        @forelse ($data['income']['categories'] as $cat)
            <tr>
                <td>{{ $cat['name'] }}</td>
                <td class="num">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                @if ($hasCompare)
                    <td class="num">{{ \App\Services\FinanceReportService::usd($cat['prior_amount'] ?? 0) }}</td>
                    <td class="num">
                        @if ($cat['delta'] !== null)
                            {{ $cat['delta'] >= 0 ? '+' : '' }}{{ number_format($cat['delta'] * 100, 1) }}%
                        @else
                            <span style="color:#9ca3af;">new</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $hasCompare ? 4 : 2 }}" style="color:#9ca3af; text-align:center;">No income recorded.</td></tr>
        @endforelse
        <tr class="total-row">
            <td>Total Revenue</td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</td>
            @if ($hasCompare)
                <td class="num">{{ \App\Services\FinanceReportService::usd($data['income']['prior_total'] ?? 0) }}</td>
                <td></td>
            @endif
        </tr>

        <tr class="section-row"><td colspan="{{ $hasCompare ? 4 : 2 }}">Expenses</td></tr>
        @forelse ($data['expense']['categories'] as $cat)
            <tr>
                <td>{{ $cat['name'] }}</td>
                <td class="num">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                @if ($hasCompare)
                    <td class="num">{{ \App\Services\FinanceReportService::usd($cat['prior_amount'] ?? 0) }}</td>
                    <td class="num">
                        @if ($cat['delta'] !== null)
                            {{ $cat['delta'] >= 0 ? '+' : '' }}{{ number_format($cat['delta'] * 100, 1) }}%
                        @else
                            <span style="color:#9ca3af;">new</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $hasCompare ? 4 : 2 }}" style="color:#9ca3af; text-align:center;">No expenses recorded.</td></tr>
        @endforelse
        <tr class="total-row">
            <td>Total Expenses</td>
            <td class="num">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</td>
            @if ($hasCompare)
                <td class="num">{{ \App\Services\FinanceReportService::usd($data['expense']['prior_total'] ?? 0) }}</td>
                <td></td>
            @endif
        </tr>

        <tr class="net-row">
            <td>Change in Net Assets</td>
            <td class="num {{ $netChange >= 0 ? 'net-positive' : 'net-negative' }}">
                {{ $netChange >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($netChange) }}
            </td>
            @if ($hasCompare)
                <td class="num">
                    @if ($data['prior_net'] !== null)
                        <span class="{{ $data['prior_net'] >= 0 ? 'net-positive' : 'net-negative' }}">
                            {{ $data['prior_net'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['prior_net']) }}
                        </span>
                    @endif
                </td>
                <td></td>
            @endif
        </tr>
    </tbody>
</table>

{{-- Insights ──────────────────────────────────────────────── --}}
<div class="insights">
    <h3>Insights</h3>
    <ul>
        @foreach ($data['insights'] as $bullet)
            <li>{{ $bullet }}</li>
        @endforeach
    </ul>
</div>

<div class="footer">
    <span class="left">{{ $branding['app_name'] }} · Statement of Activities · {{ $period['label'] }}</span>
    <span class="right">Page <span class="page-num"></span> of <span class="page-total"></span></span>
</div>

</body>
</html>
