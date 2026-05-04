<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of Activities — {{ $branding['app_name'] }}</title>
    <style>
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

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1b2b4b;
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        .stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            background: #fafafa;
        }
        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .stat-card.income  .stat-value { color: #047857; }
        .stat-card.expense .stat-value { color: #b91c1c; }
        .stat-card.net.positive .stat-value { color: #047857; }
        .stat-card.net.negative .stat-value { color: #b91c1c; }
        .stat-card .stat-label {
            font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; margin-top: 6px; font-weight: 600;
        }
        .stat-card .stat-compare { font-size: 10px; color: #6b7280; margin-top: 4px; }

        .charts {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 18px;
        }
        .chart-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
            background: #fff;
        }
        .chart-card h3 { margin: 0 0 4px; font-size: 12px; color: #1b2b4b; }
        .chart-card .sub { font-size: 10px; color: #9ca3af; margin: 0 0 10px; }
        .chart-card .legend { margin-top: 10px; font-size: 10px; }
        .chart-card .legend li {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 2px 0;
        }
        .chart-card .legend .swatch {
            display: inline-block; width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0;
        }
        .chart-card .legend .name { flex: 1; color: #4b5563; }
        .chart-card .legend .pct  { color: #9ca3af; min-width: 28px; text-align: right; }
        .chart-card .legend .amt  { color: #1f2937; font-weight: 700; min-width: 64px; text-align: right; }

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
        .detail tbody td.num   { font-variant-numeric: tabular-nums; text-align: right; }
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
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { padding: 12px; }
            .no-print { display: none !important; }
            tr { page-break-inside: avoid; }
            .insights, .charts, table.detail { page-break-inside: avoid; }
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

    <div class="header">
        <div class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>Statement of Activities</p>
        </div>
        <div class="doc-title">
            <h2>STATEMENT OF ACTIVITIES</h2>
            <div class="meta">For the period: {{ $period['label'] }}</div>
            @if ($hasCompare)
                <div class="meta">Compared to: {{ $period['compare']['label'] }}</div>
            @endif
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </div>
    </div>

    {{-- KPI strip ─────────────────────────────────────────────────── --}}
    <div class="stat-strip">
        <div class="stat-card income">
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</div>
            <div class="stat-label">Income</div>
            @if ($hasCompare && isset($data['income']['prior_total']) && $data['income']['prior_total'] > 0)
                @php $delta = ($data['income']['total'] - $data['income']['prior_total']) / $data['income']['prior_total']; @endphp
                <div class="stat-compare">
                    {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. prior
                </div>
            @endif
        </div>
        <div class="stat-card expense">
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</div>
            <div class="stat-label">Expenses</div>
            @if ($hasCompare && isset($data['expense']['prior_total']) && $data['expense']['prior_total'] > 0)
                @php $delta = ($data['expense']['total'] - $data['expense']['prior_total']) / $data['expense']['prior_total']; @endphp
                <div class="stat-compare">
                    {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. prior
                </div>
            @endif
        </div>
        <div class="stat-card net {{ $netChange >= 0 ? 'positive' : 'negative' }}">
            <div class="stat-value">{{ $netChange >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($netChange) }}</div>
            <div class="stat-label">Change in Net Assets</div>
        </div>
    </div>

    {{-- Dual donut ────────────────────────────────────────────────── --}}
    <div class="charts">
        <div class="chart-card">
            <h3>Income by Category</h3>
            <p class="sub">{{ $period['label'] }}</p>
            {!! \App\Support\SvgChart::donut($incomeSegments, [
                'width' => 240, 'height' => 220,
                'center_label' => \App\Services\FinanceReportService::usd($data['income']['total']),
                'center_sub' => 'Total',
            ]) !!}
            @if (! empty($incomeSegments))
                <ul class="legend">
                    @foreach ($data['income']['categories'] as $cat)
                        <li>
                            <span class="swatch" style="background: {{ $cat['color'] }};"></span>
                            <span class="name">{{ $cat['name'] }}</span>
                            <span class="pct">{{ number_format($cat['share'] * 100, 0) }}%</span>
                            <span class="amt">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="chart-card">
            <h3>Expenses by Category</h3>
            <p class="sub">{{ $period['label'] }}</p>
            {!! \App\Support\SvgChart::donut($expenseSegments, [
                'width' => 240, 'height' => 220,
                'center_label' => \App\Services\FinanceReportService::usd($data['expense']['total']),
                'center_sub' => 'Total',
            ]) !!}
            @if (! empty($expenseSegments))
                <ul class="legend">
                    @foreach ($data['expense']['categories'] as $cat)
                        <li>
                            <span class="swatch" style="background: {{ $cat['color'] }};"></span>
                            <span class="name">{{ $cat['name'] }}</span>
                            <span class="pct">{{ number_format($cat['share'] * 100, 0) }}%</span>
                            <span class="amt">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

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
        <h3>📊 Insights</h3>
        <ul>
            @foreach ($data['insights'] as $bullet)
                <li>{{ $bullet }}</li>
            @endforeach
        </ul>
    </div>

    <div class="footer">
        <span>{{ $branding['app_name'] }} · Statement of Activities · Generated {{ now()->format('Y-m-d H:i') }}</span>
        <span>{{ $period['label'] }}</span>
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
