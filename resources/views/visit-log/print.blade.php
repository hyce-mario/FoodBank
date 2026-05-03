<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visit Log — {{ $event->name }}</title>
    @php
        $orgName    = \App\Services\SettingService::get('organization.name',    config('app.name', 'Food Bank'));
        $orgEmail   = \App\Services\SettingService::get('organization.email',   '');
        $orgPhone   = \App\Services\SettingService::get('organization.phone',   '');
        $orgWebsite = \App\Services\SettingService::get('organization.website', '');
        $logoPath   = \App\Services\SettingService::get('branding.logo_path',   '');

        $fmtMins = function (?int $m): string {
            if ($m === null) return '—';
            if ($m < 60) return $m . 'm';
            $h = intdiv($m, 60);
            $r = $m % 60;
            return $r === 0 ? "{$h}h" : "{$h}h {$r}m";
        };
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
            margin-bottom: 16px;
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

        .timing-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .timing-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            background: white;
        }
        .timing-card .timing-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            font-weight: 600;
        }
        .timing-card .timing-value {
            font-size: 16px;
            font-weight: 700;
            color: #111;
            margin-top: 4px;
            font-variant-numeric: tabular-nums;
        }

        h3.section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin: 0 0 8px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        thead th {
            background: #f3f4f6;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #374151;
            border-bottom: 1px solid #d1d5db;
        }
        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 11px;
            color: #1f2937;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #fafafa; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.muted { color: #6b7280; font-size: 10px; }
        td.long-row { background: #fef3c7 !important; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .badge-checked_in { background: #eff6ff; color: #1d4ed8; }
        .badge-queued     { background: #f5f3ff; color: #6d28d9; }
        .badge-loading    { background: #fff7ed; color: #c2410c; }
        .badge-loaded     { background: #fef3c7; color: #b45309; }
        .badge-exited     { background: #f0fdf4; color: #15803d; }

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
            @if ($logoPath)
                <img src="{{ asset('storage/' . $logoPath) }}" alt="{{ $orgName }}">
            @endif
            <h1>{{ $orgName }}</h1>
            <p>
                @if ($orgEmail) {{ $orgEmail }} @endif
                @if ($orgPhone) · {{ $orgPhone }} @endif
                @if ($orgWebsite) · {{ $orgWebsite }} @endif
            </p>
        </div>
        <div class="doc-title">
            <h2>VISIT LOG</h2>
            <div class="event-name">{{ $event->name }}</div>
            <div class="event-meta">
                {{ $event->date?->format('D, M j, Y') }}
                @if ($event->location) · {{ $event->location }} @endif
                · {{ $event->lanes }} {{ \Illuminate\Support\Str::plural('lane', $event->lanes) }}
            </div>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-value">{{ number_format($summary['total_visits']) }}</div>
            <div class="stat-label">Total Check-ins</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($summary['households_served']) }}</div>
            <div class="stat-label">Households Served</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($summary['people_served']) }}</div>
            <div class="stat-label">People Served</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($summary['bags_distributed']) }}</div>
            <div class="stat-label">Bags Distributed</div>
        </div>
    </div>

    <h3 class="section-title">Average Time Per Stage</h3>
    <div class="timing-strip">
        <div class="timing-card">
            <div class="timing-label">Check-in → Queue</div>
            <div class="timing-value">{{ $summary['avg_checkin_to_queue'] > 0 ? $fmtMins((int) round($summary['avg_checkin_to_queue'])) : '—' }}</div>
        </div>
        <div class="timing-card">
            <div class="timing-label">Queue → Loading</div>
            <div class="timing-value">{{ $summary['avg_queue_to_loaded'] > 0 ? $fmtMins((int) round($summary['avg_queue_to_loaded'])) : '—' }}</div>
        </div>
        <div class="timing-card">
            <div class="timing-label">Loading → Exit</div>
            <div class="timing-value">{{ $summary['avg_loaded_to_exited'] > 0 ? $fmtMins((int) round($summary['avg_loaded_to_exited'])) : '—' }}</div>
        </div>
        <div class="timing-card">
            <div class="timing-label">Avg Total</div>
            <div class="timing-value">{{ $summary['avg_total_time'] > 0 ? $fmtMins((int) round($summary['avg_total_time'])) : '—' }}</div>
        </div>
    </div>

    <h3 class="section-title">
        Visit Detail ({{ $visits->count() }} {{ \Illuminate\Support\Str::plural('visit', $visits->count()) }})
        @if (!empty($filters))
            <span style="font-weight: 500; color: #6b7280; text-transform: none; letter-spacing: 0;">
                — Filtered by: {{ $filters }}
            </span>
        @endif
    </h3>

    @if ($visits->isEmpty())
        <div class="empty">No visits recorded for this event.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Household</th>
                    <th class="num">Lane</th>
                    <th>Status</th>
                    <th class="num">Check-in</th>
                    <th class="num">Queued</th>
                    <th class="num">Loaded</th>
                    <th class="num">Exited</th>
                    <th class="num" title="Check-in to Queue">C→Q</th>
                    <th class="num" title="Queue to Loaded">Q→L</th>
                    <th class="num" title="Loaded to Exit">L→E</th>
                    <th class="num">Total</th>
                    <th class="num">Bags</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($visits as $v)
                    @php $isLong = $v->total_time !== null && $v->total_time > 45; @endphp
                    <tr>
                        <td class="{{ $isLong ? 'long-row' : '' }}">
                            <strong>{{ $v->full_name }}</strong>
                            <div class="muted">#{{ $v->household_number }}</div>
                        </td>
                        <td class="num {{ $isLong ? 'long-row' : '' }}">{{ $v->lane }}</td>
                        <td class="{{ $isLong ? 'long-row' : '' }}">
                            <span class="badge badge-{{ $v->visit_status }}">{{ $v->status_label }}</span>
                        </td>
                        <td class="num muted {{ $isLong ? 'long-row' : '' }}">{{ optional($v->start_time)->format('g:i A') ?: '—' }}</td>
                        <td class="num muted {{ $isLong ? 'long-row' : '' }}">{{ optional($v->queued_at)->format('g:i A') ?: '—' }}</td>
                        <td class="num muted {{ $isLong ? 'long-row' : '' }}">{{ optional($v->loading_completed_at)->format('g:i A') ?: '—' }}</td>
                        <td class="num muted {{ $isLong ? 'long-row' : '' }}">{{ optional($v->exited_at)->format('g:i A') ?: '—' }}</td>
                        <td class="num {{ $isLong ? 'long-row' : '' }}">{{ $fmtMins($v->checkin_to_queue) }}</td>
                        <td class="num {{ $isLong ? 'long-row' : '' }}">{{ $fmtMins($v->queue_to_loaded) }}</td>
                        <td class="num {{ $isLong ? 'long-row' : '' }}">{{ $fmtMins($v->loaded_to_exited) }}</td>
                        <td class="num {{ $isLong ? 'long-row' : '' }}"><strong>{{ $fmtMins($v->total_time) }}</strong></td>
                        <td class="num {{ $isLong ? 'long-row' : '' }}">{{ $v->served_bags ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Printed {{ now()->format('M j, Y g:i A') }}</span>
        <span>
            {{ $visits->count() }} {{ \Illuminate\Support\Str::plural('visit', $visits->count()) }}
            @if (!empty($filters)) (filtered) @endif
            · Long visits (&gt;45m) shaded
        </span>
    </div>
</div>

<script>
    // Auto-fire the print dialog after a beat so layout fully paints first.
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
</script>
</body>
</html>
