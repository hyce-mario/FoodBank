<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Households — {{ $branding['app_name'] }}</title>
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
            border-bottom: 2px solid #1b2b4b;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .org h1 { margin: 0; font-size: 20px; letter-spacing: -0.01em; color: #1b2b4b; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        .filter-strip {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 11px;
            color: #4b5563;
        }
        .filter-strip strong { color: #1b2b4b; font-weight: 700; }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
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

        td.num { font-variant-numeric: tabular-nums; }
        td.muted { color: #9ca3af; }

        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 700;
            background: #d1fae5;
            color: #065f46;
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
            <p>Households Directory Report</p>
        </div>
        <div class="doc-title">
            <h2>HOUSEHOLDS</h2>
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
            <div class="meta">{{ $households->count() }} {{ $households->count() === 1 ? 'household' : 'households' }}</div>
        </div>
    </div>

    @if (count($appliedFilters))
        <div class="filter-strip">
            <strong>Filters applied:</strong> {{ implode(' · ', $appliedFilters) }}
        </div>
    @endif

    @php
        $totalSize       = $households->sum('household_size');
        $firstTimerCount = $households->filter(fn ($h) => (int) $h->events_attended_count === 1)->count();
    @endphp

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-value">{{ $households->count() }}</div>
            <div class="stat-label">Total Households</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $totalSize }}</div>
            <div class="stat-label">Total People Served</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $firstTimerCount }}</div>
            <div class="stat-label">First-Timers</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 70px;">ID</th>
                <th>Household</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Location</th>
                <th style="width: 60px;">Zip</th>
                <th style="width: 50px;">Size</th>
                <th style="width: 90px;">Events</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($households as $h)
                <tr>
                    <td class="num">#{{ $h->household_number }}</td>
                    <td>
                        <strong>{{ $h->full_name }}</strong>
                        @if ((int) $h->events_attended_count === 1)
                            <span class="badge">First-Timer</span>
                        @endif
                    </td>
                    <td>{{ $h->email ?: '—' }}</td>
                    <td>{{ $h->phone ?: '—' }}</td>
                    <td>{{ $h->location ?: '—' }}</td>
                    <td>{{ $h->zip ?: '—' }}</td>
                    <td class="num">{{ $h->household_size }}</td>
                    <td class="num">{{ (int) $h->events_attended_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted" style="text-align:center; padding: 28px;">
                        No households match the applied filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>{{ $branding['app_name'] }} · Generated {{ now()->format('Y-m-d H:i') }}</span>
        <span>{{ $households->count() }} {{ $households->count() === 1 ? 'record' : 'records' }}</span>
    </div>

</div>

@if (! empty($autoPrint))
<script>
    window.addEventListener('load', () => {
        // Slight delay so the logo image has time to render before the print
        // dialog snapshots the page (otherwise some browsers print without it).
        setTimeout(() => window.print(), 250);
    });
</script>
@endif
</body>
</html>
