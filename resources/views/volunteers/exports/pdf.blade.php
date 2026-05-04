<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Volunteers — {{ $branding['app_name'] }}</title>
    <style>
        @page { margin: 30px 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9.5px;
            line-height: 1.4;
        }

        /* Use a table for the header layout — dompdf v3 has limited flex support. */
        table.header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #1b2b4b;
            margin-bottom: 14px;
        }
        table.header td { vertical-align: top; padding: 0 0 12px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 50px; max-width: 200px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 16px; color: #1b2b4b; font-weight: bold; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 9px; }
        .doc-title h2 { margin: 0; font-size: 18px; color: #111; letter-spacing: 0.05em; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 3px; }

        .filter-strip {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            padding: 7px 10px;
            margin-bottom: 12px;
            font-size: 9px;
            color: #4b5563;
        }
        .filter-strip strong { color: #1b2b4b; }

        /* Stat strip — three side-by-side cards via a 3-cell table. */
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
            font-size: 9px;
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
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 7.5px;
            font-weight: bold;
            background: #fef3c7;
            color: #92400e;
            margin-left: 3px;
        }
        .badge.gray { background: #f3f4f6; color: #6b7280; }

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
        .page-num:after { content: counter(page); }
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
            <p>Volunteer Roster Report</p>
        </td>
        <td class="right doc-title">
            <h2>VOLUNTEERS</h2>
            <div class="meta">Generated {{ now()->format('M j, Y g:i A') }}</div>
            <div class="meta">{{ $volunteers->count() }} {{ $volunteers->count() === 1 ? 'volunteer' : 'volunteers' }}</div>
        </td>
    </tr>
</table>

@if (count($appliedFilters))
    <div class="filter-strip">
        <strong>Filters applied:</strong> {{ implode(' · ', $appliedFilters) }}
    </div>
@endif

@php
    $firstTimerCount = $volunteers->filter(fn ($v) => (int) ($v->events_served_count ?? 0) === 1)->count();
    $totalEventsServed = (int) $volunteers->sum(fn ($v) => (int) ($v->events_served_count ?? 0));
@endphp

<table class="stats">
    <tr>
        <td>
            <div class="stat-value">{{ $volunteers->count() }}</div>
            <div class="stat-label">Total Volunteers</div>
        </td>
        <td>
            <div class="stat-value">{{ $totalEventsServed }}</div>
            <div class="stat-label">Total Events Served</div>
        </td>
        <td>
            <div class="stat-value">{{ $firstTimerCount }}</div>
            <div class="stat-label">First-Timers</div>
        </td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th style="width: 11%;">Role</th>
            <th style="width: 7%;">Groups</th>
            <th style="width: 8%;">Events</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($volunteers as $v)
            <tr>
                <td>
                    <strong>{{ $v->full_name }}</strong>
                    @if ((int) ($v->events_served_count ?? 0) === 0)
                        <span class="badge gray">New</span>
                    @elseif ((int) ($v->events_served_count ?? 0) === 1)
                        <span class="badge">First Timer</span>
                    @endif
                </td>
                <td>{{ $v->phone ?: '—' }}</td>
                <td>{{ $v->email ?: '—' }}</td>
                <td>{{ $v->role ?: '—' }}</td>
                <td class="num">{{ (int) ($v->groups_count ?? 0) }}</td>
                <td class="num">{{ (int) ($v->events_served_count ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="muted" style="padding: 24px;">
                    No volunteers match the applied filters.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <span class="left">{{ $branding['app_name'] }} · {{ now()->format('Y-m-d H:i') }}</span>
    <span class="right">Page <span class="page-num"></span> of <span class="page-total"></span></span>
</div>

</body>
</html>
