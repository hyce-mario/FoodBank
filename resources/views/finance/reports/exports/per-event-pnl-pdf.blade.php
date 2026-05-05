<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Per-Event P&L — {{ $data['event']['name'] }} — {{ $branding['app_name'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9.5px;
            line-height: 1.4;
            margin: 0;
            padding: 24px;
        }

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 12px; }
        table.header td { vertical-align: top; padding: 0 0 10px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 44px; max-width: 180px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 14px; color: #1b2b4b; font-weight: bold; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 9px; }
        .doc-title h2 { margin: 0; font-size: 14px; color: #111; letter-spacing: 0.04em; font-weight: bold; }
        .doc-title .event-name { font-size: 11px; font-weight: bold; color: #374151; margin-top: 3px; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 2px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 10px; }
        table.stats td { border: 1px solid #e5e7eb; background: #fafafa; padding: 7px 9px; vertical-align: top; }
        .stats-financial td { width: 33%; }
        .stats-beneficiary td { width: 25%; }
        .stats-beneficiary td.cost { background: #fffbeb; border-color: #fde68a; }

        .stat-value { font-size: 13px; font-weight: bold; }
        .stat-value.income { color: #047857; }
        .stat-value.expense { color: #b91c1c; }
        .stat-value.net-positive { color: #047857; }
        .stat-value.net-negative { color: #b91c1c; }
        .stat-value.cost { color: #92400e; }
        .stat-value.beneficiary { color: #1b2b4b; }
        .stat-label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 2px; font-weight: bold; }
        .stat-label.cost { color: #92400e; }
        .stat-sub { font-size: 7.5px; color: #6b7280; margin-top: 2px; }

        table.charts { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 12px; }
        table.charts td { border: 1px solid #e5e7eb; padding: 9px; background: #fff; width: 50%; vertical-align: top; }
        table.charts h3 { margin: 0 0 4px; font-size: 10px; color: #1b2b4b; font-weight: bold; }
        table.charts .sub { font-size: 8px; color: #9ca3af; margin: 0 0 6px; }
        .chart-wrap { text-align: center; }
        .swatch { display: inline-block; width: 7px; height: 7px; }
        table.legend { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 8px; }
        table.legend td { padding: 1px 4px; }

        table.detail { width: 100%; border-collapse: collapse; font-size: 8.5px; margin-bottom: 12px; }
        .detail thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 5px 6px; font-weight: bold; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.04em; }
        .detail thead th.right { text-align: right; }
        .detail tbody td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .detail tbody td.num { text-align: right; }
        .num.income { color: #047857; }
        .num.expense { color: #b91c1c; }
        .type-pill { display: inline-block; padding: 0 3px; font-size: 6.5px; font-weight: bold; }
        .type-pill.in { background: #d1fae5; color: #065f46; }
        .type-pill.out { background: #fee2e2; color: #991b1b; }

        .insights { border: 1px solid #e5e7eb; border-left: 3px solid #f97316; padding: 8px 10px; background: #fffbeb; margin-bottom: 12px; }
        .insights h3 { margin: 0 0 5px; font-size: 9.5px; color: #92400e; font-weight: bold; }
        .insights ul { margin: 0; padding-left: 14px; }
        .insights li { margin: 2px 0; color: #4b5563; font-size: 9px; }

        .footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
        .footer .right { float: right; }
    </style>
</head>
<body>

@php
    $incomeSegments  = collect($data['income']['categories'])->map(fn ($c) => ['label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color']])->values()->all();
    $expenseSegments = collect($data['expense']['categories'])->map(fn ($c) => ['label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color']])->values()->all();
    $eventDate = $data['event']['date'] ? \Carbon\Carbon::parse($data['event']['date'])->format('M j, Y') : '(undated)';
@endphp

<table class="header">
    <tr>
        <td class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>Per-Event P&amp;L</p>
        </td>
        <td class="right doc-title">
            <h2>PER-EVENT P&amp;L</h2>
            <div class="event-name">{{ $data['event']['name'] }}</div>
            <div class="meta">{{ $eventDate }} · {{ ucfirst((string) $data['event']['status']) }}</div>
            @if (! empty($data['event']['location']))
                <div class="meta">{{ $data['event']['location'] }}</div>
            @endif
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </td>
    </tr>
</table>

{{-- KPI strip — financial ──────────────────────────────────── --}}
<table class="stats stats-financial">
    <tr>
        <td>
            <div class="stat-value income">{{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</div>
            <div class="stat-label">Income</div>
        </td>
        <td>
            <div class="stat-value expense">{{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</div>
            <div class="stat-label">Expense</div>
        </td>
        <td>
            <div class="stat-value {{ $data['net'] >= 0 ? 'net-positive' : 'net-negative' }}">{{ $data['net'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['net']) }}</div>
            <div class="stat-label">Net</div>
        </td>
    </tr>
</table>

{{-- KPI strip — beneficiary ──────────────────────────────── --}}
<table class="stats stats-beneficiary">
    <tr>
        <td>
            <div class="stat-value beneficiary">{{ number_format($data['households_served']) }}</div>
            <div class="stat-label">Households Served</div>
            <div class="stat-sub">snapshot at visit</div>
        </td>
        <td>
            <div class="stat-value beneficiary">{{ number_format($data['people_served']) }}</div>
            <div class="stat-label">People Served</div>
        </td>
        <td class="cost">
            @if ($data['cost_per_household'] !== null)
                <div class="stat-value cost">{{ \App\Services\FinanceReportService::usd($data['cost_per_household']) }}</div>
            @else
                <div class="stat-value cost" style="color:#9ca3af;">—</div>
            @endif
            <div class="stat-label cost">Cost / Household</div>
        </td>
        <td class="cost">
            @if ($data['cost_per_person'] !== null)
                <div class="stat-value cost">{{ \App\Services\FinanceReportService::usd($data['cost_per_person']) }}</div>
            @else
                <div class="stat-value cost" style="color:#9ca3af;">—</div>
            @endif
            <div class="stat-label cost">Cost / Person</div>
        </td>
    </tr>
</table>

{{-- Dual donut → horizontal-stacked-bar fallback for dompdf ── --}}
<table class="charts">
    <tr>
        <td>
            <h3>Income by Category</h3>
            <p class="sub">Total: {{ \App\Services\FinanceReportService::usd($data['income']['total']) }}</p>
            <div class="chart-wrap">
                @if (! empty($incomeSegments))
                    {!! \App\Support\SvgChart::horizontalStackedBar($incomeSegments, ['width' => 240, 'height' => 26]) !!}
                @else
                    <div style="height:26px; background:#f3f4f6; text-align:center; color:#9ca3af; font-size:8px; line-height:26px;">No income</div>
                @endif
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
            <h3>Expense by Category</h3>
            <p class="sub">Total: {{ \App\Services\FinanceReportService::usd($data['expense']['total']) }}</p>
            <div class="chart-wrap">
                @if (! empty($expenseSegments))
                    {!! \App\Support\SvgChart::horizontalStackedBar($expenseSegments, ['width' => 240, 'height' => 26]) !!}
                @else
                    <div style="height:26px; background:#f3f4f6; text-align:center; color:#9ca3af; font-size:8px; line-height:26px;">No expenses</div>
                @endif
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

{{-- Transactions ──────────────────────────────────────────── --}}
<table class="detail">
    <thead>
        <tr>
            <th style="width:60px;">Date</th>
            <th style="width:40px;">Type</th>
            <th>Title / Source</th>
            <th>Category</th>
            <th class="right" style="width:80px;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data['rows'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td><span class="type-pill {{ $row['type'] === 'income' ? 'in' : 'out' }}">{{ $row['type'] === 'income' ? 'IN' : 'OUT' }}</span></td>
                <td>
                    <strong>{{ $row['title'] }}</strong>
                    @if ($row['source'])
                        <span style="color:#9ca3af;"> — {{ $row['source'] }}</span>
                    @endif
                </td>
                <td>{{ $row['category'] }}</td>
                <td class="num {{ $row['type'] === 'income' ? 'income' : 'expense' }}">
                    {{ $row['type'] === 'expense' ? '-' : '+' }}{{ \App\Services\FinanceReportService::usd($row['amount']) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:10px;">No completed finance transactions are linked to this event.</td></tr>
        @endforelse
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
    <span>{{ $branding['app_name'] }} · Per-Event P&amp;L · {{ $data['event']['name'] }}</span>
</div>

</body>
</html>
