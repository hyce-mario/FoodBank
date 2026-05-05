<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }} — {{ $branding['app_name'] }}</title>
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

        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 18px; }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }

        table.stats { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 18px; }
        table.stats td {
            border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px;
            background: #fafafa; width: 33%; vertical-align: top;
        }
        .stat-value { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; color: #1b2b4b; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 6px; font-weight: 600; }
        .stat-compare { font-size: 10px; color: #6b7280; margin-top: 4px; }

        .composition {
            border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px;
            background: #fff; margin-bottom: 18px;
        }
        .composition h3 { margin: 0 0 10px; font-size: 12px; color: #1b2b4b; }
        .composition .legend { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 10px; }
        .composition .legend td { padding: 2px 4px; }
        .swatch { display: inline-block; width: 9px; height: 9px; border-radius: 2px; }

        table.detail { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 18px; }
        .detail thead th {
            background: #1b2b4b; color: #fff; text-align: left; padding: 7px 8px;
            font-weight: 600; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .detail thead th.right { text-align: right; }
        .detail tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .detail tbody td.num { font-variant-numeric: tabular-nums; text-align: right; }
        .detail .group-row td {
            background: #f9fafb; font-weight: 700; font-size: 9px;
            color: #1b2b4b; padding-top: 8px; padding-bottom: 6px;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .detail .total-row td {
            border-top: 2px solid #1b2b4b; background: #f3f4f6; font-weight: 800;
            font-size: 12px; padding-top: 10px; padding-bottom: 10px;
        }

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
            .insights, .composition, table.detail { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">

    @php
        $hasCompare = ! empty($period['compare']);
        $stackedSegments = collect($data['by_category'])->map(fn ($c) => [
            'label' => $c['name'], 'value' => $c['amount'], 'color' => $c['color'],
        ])->values()->all();
    @endphp

    <table class="header">
        <tr>
            <td>
                @if (! empty($branding['logo_src']))
                    <img class="org-logo" src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}" style="max-height:56px; max-width:220px; margin-bottom:6px; display:block;">
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

    {{-- KPI strip ─────────────────────────────────────────────────── --}}
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
                <div class="stat-value">{{ $data['count'] }}</div>
                <div class="stat-label">Transactions</div>
                <div class="stat-compare">across {{ count($data['by_category']) }} {{ count($data['by_category']) === 1 ? 'category' : 'categories' }}</div>
            </td>
            <td>
                @if ($data['top_source'])
                    <div class="stat-value" style="font-size:14px;">{{ $data['top_source']['name'] }}</div>
                    <div class="stat-label">Top {{ strtolower($sourceLabel) }}</div>
                    <div class="stat-compare">{{ \App\Services\FinanceReportService::usd($data['top_source']['amount']) }}</div>
                @else
                    <div class="stat-value" style="color:#9ca3af;">—</div>
                    <div class="stat-label">Top {{ strtolower($sourceLabel) }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Composition stacked bar + legend ─────────────────────────── --}}
    <div class="composition">
        <h3>Composition — {{ $period['label'] }}</h3>
        @if (! empty($stackedSegments))
            {!! \App\Support\SvgChart::horizontalStackedBar($stackedSegments, ['width' => 1040, 'height' => 30]) !!}
            <table class="legend">
                @foreach ($data['by_category'] as $cat)
                    <tr>
                        <td style="width:14px;"><span class="swatch" style="background: {{ $cat['color'] }};"></span></td>
                        <td>{{ $cat['name'] }}</td>
                        <td style="text-align:right; color:#9ca3af;">{{ number_format($cat['share'] * 100, 0) }}%</td>
                        <td style="text-align:right; font-weight:bold;">{{ \App\Services\FinanceReportService::usd($cat['amount']) }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <div style="text-align:center; color:#9ca3af; padding:14px 0;">No data for this period.</div>
        @endif
    </div>

    {{-- Detail table ──────────────────────────────────────────────── --}}
    <table class="detail">
        <thead>
            <tr>
                <th style="width:80px;">Date</th>
                <th>Title / {{ $sourceLabel }}</th>
                <th>Event</th>
                <th class="right" style="width:100px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['by_category'] as $cat)
                <tr class="group-row">
                    <td colspan="4">
                        <span class="swatch" style="background: {{ $cat['color'] }};"></span>
                        {{ $cat['name'] }}
                        <span style="float:right; font-weight:normal; text-transform:none; letter-spacing:normal; color:#6b7280;">
                            {{ $cat['count'] }} {{ $cat['count'] === 1 ? 'txn' : 'txns' }} · {{ \App\Services\FinanceReportService::usd($cat['amount']) }}
                        </span>
                    </td>
                </tr>
                @foreach (collect($data['rows'])->where('category', $cat['name']) as $row)
                    <tr>
                        <td class="num">{{ $row['date'] }}</td>
                        <td>
                            <strong>{{ $row['title'] }}</strong>
                            @if ($row['source'])
                                <span style="color:#9ca3af;"> — {{ $row['source'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['event'] ?: '—' }}</td>
                        <td class="num">{{ \App\Services\FinanceReportService::usd($row['amount']) }}</td>
                    </tr>
                @endforeach
            @endforeach

            @if (empty($data['rows']))
                <tr><td colspan="4" style="text-align:center; color:#9ca3af; padding:18px;">No transactions match the applied filters.</td></tr>
            @endif

            <tr class="total-row">
                <td colspan="3">{{ $totalLabel }}</td>
                <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
            </tr>
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
