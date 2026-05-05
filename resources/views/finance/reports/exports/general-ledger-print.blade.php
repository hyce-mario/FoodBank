<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>General Ledger — {{ $branding['app_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937; margin: 0; padding: 24px;
            font-size: 11px; line-height: 1.5;
        }
        .sheet { max-width: 1200px; margin: 0 auto; }

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 18px; }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 18px; }
        table.stats td { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #fafafa; width: 33%; vertical-align: top; }
        .stat-value { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .stat-value.income { color: #047857; }
        .stat-value.expense { color: #b91c1c; }
        .stat-value.net-positive { color: #047857; }
        .stat-value.net-negative { color: #b91c1c; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 6px; font-weight: 600; }
        .stat-compare { font-size: 10px; color: #6b7280; margin-top: 4px; }

        table.ledger { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 18px; }
        .ledger thead th {
            background: #1b2b4b; color: #fff; text-align: left; padding: 7px 8px;
            font-weight: 600; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .ledger thead th.right { text-align: right; }
        .ledger tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .ledger tbody td.num { font-variant-numeric: tabular-nums; text-align: right; }
        .ledger .income-row td.amt { color: #047857; font-weight: 600; }
        .ledger .expense-row td.amt { color: #b91c1c; font-weight: 600; }
        .ledger .pending-row { opacity: 0.65; }
        .ledger .cancelled-row { opacity: 0.45; text-decoration: line-through; }
        .ledger .total-row td {
            border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: 800;
            font-size: 12px; padding-top: 10px; padding-bottom: 10px;
        }
        .pill { display: inline-block; padding: 1px 5px; border-radius: 999px; font-size: 8px; font-weight: 700; }
        .pill.in  { background: #d1fae5; color: #065f46; }
        .pill.out { background: #fee2e2; color: #991b1b; }

        .insights { border: 1px solid #e5e7eb; border-left: 4px solid #f97316; border-radius: 8px; padding: 12px 16px; background: #fffbeb; }
        .insights h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 18px; }
        .insights li { margin: 4px 0; color: #4b5563; font-size: 11px; line-height: 1.55; }

        .footer { margin-top: 22px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
        .footer .right { float: right; }

        @media print {
            body { padding: 12px; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
            .insights, .footer { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">

    <table class="header">
        <tr>
            <td>
                @if (! empty($branding['logo_src']))
                    <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}" style="max-height:56px; max-width:220px; margin-bottom:6px; display:block;">
                @endif
                <div class="org">
                    <h1>{{ $branding['app_name'] }}</h1>
                    <p>General Ledger</p>
                </div>
            </td>
            <td class="right">
                <div class="doc-title">
                    <h2>GENERAL LEDGER</h2>
                    <div class="meta">For the period: {{ $period['label'] }}</div>
                    <div class="meta">{{ $data['count'] }} {{ $data['count'] === 1 ? 'transaction' : 'transactions' }} ({{ $data['counted'] }} completed)</div>
                    <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="stats">
        <tr>
            <td>
                <div class="stat-value income">{{ \App\Services\FinanceReportService::usd($data['total_in']) }}</div>
                <div class="stat-label">Total Inflow</div>
                <div class="stat-compare">income, completed only</div>
            </td>
            <td>
                <div class="stat-value expense">{{ \App\Services\FinanceReportService::usd($data['total_out']) }}</div>
                <div class="stat-label">Total Outflow</div>
                <div class="stat-compare">expense, completed only</div>
            </td>
            <td>
                <div class="stat-value {{ $data['net_change'] >= 0 ? 'net-positive' : 'net-negative' }}">
                    {{ $data['net_change'] >= 0 ? '+' : '' }}{{ \App\Services\FinanceReportService::usd($data['net_change']) }}
                </div>
                <div class="stat-label">Net Change</div>
                <div class="stat-compare">closing balance: {{ \App\Services\FinanceReportService::usd($data['closing_balance']) }}</div>
            </td>
        </tr>
    </table>

    <table class="ledger">
        <thead>
            <tr>
                <th style="width:80px;">Date</th>
                <th style="width:35px;">Type</th>
                <th>Title / Source</th>
                <th>Category</th>
                <th>Reference</th>
                <th>Status</th>
                <th class="right" style="width:90px;">Amount</th>
                <th class="right" style="width:100px;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rows'] as $row)
                <tr class="{{ $row['type'] }}-row {{ $row['status'] === 'pending' ? 'pending-row' : '' }} {{ $row['status'] === 'cancelled' ? 'cancelled-row' : '' }}">
                    <td class="num">{{ $row['date'] }}</td>
                    <td>
                        <span class="pill {{ $row['type'] === 'income' ? 'in' : 'out' }}">{{ $row['type'] === 'income' ? 'IN' : 'OUT' }}</span>
                    </td>
                    <td>
                        <strong>{{ $row['title'] }}</strong>
                        @if ($row['source'])
                            <span style="color:#9ca3af;"> — {{ $row['source'] }}</span>
                        @endif
                        @if ($row['event'])
                            <br><span style="font-size:9px; color:#9ca3af;">Event: {{ $row['event'] }}</span>
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
                <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:18px;">No transactions match the applied filters.</td></tr>
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

</div>

@if (! empty($autoPrint))
<script>
    window.addEventListener('load', () => { setTimeout(() => window.print(), 250); });
</script>
@endif
</body>
</html>
