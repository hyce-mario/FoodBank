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
        /* DomPDF-friendly: no flex, no grid, table-based layout, DejaVu font
           (the only font dompdf reliably ships with that supports unicode). */
        @page { margin: 30px 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.4;
        }

        table.header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #1b2b4b;
            margin-bottom: 14px;
        }
        table.header td { vertical-align: top; padding: 0 0 12px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 50px; max-width: 200px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 15px; color: #1b2b4b; font-weight: bold; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 9px; }
        .doc-title h2 { margin: 0; font-size: 17px; color: #111; letter-spacing: 0.05em; }
        .doc-title .event-name { font-size: 12px; font-weight: bold; color: #374151; margin-top: 3px; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 2px; }

        table.stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 14px;
        }
        table.stats td {
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            background: #fafafa;
            padding: 8px 10px;
            width: 25%;
        }
        .stat-value { font-size: 14px; font-weight: bold; color: #1b2b4b; }
        .stat-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-top: 2px;
            font-weight: bold;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        table.data thead th {
            background: #1b2b4b;
            color: #fff;
            text-align: left;
            padding: 6px 7px;
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table.data tbody td {
            padding: 5px 7px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        table.data tbody tr.represented td { background: #fafafa; }
        table.data tbody tr.represented td.name { padding-left: 18px; color: #4b5563; }
        td.num   { text-align: right; }
        td.id    { color: #4b5563; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 8.5px;
            font-weight: bold;
            background: #f3f4f6;
            color: #374151;
        }
        .badge.served      { background: #d1fae5; color: #065f46; }
        .badge.checked_in  { background: #dbeafe; color: #1e40af; }
        .badge.in_queue    { background: #fef3c7; color: #92400e; }
        .badge.no_show     { background: #fee2e2; color: #991b1b; }

        .empty {
            text-align: center;
            padding: 24px 16px;
            color: #9ca3af;
            font-size: 10px;
            border: 1px dashed #e5e7eb;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            margin-top: 14px;
            font-size: 8px;
            color: #6b7280;
        }
        .footer table { width: 100%; }
        .footer td.right { text-align: right; }
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
            <p>
                @if ($orgEmail) {{ $orgEmail }} @endif
                @if ($orgPhone) · {{ $orgPhone }} @endif
                @if ($orgWebsite) · {{ $orgWebsite }} @endif
            </p>
        </td>
        <td class="right doc-title">
            <h2>EVENT REPORT</h2>
            <div class="event-name">{{ $event->name }}</div>
            <div class="meta">
                {{ $event->date?->format('D, M j, Y') }}
                @if ($event->location) · {{ $event->location }} @endif
            </div>
        </td>
    </tr>
</table>

<table class="stats">
    <tr>
        <td>
            <div class="stat-value">{{ number_format($totalVisits) }}</div>
            <div class="stat-label">Visits</div>
        </td>
        <td>
            <div class="stat-value">{{ number_format($totalHouseholds) }}</div>
            <div class="stat-label">Households Served</div>
        </td>
        <td>
            <div class="stat-value">{{ number_format($totalBags) }}</div>
            <div class="stat-label">Total Bags</div>
        </td>
        <td>
            <div class="stat-value" style="font-size: 10px;">{{ $ruleset?->name ?? '—' }}</div>
            <div class="stat-label">Allocation Ruleset</div>
        </td>
    </tr>
</table>

@if ($visits->isEmpty())
    <div class="empty">No check-ins recorded for this event yet.</div>
@else
    <table class="data">
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
                        <td><span class="badge {{ $statusKey }}">{{ $statusLabel }}</span></td>
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
                            <td><span class="badge {{ $statusKey }}">{{ $statusLabel }}</span></td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    <table>
        <tr>
            <td>Generated {{ now()->format('M j, Y g:i A') }}</td>
            <td class="right">
                {{ $totalHouseholds }} household{{ $totalHouseholds === 1 ? '' : 's' }} ·
                {{ $totalBags }} bag{{ $totalBags === 1 ? '' : 's' }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
