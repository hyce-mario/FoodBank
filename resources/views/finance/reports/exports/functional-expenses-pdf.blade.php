<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of Functional Expenses — {{ $branding['app_name'] }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.5; margin: 0; padding: 0; }
        h1 { margin: 0; font-size: 18px; color: #1b2b4b; }
        h2 { margin: 0; font-size: 16px; color: #111; }
        .header { width: 100%; border-bottom: 2px solid #1b2b4b; padding-bottom: 10px; margin-bottom: 14px; }
        .header td { vertical-align: top; padding: 0; }
        .header td.right { text-align: right; }
        .meta { font-size: 10px; color: #6b7280; margin-top: 3px; }
        table.stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 14px; }
        table.stats td { border: 1px solid #e5e7eb; padding: 8px 10px; background: #fafafa; width: 25%; vertical-align: top; }
        .stat-value { font-size: 16px; font-weight: 700; }
        .stat-value.expense { color: #b91c1c; }
        .stat-value.green { color: #047857; }
        .stat-value.amber { color: #b45309; }
        .stat-value.red   { color: #b91c1c; }
        .stat-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 4px; font-weight: 600; }
        .stat-meta  { font-size: 8px; color: #6b7280; margin-top: 3px; }
        table.fx { width: 100%; border-collapse: collapse; font-size: 9px; }
        .fx thead th { background: #1b2b4b; color: #fff; text-align: left; padding: 6px 7px; font-weight: 600; font-size: 8px; text-transform: uppercase; }
        .fx thead th.right { text-align: right; }
        .fx tbody td { padding: 4px 7px; border-bottom: 1px solid #f3f4f6; }
        .fx tbody td.num { text-align: right; }
        .fx .fn-header td { background: #f9fafb; padding: 6px 7px; font-weight: 700; font-size: 9px; text-transform: uppercase; }
        .fx .fn-subtotal td { background: #f3f4f6; font-weight: 700; border-top: 1px solid #d1d5db; }
        .fx .grand-total td { background: #1b2b4b; color: #fff; font-weight: 800; font-size: 11px; padding: 7px; }
        .insights { border: 1px solid #e5e7eb; border-left: 3px solid #f97316; padding: 9px 12px; background: #fffbeb; margin-top: 12px; }
        .insights h3 { margin: 0 0 6px; font-size: 10px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 16px; }
        .insights li { margin: 3px 0; color: #4b5563; font-size: 9px; }
        .footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
        .footer .right { float: right; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td>
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}" style="max-height:42px; max-width:160px; margin-bottom:4px;">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
        </td>
        <td class="right">
            <h2>Statement of Functional Expenses</h2>
            <div class="meta">{{ $period['label'] }}</div>
            @if ($data['compare'])
                <div class="meta">Comparing to {{ $data['compare']['label'] }}</div>
            @endif
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
        </td>
        <td>
            <div class="stat-value {{ $ratioCls }}">{{ number_format($r * 100, 1) }}%</div>
            <div class="stat-label">Program Ratio</div>
        </td>
        <td>
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['by_function']['management_general']['total']) }}</div>
            <div class="stat-label">Mgmt &amp; General</div>
            <div class="stat-meta">{{ number_format($data['by_function']['management_general']['share'] * 100, 1) }}%</div>
        </td>
        <td>
            <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['by_function']['fundraising']['total']) }}</div>
            <div class="stat-label">Fundraising</div>
            <div class="stat-meta">{{ number_format($data['by_function']['fundraising']['share'] * 100, 1) }}%</div>
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
            @if ($data['compare'])<th class="right">Δ vs Prior</th>@endif
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
                        <td style="padding-left:18px;">{{ $c['name'] }}</td>
                        <td class="num">{{ \App\Services\FinanceReportService::usd($c['amount']) }}</td>
                        <td class="num">{{ number_format($c['share'] * 100, 1) }}%</td>
                        <td class="num">{{ $data['total'] > 0 ? number_format(($c['amount'] / $data['total']) * 100, 1) : '0.0' }}%</td>
                        @if ($data['compare'])<td class="num">—</td>@endif
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
            @if ($data['compare'])<td></td>@endif
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

</body>
</html>
