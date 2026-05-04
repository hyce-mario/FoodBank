<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service History — {{ $volunteer->full_name }}</title>
    @php
        $orgName    = \App\Services\SettingService::get('organization.name',    config('app.name', 'Food Bank'));
        $orgEmail   = \App\Services\SettingService::get('organization.email',   '');
        $orgPhone   = \App\Services\SettingService::get('organization.phone',   '');
        $orgWebsite = \App\Services\SettingService::get('organization.website', '');
        $logoPath   = \App\Services\SettingService::get('branding.logo_path',   '');
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
        .sheet { max-width: 900px; margin: 0 auto; }

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
        .doc-title .vol-name {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-top: 4px;
        }
        .doc-title .vol-meta {
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
            font-size: 18px;
            font-weight: 800;
            color: #111;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
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
        tbody tr:nth-child(even) td { background: #fafafa; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        td.muted { color: #6b7280; font-size: 10px; }

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
            <h2>SERVICE HISTORY</h2>
            <div class="vol-name">{{ $volunteer->full_name }}</div>
            <div class="vol-meta">
                @if ($volunteer->role) {{ $volunteer->role }} @endif
                @if ($volunteer->phone) · {{ $volunteer->phone }} @endif
                @if ($volunteer->email) · {{ $volunteer->email }} @endif
            </div>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-value">{{ number_format($stats['totalEvents']) }}</div>
            <div class="stat-label">Events Served</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($stats['totalHours'] ?? 0, 1) }}h</div>
            <div class="stat-label">Total Hours</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                {{ $stats['firstService'] ? $stats['firstService']->format('M j, Y') : '—' }}
            </div>
            <div class="stat-label">First Service</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                {{ $stats['lastService'] ? $stats['lastService']->format('M j, Y') : '—' }}
            </div>
            <div class="stat-label">Last Service</div>
        </div>
    </div>

    @if ($stats['checkIns']->isEmpty())
        <div class="empty">No event service history on record yet.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Role</th>
                    <th>Source</th>
                    <th>Check-In</th>
                    <th>Check-Out</th>
                    <th class="num">Hours</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stats['checkIns'] as $ci)
                    <tr>
                        <td>
                            <strong>{{ $ci->event?->name ?? 'Event removed' }}</strong>
                            @if ($ci->is_first_timer)
                                <span class="muted"> · ★ First Timer</span>
                            @endif
                        </td>
                        <td class="muted">{{ $ci->event?->date?->format('M j, Y') ?? '—' }}</td>
                        <td>{{ $ci->role ?: '—' }}</td>
                        <td class="muted">{{ $ci->sourceLabel() }}</td>
                        <td class="muted">{{ $ci->checked_in_at?->format('g:i A') ?? '—' }}</td>
                        <td class="muted">{{ $ci->checked_out_at?->format('g:i A') ?? '—' }}</td>
                        <td class="num">
                            @if ($ci->hours_served !== null)
                                {{ number_format((float) $ci->hours_served, 1) }}h
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Printed {{ now()->format('M j, Y g:i A') }}</span>
        <span>{{ $stats['checkIns']->count() }} {{ \Illuminate\Support\Str::plural('session', $stats['checkIns']->count()) }} · {{ number_format($stats['totalHours'] ?? 0, 1) }}h total</span>
    </div>
</div>

<script>
    // Auto-fire the print dialog after a beat so layout fully paints first.
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
</script>
</body>
</html>
