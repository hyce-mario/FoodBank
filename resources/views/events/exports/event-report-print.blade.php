<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Report — {{ $event->name }}</title>
    @php
        $orgEmail   = \App\Services\SettingService::get('organization.email',   '');
        $orgPhone   = \App\Services\SettingService::get('organization.phone',   '');
        $orgWebsite = \App\Services\SettingService::get('organization.website', '');
    @endphp
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
            border-bottom: 2px solid #111;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .org h1 { margin: 0; font-size: 20px; letter-spacing: -0.01em; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .event-name {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-top: 4px;
        }
        .doc-title .event-meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            background: #fafafa;
        }
        .stat-card .stat-value {
            font-size: 20px;
            font-weight: 800;
            color: #111;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            margin-top: 6px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        thead th {
            background: #f3f4f6;
            text-align: left;
            padding: 8px 10px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #374151;
            border-bottom: 1px solid #d1d5db;
        }
        tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 11px;
            color: #1f2937;
            vertical-align: top;
        }
        tbody tr.represented td { background: #fafafa; }
        tbody tr.represented td.name { padding-left: 24px; color: #4b5563; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.id  { font-family: 'SF Mono', Menlo, Consolas, monospace; color: #4b5563; }

        .status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            background: #f3f4f6;
            color: #374151;
        }
        .status.served       { background: #d1fae5; color: #065f46; }
        .status.checked_in   { background: #dbeafe; color: #1e40af; }
        .status.in_queue     { background: #fef3c7; color: #92400e; }
        .status.no_show      { background: #fee2e2; color: #991b1b; }

        .empty {
            text-align: center;
            padding: 32px 16px;
            color: #9ca3af;
            font-size: 12px;
            border: 1px dashed #e5e7eb;
            border-radius: 8px;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            margin-top: 20px;
            font-size: 10px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
        }

        .toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .toolbar button {
            font-family: inherit;
            font-size: 11px;
            padding: 6px 14px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            color: #374151;
        }
        .toolbar button.primary {
            background: #111;
            color: white;
            border-color: #111;
        }

        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            tbody tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>

<div class="sheet">

    <div class="toolbar">
        <button onclick="window.print()" class="primary">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="header">
        <div class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>
                @if ($orgEmail) {{ $orgEmail }} @endif
                @if ($orgPhone) · {{ $orgPhone }} @endif
                @if ($orgWebsite) · {{ $orgWebsite }} @endif
            </p>
        </div>
        <div class="doc-title">
            <h2>EVENT REPORT</h2>
            <div class="event-name">{{ $event->name }}</div>
            <div class="event-meta">
                {{ $event->date?->format('D, M j, Y') }}
                @if ($event->location) · {{ $event->location }} @endif
            </div>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-value">{{ number_format($totalVisits) }}</div>
            <div class="stat-label">Visits</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($totalHouseholds) }}</div>
            <div class="stat-label">Households Served</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($totalBags) }}</div>
            <div class="stat-label">Total Bags</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $ruleset?->name ?? '—' }}</div>
            <div class="stat-label">Allocation Ruleset</div>
        </div>
    </div>

    @if ($visits->isEmpty())
        <div class="empty">No check-ins recorded for this event yet.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Household #</th>
                    <th>Household</th>
                    <th class="num">Size</th>
                    <th class="num">Bags</th>
                    <th>Check-in Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($visits as $visit)
                    @php
                        $allHouseholds = $visit->households;
                        $primary       = $allHouseholds->first();
                        $represented   = $allHouseholds->slice(1);
                        $timeFmt       = $visit->start_time
                            ? $visit->start_time->format('M j, g:i A')
                            : '—';
                        $statusKey     = $visit->visit_status;
                        $statusLabel   = $visit->statusLabel();
                    @endphp
                    @if ($primary)
                        @php
                            $primarySize = (int) ($primary->pivot->household_size ?? $primary->household_size);
                            $primaryBags = $ruleset ? (int) $ruleset->getBagsFor($primarySize) : null;
                        @endphp
                        <tr class="primary">
                            <td class="id">#{{ $primary->household_number }}</td>
                            <td class="name"><strong>{{ $primary->full_name }}</strong></td>
                            <td class="num">{{ $primarySize }}</td>
                            <td class="num">{{ $primaryBags ?? '—' }}</td>
                            <td>{{ $timeFmt }}</td>
                            <td><span class="status {{ $statusKey }}">{{ $statusLabel }}</span></td>
                        </tr>
                        @foreach ($represented as $rep)
                            @php
                                $repSize = (int) ($rep->pivot->household_size ?? $rep->household_size);
                                $repBags = $ruleset ? (int) $ruleset->getBagsFor($repSize) : null;
                            @endphp
                            <tr class="represented">
                                <td class="id">#{{ $rep->household_number }}</td>
                                <td class="name">↳ {{ $rep->full_name }}</td>
                                <td class="num">{{ $repSize }}</td>
                                <td class="num">{{ $repBags ?? '—' }}</td>
                                <td>{{ $timeFmt }}</td>
                                <td><span class="status {{ $statusKey }}">{{ $statusLabel }}</span></td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Printed {{ now()->format('M j, Y g:i A') }}</span>
        <span>{{ $totalHouseholds }} household{{ $totalHouseholds === 1 ? '' : 's' }} · {{ $totalBags }} bag{{ $totalBags === 1 ? '' : 's' }}</span>
    </div>
</div>

<script>
    // Auto-fire the print dialog after a beat so the layout fully paints first.
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
</script>
</body>
</html>
