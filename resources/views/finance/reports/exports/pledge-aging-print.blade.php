<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pledge / AR Aging — {{ $branding['app_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937; margin: 0; padding: 24px;
            font-size: 11px; line-height: 1.5;
        }
        .sheet { max-width: 1100px; margin: 0 auto; }

        /* ── Header ───────────────────────────────────────────────────────── */
        table.header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1b2b4b; margin-bottom: 18px; }
        table.header td { vertical-align: top; padding: 0 0 16px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .org h1 { margin: 0; font-size: 20px; color: #1b2b4b; font-weight: 800; }
        .org p { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.04em; color: #111; font-weight: 800; }
        .doc-title .meta { font-size: 11px; color: #6b7280; margin-top: 4px; }

        /* ── KPI cards ────────────────────────────────────────────────────── */
        table.stats { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 18px; }
        table.stats td { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #fafafa; width: 33%; vertical-align: top; }
        .stat-value { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .stat-value.green { color: #047857; } .stat-value.amber { color: #b45309; } .stat-value.red { color: #b91c1c; } .stat-value.blue { color: #1d4ed8; } .stat-value.navy { color: #1b2b4b; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 6px; font-weight: 700; }
        .stat-meta { font-size: 10px; color: #6b7280; margin-top: 4px; }

        /* ── Two-column block (summary + top donors) ──────────────────────── */
        table.split { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 18px; }
        table.split > tbody > tr > td { vertical-align: top; padding: 0; width: 50%; }
        .panel { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #fff; }
        .panel h3 { margin: 0 0 8px; font-size: 11px; color: #1b2b4b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }

        table.summary { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .summary th, .summary td { padding: 5px 6px; text-align: left; }
        .summary th { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; font-weight: 700; }
        .summary td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .summary tr.total td { border-top: 1.5px solid #1b2b4b; font-weight: 800; padding-top: 7px; }
        .summary .swatch { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }

        ul.donors { list-style: none; padding: 0; margin: 0; }
        ul.donors li { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #f3f4f6; font-size: 10.5px; }
        ul.donors li:last-child { border-bottom: none; }
        ul.donors .name { color: #374151; }
        ul.donors .count { color: #9ca3af; font-size: 9.5px; margin-left: 4px; }
        ul.donors .amount { font-weight: 700; font-variant-numeric: tabular-nums; color: #111; }

        /* ── Detail table ─────────────────────────────────────────────────── */
        table.aging { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 6px; }
        .aging thead th {
            background: #1b2b4b; color: #fff; text-align: left; padding: 7px 8px;
            font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .aging thead th.right { text-align: right; }
        .aging tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .aging td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .aging .bk-header td {
            background: #f3f4f6; padding: 8px 10px; font-weight: 800; font-size: 10px;
            text-transform: uppercase; letter-spacing: 0.05em; border-top: 1px solid #d1d5db;
        }
        .aging .bk-header .swatch { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }
        .aging .bk-header .meta { font-weight: 500; text-transform: none; letter-spacing: 0; color: #6b7280; margin-left: 8px; font-size: 10px; }
        .aging .bk-empty td { font-style: italic; color: #9ca3af; padding: 8px 24px; }
        .aging .grand-total td {
            background: #1b2b4b; color: #fff; font-weight: 800; font-size: 12px; padding: 10px 8px;
            border-top: 2px solid #1b2b4b;
        }
        .pill { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 9px; font-weight: 700; }
        .pill.open    { background: #dbeafe; color: #1e40af; }
        .pill.partial { background: #fef3c7; color: #92400e; }
        .od-overdue { color: #b91c1c; font-weight: 700; }

        /* ── Insights ─────────────────────────────────────────────────────── */
        .insights {
            border: 1px solid #e5e7eb; border-left: 4px solid #f97316;
            border-radius: 8px; padding: 12px 16px; background: #fffbeb; margin-top: 16px;
        }
        .insights h3 { margin: 0 0 8px; font-size: 12px; color: #92400e; font-weight: 700; }
        .insights ul { margin: 0; padding-left: 18px; }
        .insights li { margin: 4px 0; color: #4b5563; font-size: 11px; line-height: 1.55; }

        /* ── Footer ───────────────────────────────────────────────────────── */
        .footer { margin-top: 22px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
        .footer .right { float: right; }

        @media print {
            body { padding: 12px; }
            tr, .panel, .insights, .footer { page-break-inside: avoid; }
            thead { display: table-header-group; }
            .aging .bk-header { page-break-after: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">

    {{-- ── Branded header ──────────────────────────────────────────── --}}
    <table class="header">
        <tr>
            <td class="org">
                @if (! empty($branding['logo_src']))
                    <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
                @endif
                <h1>{{ $branding['app_name'] }}</h1>
                <p>Pledge Receivables &amp; AR Aging Statement</p>
            </td>
            <td class="right">
                <div class="doc-title">
                    <h2>PLEDGE / AR AGING</h2>
                    <div class="meta">As of {{ $asOf->format('F j, Y') }}</div>
                    <div class="meta">{{ $data['count'] }} outstanding {{ $data['count'] === 1 ? 'pledge' : 'pledges' }}</div>
                    <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Headline KPIs ──────────────────────────────────────────── --}}
    @php
        $over90  = $data['buckets']['over_90'];
        $current = $data['buckets']['current'];
        $share90 = $data['total'] > 0 ? ($over90['total'] / $data['total']) * 100 : 0;
        if ($share90 >= 25)      $cls90 = 'red';
        elseif ($share90 > 0)    $cls90 = 'amber';
        else                     $cls90 = 'green';

        $overdueTotal = $data['total'] - $current['total'];
        $sharePastDue = $data['total'] > 0 ? ($overdueTotal / $data['total']) * 100 : 0;
    @endphp
    <table class="stats">
        <tr>
            <td>
                <div class="stat-value navy">{{ \App\Services\FinanceReportService::usd($data['total']) }}</div>
                <div class="stat-label">Total Outstanding</div>
                <div class="stat-meta">{{ $data['count'] }} {{ $data['count'] === 1 ? 'pledge' : 'pledges' }}</div>
            </td>
            <td>
                <div class="stat-value blue">{{ \App\Services\FinanceReportService::usd($current['total']) }}</div>
                <div class="stat-label">Current (not yet due)</div>
                <div class="stat-meta">{{ $current['count'] }} pledges &middot; {{ number_format(100 - $sharePastDue, 1) }}% of outstanding</div>
            </td>
            <td>
                <div class="stat-value {{ $cls90 }}">{{ \App\Services\FinanceReportService::usd($over90['total']) }}</div>
                <div class="stat-label">90+ Days Overdue</div>
                <div class="stat-meta">{{ $over90['count'] }} pledges &middot; {{ number_format($share90, 1) }}% of outstanding</div>
            </td>
        </tr>
    </table>

    {{-- ── Aging summary + Top donors ───────────────────────────── --}}
    <table class="split">
        <tr>
            <td>
                <div class="panel">
                    <h3>Aging Summary</h3>
                    <table class="summary">
                        <thead>
                            <tr>
                                <th>Bucket</th>
                                <th class="num">Pledges</th>
                                <th class="num">Total</th>
                                <th class="num">% of Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['buckets'] as $key => $bucket)
                                @php $share = $data['total'] > 0 ? ($bucket['total'] / $data['total']) * 100 : 0; @endphp
                                <tr>
                                    <td><span class="swatch" style="background: {{ $bucket['color'] }};"></span>{{ $bucket['label'] }}</td>
                                    <td class="num">{{ $bucket['count'] }}</td>
                                    <td class="num">{{ \App\Services\FinanceReportService::usd($bucket['total']) }}</td>
                                    <td class="num">{{ number_format($share, 1) }}%</td>
                                </tr>
                            @endforeach
                            <tr class="total">
                                <td>TOTAL</td>
                                <td class="num">{{ $data['count'] }}</td>
                                <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
                                <td class="num">100.0%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
            <td>
                <div class="panel">
                    <h3>Top 5 Outstanding Donors</h3>
                    @if (empty($data['top_donors']))
                        <p style="color:#9ca3af; font-size:10.5px; margin:6px 0 0;">No outstanding pledges.</p>
                    @else
                        <ul class="donors">
                            @foreach ($data['top_donors'] as $d)
                                @php $share = $data['total'] > 0 ? ($d['total'] / $data['total']) * 100 : 0; @endphp
                                <li>
                                    <span class="name">{{ $d['name'] }}<span class="count">({{ $d['count'] }} pledge{{ $d['count'] === 1 ? '' : 's' }} &middot; {{ number_format($share, 1) }}%)</span></span>
                                    <span class="amount">{{ \App\Services\FinanceReportService::usd($d['total']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Per-pledge detail by bucket ─────────────────────────── --}}
    <table class="aging">
        <thead>
            <tr>
                <th>Donor / Source</th>
                <th>Pledged</th>
                <th>Expected</th>
                <th class="right">Days Overdue</th>
                <th class="right">Amount</th>
                <th>Status</th>
                <th>Linked Event</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['buckets'] as $bucket)
                <tr class="bk-header">
                    <td colspan="7" style="color: {{ $bucket['color'] }};">
                        <span class="swatch" style="background: {{ $bucket['color'] }};"></span>{{ $bucket['label'] }}
                        <span class="meta">{{ $bucket['count'] }} pledges &middot; {{ \App\Services\FinanceReportService::usd($bucket['total']) }}</span>
                    </td>
                </tr>
                @if (empty($bucket['pledges']))
                    <tr class="bk-empty"><td colspan="7">No pledges in this bucket.</td></tr>
                @else
                    @foreach ($bucket['pledges'] as $p)
                        <tr>
                            <td style="padding-left:24px;">{{ $p['source_or_payee'] }}</td>
                            <td>{{ $p['pledged_at'] }}</td>
                            <td>{{ $p['expected_at'] }}</td>
                            <td class="num">
                                @if ($p['days_overdue'] > 0)
                                    <span class="od-overdue">{{ $p['days_overdue'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">{{ \App\Services\FinanceReportService::usd($p['amount']) }}</td>
                            <td><span class="pill {{ $p['status'] }}">{{ ucfirst($p['status']) }}</span></td>
                            <td style="color:#6b7280;">{{ $p['event_name'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
            <tr class="grand-total">
                <td>GRAND TOTAL</td>
                <td colspan="3"></td>
                <td class="num">{{ \App\Services\FinanceReportService::usd($data['total']) }}</td>
                <td colspan="2">{{ $data['count'] }} pledges</td>
            </tr>
        </tbody>
    </table>

    {{-- ── Insights ──────────────────────────────────────────────── --}}
    @if (! empty($data['insights']))
        <div class="insights">
            <h3>Insights</h3>
            <ul>
                @foreach ($data['insights'] as $bullet) <li>{{ $bullet }}</li> @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Footer ────────────────────────────────────────────────── --}}
    <div class="footer">
        Generated {{ now()->format('M j, Y g:i A') }}
        <span class="right">{{ $branding['app_name'] }} &middot; Pledge / AR Aging</span>
    </div>

</div>

@if (! empty($autoPrint))
<script>setTimeout(function () { window.print(); }, 250);</script>
@endif
</body>
</html>
