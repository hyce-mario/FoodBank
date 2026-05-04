<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Report — {{ $household->full_name }}</title>
    <style>
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
        .doc-title .household-name { font-size: 12px; font-weight: bold; color: #374151; margin-top: 3px; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 2px; }

        table.stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 14px;
        }
        table.stats td {
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            background: #fafafa;
            padding: 8px 10px;
            width: 33%;
        }
        .stat-value { font-size: 16px; font-weight: bold; color: #1b2b4b; }
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
        table.data tbody tr:nth-child(even) td { background: #fafafa; }
        td.num   { text-align: right; }
        td.muted { color: #9ca3af; text-align: center; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 7.5px;
            font-weight: bold;
        }
        .badge-served      { background: #d1fae5; color: #065f46; }
        .badge-in-progress { background: #fef3c7; color: #92400e; }
        .pickup { display: block; font-size: 8.5px; color: #b45309; margin-top: 2px; }

        .footer {
            position: fixed;
            bottom: -15px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
        .footer .left  { float: left; }
        .footer .right { float: right; }
        .page-num:after   { content: counter(page); }
        .page-total:after { content: counter(pages); }
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
            <p>Per-Household Event Report</p>
        </td>
        <td class="right doc-title">
            <h2>EVENT REPORT</h2>
            <div class="household-name">{{ $household->full_name }}</div>
            <div class="meta">#{{ $household->household_number }} · {{ $household->household_size }} {{ $household->household_size == 1 ? 'member' : 'members' }}</div>
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </td>
    </tr>
</table>

<table class="stats">
    <tr>
        <td>
            <div class="stat-value">{{ $stats['total_visits'] }}</div>
            <div class="stat-label">Total Visits</div>
        </td>
        <td>
            <div class="stat-value">{{ $stats['total_bags_received'] }}</div>
            <div class="stat-label">Total Bags Received</div>
        </td>
        <td>
            <div class="stat-value">{{ $stats['last_served_at']?->format('M j, Y') ?? '—' }}</div>
            <div class="stat-label">Last Served</div>
        </td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Event</th>
            <th style="width: 17%;">Date</th>
            <th style="width: 22%;">Location</th>
            <th style="width: 9%;">Bags</th>
            <th style="width: 14%;">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $r)
            <tr>
                <td>
                    <strong>{{ $r->event_name }}</strong>
                    @if ($r->picked_up_by)
                        <span class="pickup">★ Picked up by {{ $r->picked_up_by }}</span>
                    @endif
                </td>
                <td>{{ $r->event_date?->format('M j, Y') ?? '—' }}</td>
                <td>{{ $r->event_location }}</td>
                <td class="num">{{ $r->bags }}</td>
                <td>
                    <span class="badge {{ $r->status_label === 'Served' ? 'badge-served' : 'badge-in-progress' }}">
                        {{ $r->status_label }}
                    </span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="muted" style="padding: 24px;">
                    No event history yet for this household.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <span class="left">{{ $branding['app_name'] }} · #{{ $household->household_number }} · {{ now()->format('Y-m-d H:i') }}</span>
    <span class="right">Page <span class="page-num"></span> of <span class="page-total"></span></span>
</div>

</body>
</html>
