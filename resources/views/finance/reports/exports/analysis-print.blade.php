<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }} — {{ $branding['app_name'] }}</title>
    <style>
        /* Shared print template for Donor + Vendor analysis. The two
           reports differ only in labelling — the layout, KPI strip,
           top-N table, lapsed callout, and insights panel are
           identical. Standalone HTML (no app chrome) so the printout
           is dompdf-compatible AND browser-print-compatible. */
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

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 18px; }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-bottom: 18px; }
        table.stats td {
            border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px;
            background: #fafafa; width: 25%; vertical-align: top;
        }
        .stat-value { font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; color: #1b2b4b; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 6px; font-weight: 600; }
        .stat-compare { font-size: 10px; color: #6b7280; margin-top: 4px; }

        table.donor-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 18px; }
        .donor-table thead th {
            background: #1b2b4b; color: #fff; text-align: left; padding: 7px 8px;
            font-weight: 600; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .donor-table thead th.right  { text-align: right; }
        .donor-table thead th.center { text-align: center; }
        .donor-table tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .donor-table tbody td.num    { font-variant-numeric: tabular-nums; text-align: right; }
        .donor-table tbody td.center { text-align: center; }
        .donor-table .rank-col { width: 28px; color: #9ca3af; }
        .donor-table .swatch {
            display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 4px;
            vertical-align: middle;
        }
        .donor-table .new-tag {
            display: inline-block; padding: 0 4px; border-radius: 2px;
            font-size: 7.5px; font-weight: bold;
            background: #fef3c7; color: #92400e; margin-left: 4px;
        }
        .donor-table .total-row td {
            border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: 800;
            font-size: 11px; padding-top: 9px; padding-bottom: 9px;
        }

        .lapsed-section {
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            padding: 12px 16px;
            background: #fffbeb;
            margin-bottom: 18px;
        }
        .lapsed-section h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; }
        .lapsed-section .row {
            display: table; width: 100%;
            font-size: 10px; padding: 2px 0;
            border-bottom: 1px dotted #fde68a;
        }
        .lapsed-section .row:last-child { border-bottom: none; }
        .lapsed-section .name { display: table-cell; color: #4b5563; }
        .lapsed-section .amt  { display: table-cell; text-align: right; color: #92400e; font-weight: bold; }

        .insights {
            border: 1px solid #e5e7eb; border-left: 4px solid #f97316;
            border-radius: 8px; padding: 12px 16px; background: #fffbeb;
        }
        .insights h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; }
        .insights ul { margin: 0; padding-left: 18px; }
        .insights li { margin: 4px 0; color: #4b5563; font-size: 11px; line-height: 1.55; }

        .footer {
            margin-top: 22px; padding-top: 12px; border-top: 1px solid #e5e7eb;
            font-size: 10px; color: #9ca3af;
        }
        .footer .right { float: right; }

        @media print {
            body { padding: 12px; }
            tr { page-break-inside: avoid; }
            .insights, .lapsed-section, table.donor-table { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">

    @php
        $hasCompare = ! empty($period['compare']);
    @endphp

    <table class="header">
        <tr>
            <td>
                @if (! empty($branding['logo_src']))
                    <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}" style="max-height:56px; max-width:220px; margin-bottom:6px; display:block;">
                @endif
                <div class="org">
                    <h1>{{ $branding['app_name'] }}</h1>
                    <p>{{ $reportTitle }}</p>
                </div>
            </td>
            <td class="right">
                <div class="doc-title">
                    <h2>{{ strtoupper($reportTitle) }}</h2>
                    <div class="meta">For the period: {{ $period['label'] }}</div>
                    @if ($hasCompare)
                        <div class="meta">Compared to: {{ $period['compare']['label'] }}</div>
                    @endif
                    <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- KPI strip — 4 cells ─────────────────────────────────────── --}}
    <table class="stats">
        <tr>
            <td>
                <div class="stat-value">{{ \App\Services\FinanceReportService::usd($data['total']) }}</div>
                <div class="stat-label">{{ $totalLabel }}</div>
                @if ($hasCompare && $data['prior_total'] !== null && $data['prior_total'] > 0)
                    @php $delta = ($data['total'] - $data['prior_total']) / $data['prior_total']; @endphp
                    <div class="stat-compare">{{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta) * 100, 0) }}% vs. prior</div>
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
                    <div class="stat-compare">no prior data</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Top donors / vendors table ──────────────────────────────── --}}
    <table class="donor-table">
        <thead>
            <tr>
                <th class="rank-col">#</th>
                <th>{{ ucfirst($entityLabel) }}</th>
                <th class="right">Total</th>
                <th class="center">Gifts</th>
                <th class="right">Avg</th>
                <th class="center">12-mo Trend</th>
                @if ($hasCompare)
                    <th class="right">vs. Prior</th>
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
                        <div style="color:#9ca3af; font-size:8.5px; margin-top:1px;">
                            last: {{ $donor['last_gift'] ?: '—' }}
                        </div>
                    </td>
                    <td class="num">
                        {{ \App\Services\FinanceReportService::usd($donor['total']) }}
                        <div style="color:#9ca3af; font-size:8.5px; font-weight:normal;">{{ number_format($donor['share'] * 100, 0) }}%</div>
                    </td>
                    <td class="center">{{ $donor['count'] }}</td>
                    <td class="num">{{ \App\Services\FinanceReportService::usd($donor['avg_gift']) }}</td>
                    <td class="center">
                        {!! \App\Support\SvgChart::sparkline($donor['sparkline'], ['color' => $donor['color']]) !!}
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
                <tr><td colspan="{{ $hasCompare ? 7 : 6 }}" style="text-align:center; color:#9ca3af; padding:18px;">No {{ $entityLabel }} activity in this period.</td></tr>
            @endforelse
            @if (! empty($data['donors']))
                <tr class="total-row">
                    <td></td>
                    <td>{{ $totalLabel }} (top {{ count($data['donors']) }}@if ($data['donor_total_count'] > 10) of {{ $data['donor_total_count'] }} @endif)</td>
                    <td class="num">{{ \App\Services\FinanceReportService::usd((float) array_sum(array_column($data['donors'], 'total'))) }}</td>
                    <td class="center">{{ (int) array_sum(array_column($data['donors'], 'count')) }}</td>
                    <td></td>
                    <td></td>
                    @if ($hasCompare)
                        <td></td>
                    @endif
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Lapsed (compare only) ────────────────────────────────────── --}}
    @if ($hasCompare && ! empty($data['lapsed']))
        <div class="lapsed-section">
            <h3>{{ count($data['lapsed']) }} Lapsed {{ count($data['lapsed']) === 1 ? ucfirst($entityLabel) : ucfirst($entityLabelPlural) }} — {{ $entityLabelPlural === 'donors' ? 'gave' : 'were paid' }} in {{ $period['compare']['label'] }} but not {{ $period['label'] }}</h3>
            @foreach ($data['lapsed'] as $l)
                <div class="row">
                    <span class="name">{{ $l['name'] }}</span>
                    <span class="amt">{{ \App\Services\FinanceReportService::usd($l['prior_total']) }} prior</span>
                </div>
            @endforeach
        </div>
    @endif

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
        <span class="right">{{ now()->format('Y-m-d H:i') }}</span>
        <span>{{ $branding['app_name'] }} · {{ $reportTitle }} · {{ $period['label'] }}</span>
    </div>

</div>

@if (! empty($autoPrint))
<script>
    window.addEventListener('load', () => { setTimeout(() => window.print(), 250); });
</script>
@endif
</body>
</html>
