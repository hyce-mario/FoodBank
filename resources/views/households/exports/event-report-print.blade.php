<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Report — {{ $household->full_name }}</title>
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
        .sheet { max-width: 900px; margin: 0 auto; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1b2b4b;
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .org h1 { margin: 0; font-size: 18px; color: #1b2b4b; letter-spacing: -0.01em; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .org img { max-height: 50px; max-width: 200px; margin-bottom: 6px; display: block; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 20px; color: #111; letter-spacing: 0.02em; }
        .doc-title .household-name {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-top: 4px;
        }
        .doc-title .meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        .stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            background: #fafafa;
        }
        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: #1b2b4b;
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
            font-size: 11px;
        }
        thead th {
            background: #1b2b4b;
            color: #fff;
            text-align: left;
            padding: 8px 10px;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #fafafa; }

        td.num   { font-variant-numeric: tabular-nums; text-align: right; }
        td.muted { color: #9ca3af; text-align: center; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 700;
        }
        .badge-served       { background: #d1fae5; color: #065f46; }
        .badge-in-progress  { background: #fef3c7; color: #92400e; }
        .pickup {
            display: block;
            font-size: 10px;
            color: #b45309;
            margin-top: 2px;
        }

        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { padding: 12px; }
            .no-print { display: none !important; }
            thead { display: table-header-group; }
            tr    { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">

    <div class="header">
        <div class="org">
            @if (! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>Per-Household Event Report</p>
        </div>
        <div class="doc-title">
            <h2>EVENT REPORT</h2>
            <div class="household-name">{{ $household->full_name }}</div>
            <div class="meta">#{{ $household->household_number }} · {{ $household->household_size }} {{ $household->household_size == 1 ? 'member' : 'members' }}</div>
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-value">{{ $stats['total_visits'] }}</div>
            <div class="stat-label">Total Visits</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['total_bags_received'] }}</div>
            <div class="stat-label">Total Bags Received</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['last_served_at']?->format('M j, Y') ?? '—' }}</div>
            <div class="stat-label">Last Served</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Event</th>
                <th>Date</th>
                <th>Location</th>
                <th style="width: 80px;">Bags</th>
                <th style="width: 90px;">Status</th>
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
                    <td colspan="5" class="muted" style="padding: 28px;">
                        No event history yet for this household.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>{{ $branding['app_name'] }} · #{{ $household->household_number }} · Generated {{ now()->format('Y-m-d H:i') }}</span>
        <span>{{ $rows->count() }} {{ $rows->count() === 1 ? 'event' : 'events' }}</span>
    </div>

</div>

@if (! empty($autoPrint))
<script>
    window.addEventListener('load', () => {
        setTimeout(() => window.print(), 250);
    });
</script>
@endif
</body>
</html>
