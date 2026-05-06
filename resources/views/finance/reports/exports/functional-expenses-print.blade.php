<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of Functional Expenses — {{ $branding['app_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; font-size: 11px; line-height: 1.5; }
        .sheet { max-width: 1100px; margin: 0 auto; }

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 18px; }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 18px; }
        table.stats td { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #fafafa; width: 25%; vertical-align: top; }
        .stat-value { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .stat-value.expense { color: #b91c1c; }
        .stat-value.green { color: #047857; }
        .stat-value.amber { color: #b45309; }
        .stat-value.red   { color: #b91c1c; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 6px; font-weight: 600; }
        .stat-meta  { font-size: 10px; color: #6b7280; margin-top: 4px; }

        table.fx { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 18px; }
        .fx thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 7px 8px; font-weight: 600; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; }
        .fx thead th.right { text-align: right; }
        .fx tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .fx tbody td.num { font-variant-numeric: tabular-nums; text-align: right; }
        .fx .fn-header td { background: #f9fafb; padding: 8px; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
        .fx .fn-subtotal td { background: #f3f4f6; font-weight: 700; border-top: 1px solid #d1d5db; }
        .fx .grand-total td { background: #1b2b4b; color: #fff; font-weight: 800; font-size: 13px; padding: 10px 8px; border-top: 2px solid #1b2b4b; }

        .insights { border: 1px solid #e5e7eb; border-left: 4px solid #f97316; border-radius: 8px; padding: 12px 16px; background: #fffbeb; margin-top: 16px; }
        .insights h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 18px; }
        .insights li { margin: 4px 0; color: #4b5563; font-size: 11px; line-height: 1.55; }

        .footer { margin-top: 22px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
        .footer .right { float: right; }

        @media print { body { padding: 12px; } tr { page-break-inside: avoid; } thead { display: table-header-group; } .insights, .footer { page-break-inside: avoid; } }
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
                    @if (! empty($branding['org_email'])) <p>{{ $branding['org_email'] }}</p> @endif
                </div>
            </td>
            <td class="right">
                <div class="doc-title">
                    <h2>Statement of Functional Expenses</h2>
                    <p class="meta">{{ $period['label'] }}</p>
                    @if ($data['compare'])
                        <p class="meta">Comparing to {{ $data['compare']['label'] }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    @php
        $r = $data['program_ratio'];
        if ($r >= 0.75)     $ratioCls = 'green';
        elseif ($r >= 0.65) $ratioCls = 'amber';
        else                $ratioCls = 'red';
    @endphp

    <table class="stats">
        <tr>
            <td>
                <div class="stat-value expense">{{ \App\Services\FinanceReportService::usd($data['total']) }}</div>
                <div class="stat-label">Total Expenses</div>
                @if ($data['compare'] && $data['prior_total'] !== null)
                    <div class="stat-meta">Prior: {{ \App\Services\FinanceReportService::usd($data['prior_total']) }}</div>
                @endif
            </td>
            <td>
                <div class="stat-value {{ $ratioCls }}">{{ number_format($r * 100, 1) }}%</div>
                <div class="stat-label">Program Ratio</div>
                @if ($data['prior_program_ratio'] !== null)
                    <div class="stat-meta">Prior: {{ number_format($data['prior_program_ratio'] * 100, 1) }}%</div>
                @endif
            </td>
            <td>
                <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['by_function']['management_general']['total']) }}</div>
                <div class="stat-label">Mgmt &amp; General</div>
                <div class="stat-meta">{{ number_format($data['by_function']['management_general']['share'] * 100, 1) }}% of expenses</div>
            </td>
            <td>
                <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['by_function']['fundraising']['total']) }}</div>
                <div class="stat-label">Fundraising</div>
                <div class="stat-meta">{{ number_format($data['by_function']['fundraising']['share'] * 100, 1) }}% of expenses</div>
            </td>
        </tr>
    </table>

    <table class="fx">
        <thead>
            <tr>
                <th>Category</th>
                <th class="right">Amount</th>
                <th class="right">% of Function</th>
                <th class="right">% of Total</th>
                @if ($data['compare'])
                    <th class="right">Δ vs Prior</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($data['by_function'] as $f)
                <tr class="fn-header">
                    <td colspan="{{ $data['compare'] ? 5 : 4 }}" style="color: {{ $f['color'] }};">{{ $f['label'] }}</td>
                </tr>
                @if (empty($f['categories']))
                    <tr><td colspan="{{ $data['compare'] ? 5 : 4 }}" style="font-style:italic; color:#9ca3af;">No categories under this function.</td></tr>
                @else
                    @foreach ($f['categories'] as $c)
                        <tr>
                            <td style="padding-left:20px;">{{ $c['name'] }}</td>
                            <td class="num">{{ \App\Services\FinanceReportService::usd($c['amount']) }}</td>
                            <td class="num">{{ number_format($c['share'] * 100, 1) }}%</td>
                            <td class="num">{{ $data['total'] > 0 ? number_format(($c['amount'] / $data['total']) * 100, 1) : '0.0' }}%</td>
                            @if ($data['compare'])
                                <td class="num">—</td>
                            @endif
                        </tr>
                    @endforeach
                @endif
                <tr class="fn-subtotal">
                    <td>{{ $f['label'] }} Subtotal</td>
                    <td class="num">{{ \App\Services\FinanceReportService::usd($f['total']) }}</td>
                    <td class="num">100.0%</td>
                    <td class="num">{{ number_format($f['share'] * 100, 1) }}%</td>
                    @if ($data['compare'])
                        <td class="num">
                            @if (isset($f['delta']) && $f['delta'] !== null)
                                {{ $f['delta'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($f['delta']) * 100, 1) }}%
                            @else
                                —
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach

            <tr class="grand-total">
                <td>Grand Total</td>
                <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
                <td></td>
                <td class="num">100.0%</td>
                @if ($data['compare'])
                    <td></td>
                @endif
            </tr>
        </tbody>
    </table>

    @if (! empty($data['insights']))
        <div class="insights">
            <h3>Insights</h3>
            <ul>
                @foreach ($data['insights'] as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="footer">
        Generated {{ now()->format('M j, Y g:i A') }}
        <span class="right">{{ $branding['app_name'] }}</span>
    </div>

</div>

@if (! empty($autoPrint))
<script>setTimeout(function () { window.print(); }, 250);</script>
@endif
</body>
</html>
