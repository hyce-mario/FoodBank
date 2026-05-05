<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>General Ledger — {{ $branding['app_name'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 8.5px; line-height: 1.4; margin: 0; padding: 18px; }

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 12px; }
        table.header td { vertical-align: top; padding: 0 0 8px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 42px; max-width: 170px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 13px; color: #1b2b4b; font-weight: bold; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 8.5px; }
        .doc-title h2 { margin: 0; font-size: 13px; color: #111; letter-spacing: 0.04em; font-weight: bold; }
        .doc-title .meta { font-size: 8.5px; color: #6b7280; margin-top: 3px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 10px; }
        table.stats td { border: 1px solid #e5e7eb; background: #fafafa; padding: 6px 8px; width: 33%; vertical-align: top; }
        .stat-value { font-size: 13px; font-weight: bold; }
        .stat-value.income { color: #047857; }
        .stat-value.expense { color: #b91c1c; }
        .stat-value.net-positive { color: #047857; }
        .stat-value.net-negative { color: #b91c1c; }
        .stat-label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 2px; font-weight: bold; }
        .stat-compare { font-size: 7px; color: #6b7280; margin-top: 1px; }

        table.ledger { width: 100%; border-collapse: collapse; font-size: 7.5px; margin-bottom: 10px; }
        .ledger thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 5px 6px; font-weight: bold; font-size: 7px; text-transform: uppercase; letter-spacing: 0.04em; }
        .ledger thead th.right { text-align: right; }
        .ledger tbody td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .ledger tbody td.num { text-align: right; }
        .ledger .income-row td.amt { color: #047857; font-weight: bold; }
        .ledger .expense-row td.amt { color: #b91c1c; font-weight: bold; }
        .ledger .total-row td { border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: bold; font-size: 9px; padding-top: 7px; padding-bottom: 7px; }
        .pill { display: inline-block; padding: 1px 4px; font-size: 6.5px; font-weight: bold; }
        .pill.in  { background: #d1fae5; color: #065f46; }
        .pill.out { background: #fee2e2; color: #991b1b; }

        .insights { border: 1px solid #e5e7eb; border-left: 3px solid #f97316; padding: 9px 11px; background: #fffbeb; margin-bottom: 10px; }
        .insights h3 { margin: 0 0 5px; font-size: 9px; color: #92400e; font-weight: bold; }
        .insights ul { margin: 0; padding-left: 12px; }
        .insights li { margin: 1px 0; color: #4b5563; font-size: 8px; }

        .footer { margin-top: 10px; padding-top: 6px; border-top: 1px solid #e5e7eb; font-size: 7px; color: #9ca3af; }
        .footer .right { float: right; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>General Ledger</p>
        </td>
        <td class="right doc-title">
            <h2>GENERAL LEDGER</h2>
            <div class="meta">For the period: {{ $period['label'] }}</div>
            <div class="meta">{{ $data['count'] }} txns ({{ $data['counted'] }} completed)</div>
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </td>
    </tr>
</table>

<table class="stats">
    <tr>
        <td>
            <div class="stat-value income">{{ \App\Services\FinanceReportService::usd($data['total_in']) }}</div>
            <div class="stat-label">Total Inflow</div>
        </td>
        <td>
            <div class="stat-value expense">{{ \App\Services\FinanceReportService::usd($data['total_out']) }}</div>
            <div class="stat-label">Total Outflow</div>
        </td>
        <td>
            <div class="stat-value {{ $data['net_change'] >= 0 ? 'net-positive' : 'net-negative' }}">
                {{ $data['net_change'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['net_change']) }}
            </div>
            <div class="stat-label">Net Change</div>
            <div class="stat-compare">closing: {{ \App\Services\FinanceReportService::usd($data['closing_balance']) }}</div>
        </td>
    </tr>
</table>

<table class="ledger">
    <thead>
        <tr>
            <th style="width:55px;">Date</th>
            <th style="width:30px;">Type</th>
            <th>Title / Source</th>
            <th>Category</th>
            <th>Ref</th>
            <th>Status</th>
            <th class="right" style="width:65px;">Amount</th>
            <th class="right" style="width:75px;">Balance</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data['rows'] as $row)
            <tr class="{{ $row['type'] }}-row">
                <td class="num">{{ $row['date'] }}</td>
                <td>
                    <span class="pill {{ $row['type'] === 'income' ? 'in' : 'out' }}">{{ $row['type'] === 'income' ? 'IN' : 'OUT' }}</span>
                </td>
                <td>
                    <strong>{{ $row['title'] }}</strong>
                    @if ($row['source'])
                        <span style="color:#9ca3af;"> — {{ $row['source'] }}</span>
                    @endif
                </td>
                <td>{{ $row['category'] }}</td>
                <td style="color:#6b7280;">{{ $row['reference'] ?: '—' }}</td>
                <td>{{ ucfirst((string) $row['status']) }}</td>
                <td class="num amt">
                    {{ $row['type'] === 'expense' ? '-' : '+' }}{{ \App\Services\FinanceReportService::usd($row['amount']) }}
                </td>
                <td class="num">
                    @if ($row['running_balance'] !== null)
                        {{ \App\Services\FinanceReportService::usd($row['running_balance']) }}
                    @else
                        <span style="color:#d1d5db;">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:14px;">No transactions match the applied filters.</td></tr>
        @endforelse

        @if (! empty($data['rows']))
            <tr class="total-row">
                <td colspan="6">Closing Balance</td>
                <td></td>
                <td class="num">{{ \App\Services\FinanceReportService::usd($data['closing_balance']) }}</td>
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
    <span>{{ $branding['app_name'] }} · General Ledger · {{ $period['label'] }}</span>
</div>

</body>
</html>
