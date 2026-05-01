<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attendees — {{ $event->name }}</title>
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
        tbody tr:nth-child(even) td { background: #fafafa; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
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
            <h2>ATTENDEES</h2>
            <div class="event-name">{{ $event->name }}</div>
            <div class="event-meta">
                {{ $event->date?->format('D, M j, Y') }}
                @if ($event->location) · {{ $event->location }} @endif
            </div>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-value">{{ number_format($attendeeStats['total']) }}</div>
            <div class="stat-label">Total Attendees</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($attendeeStats['children']) }}</div>
            <div class="stat-label">Children</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($attendeeStats['adults']) }}</div>
            <div class="stat-label">Adults</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($attendeeStats['seniors']) }}</div>
            <div class="stat-label">Seniors</div>
        </div>
    </div>

    @if ($event->preRegistrations->isEmpty())
        <div class="empty">No attendees pre-registered for this event.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Attendee #</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Location</th>
                    <th class="num">Size</th>
                    <th class="num">C</th>
                    <th class="num">A</th>
                    <th class="num">S</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($event->preRegistrations as $reg)
                    <tr>
                        <td>{{ $reg->attendee_number ?? str_pad($reg->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td><strong>{{ $reg->full_name }}</strong></td>
                        <td class="muted">{{ $reg->email }}</td>
                        <td class="muted">{{ collect([$reg->city, $reg->state, $reg->zipcode])->filter()->implode(', ') ?: '—' }}</td>
                        <td class="num">{{ (int) $reg->household_size }}</td>
                        <td class="num">{{ (int) $reg->children_count }}</td>
                        <td class="num">{{ (int) $reg->adults_count }}</td>
                        <td class="num">{{ (int) $reg->seniors_count }}</td>
                        <td>{{ $reg->match_status ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Printed {{ now()->format('M j, Y g:i A') }}</span>
        <span>{{ $event->preRegistrations->count() }} attendee{{ $event->preRegistrations->count() === 1 ? '' : 's' }}</span>
    </div>
</div>

<script>
    // Auto-fire the print dialog after a beat so layout fully paints first.
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
</script>
</body>
</html>
