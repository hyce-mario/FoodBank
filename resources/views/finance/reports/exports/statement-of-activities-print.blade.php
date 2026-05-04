<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of Activities — {{ $branding['app_name'] }}</title>
    <style>
        /* Print-engine compatibility: tables instead of CSS Grid for the
           stat strip + chart row. Some print engines + iOS Safari Print
           don't reliably render CSS Grid even when the screen browser
           does. Tables are universally supported and produce
           identical output across browsers + print preview. */
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 24px;
            font-size: 12px;
            line-height: 1.5;
        }
        .sheet { max-width: 1100px; margin: 0 auto; }

        /* Header */
        table.header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #1b2b4b;
            margin-bottom: 18px;
        }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }

        /* KPI strip — table cells with explicit 33% widths */
        table.stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-bottom: 18px;
        }
        table.stats td {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            background: #fafafa;
            width: 33%;
            vertical-align: top;
        }
        .stat-value {
            font-size: 22px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .stat-value.income { color: #047857; }
        .stat-value.expense { color: #b91c1c; }
        .stat-value.net-positive { color: #047857; }
        .stat-value.net-negative { color: #b91c1c; }
        .stat-label {
            font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; margin-top: 6px; font-weight: 600;
        }
        .stat-compare { font-size: 10px; color: #6b7280; margin-top: 4px; }

        /* Charts — 2 cells side-by-side */
        table.charts {
            width: 100%;
            border-collapse: separate;
            border-spacing: 16px 0;
            margin-bottom: 18px;
        }
        table.charts td {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
            background: #fff;
            width: 50%;
            vertical-align: top;
        }
        table.charts h3 { margin: 0 0 4px; font-size: 12px; color: #1b2b4b; }
        table.charts .sub { font-size: 10px; color: #9ca3af; margin: 0 0 10px; }
        .chart-wrap { text-align: center; }

        /* Legend — also a table for tight alignment */
        table.legend {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10px;
        }
        table.legend td {
            padding: 2px 4px;
            border: none;
            background: transparent;
        }
        .swatch {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 2px;
        }

        /* Detail */
        table.detail {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 18px;
        }
        .detail thead th {
            background: #1b2b4b; color: #fff;
            text-align: left; padding: 8px 10px;
            font-weight: 600; font-size: 10px;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .detail thead th.right { text-align: right; }
        .detail tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        .detail tbody td.num { font-variant-numeric: tabular-nums; text-align: right; }
        .detail .section-row td {
            background: #f9fafb;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: 10px;
            color: #1b2b4b;
            padding-top: 10px;
            padding-bottom: 6px;
        }
        .detail .total-row td {
            border-top: 1px solid #d1d5db;
            background: #f3f4f6;
            font-weight: 800;
        }
        .detail .net-row td {
            border-top: 2px solid #1b2b4b;
            background: #f9fafb;
            font-weight: 800;
            font-size: 13px;
            padding-top: 12px;
            padding-bottom: 12px;
        }
        .net-positive { color: #047857; }
        .net-negative { color: #b91c1c; }

        /* Insights */
        .insights {
            border: 1px solid #e5e7eb;
            border-left: 4px solid #f97316;
            border-radius: 8px;
            padding: 12px 16px;
            background: #fffbeb;
        }
        .insights h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 18px; }
        .insights li { margin: 4px 0; color: #4b5563; font-size: 11px; line-height: 1.55; }

        .footer {
            margin-top: 22px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
        }
        .footer .right { float: right; }

        @media print {
            body { padding: 12px; }
            .no-print { display: none !important; }
            tr { page-break-inside: avoid; }
            .insights, table.charts, table.detail { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">

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
            <td>
                <div class="org">
                    @if (! empty($branding['logo_src']))
                        <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
                    @endif
                    <h1>{{ $branding['app_name'] }}</h1>
                    <p>Statement of Activities</p>
                </div>
            </td>
            <td class="right">
                <div class="doc-title">
                    <h2>STATEMENT OF ACTIVITIES</h2>
                    <div class="meta">For the period: {{ $period['label'] }}</div>
                    @if ($hasCompare)
                        <div class="meta">Compared to: {{ $period['compare']['label'] }}</div>
                    @endif
                    <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- KPI strip ─────────────────────────────────────────────────── --}}
    <table class="stats">
        <tr>
            <td>
                <div class="stat-value income">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</div>
                <div class="stat-label">Income</div>
                @if ($hasCompare && isset($data['income']['prior_total']) && $data['income']['prior_total'] > 0)
                    @php $delta = ($data['income']['total'] - $data['income']['prior_total']) / $data['income']['prior_total']; @endphp
                    <div class="stat-compare">{{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. prior</div>
                @endif
            </td>
            <td>
                <div class="stat-value expense">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</div>
                <div class="stat-label">Expenses</div>
                @if ($hasCompare && isset($data['expense']['prior_total']) && $data['expense']['prior_total'] > 0)
                    @php $delta = ($data['expense']['total'] - $data['expense']['prior_total']) / $data['expense']['prior_total']; @endphp
                    <div class="stat-compare">{{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. prior</div>
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
                <div class="chart-wrap">
                    {!! \App\Support\SvgChart::donut($incomeSegments, [
                        'width' => 240, 'height' => 220,
                        'center_label' => \App\Services\FinanceReportService::usd($data['income']['total']),
                        'center_sub' => 'Total',
                    ]) !!}
                </div>
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
                <div class="chart-wrap">
                    {!! \App\Support\SvgChart::donut($expenseSegments, [
                        'width' => 240, 'height' => 220,
                        'center_label' => \App\Services\FinanceReportService::usd($data['expense']['total']),
                        'center_sub' => 'Total',
                    ]) !!}
                </div>
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

    {{-- Detail table ──────────────────────────────────────────────── --}}
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
                                <span style="color: #9ca3af;">new</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $hasCompare ? 4 : 2 }}" style="color: #9ca3af; text-align: center;">No income recorded.</td></tr>
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
                                <span style="color: #9ca3af;">new</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $hasCompare ? 4 : 2 }}" style="color: #9ca3af; text-align: center;">No expenses recorded.</td></tr>
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

    {{-- Insights ──────────────────────────────────────────────────── --}}
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
        <span>{{ $branding['app_name'] }} · Statement of Activities · {{ $period['label'] }}</span>
    </div>

</div>

@if (! empty($autoPrint))
<script>
    window.addEventListener('load', () => {
        setTimeout(() => window.print(), 250);
    });
</script>
@endif
</body>
</html>
