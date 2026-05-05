<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Inventory — {{ \App\Services\SettingService::get('organization.name', config('app.name', 'Food Bank')) }}</title>
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
        .doc-title .doc-meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fafafa;
        }
        .stat-card .stat-value {
            font-size: 18px;
            font-weight: 800;
            color: #111;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-top: 6px;
            font-weight: 600;
        }
        .stat-card.amber .stat-value { color: #b45309; }
        .stat-card.red   .stat-value { color: #b91c1c; }

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
        td.sku   { font-family: 'Menlo', 'Consolas', monospace; font-size: 10px; color: #6b7280; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .badge-in       { background: #f0fdf4; color: #15803d; }
        .badge-low      { background: #fffbeb; color: #b45309; }
        .badge-out      { background: #fef2f2; color: #b91c1c; }
        .badge-expired  { background: #fef2f2; color: #b91c1c; }
        .badge-expiring { background: #fffbeb; color: #b45309; }

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
            <h2>INVENTORY</h2>
            <div class="doc-meta">
                As of {{ now()->format('D, M j, Y g:i A') }}
            </div>
        </div>
    </div>

    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-value">{{ number_format($summary['total']) }}</div>
            <div class="stat-label">Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($summary['total_qty']) }}</div>
            <div class="stat-label">Total Qty</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-value">{{ number_format($summary['low_stock']) }}</div>
            <div class="stat-label">Low Stock</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value">{{ number_format($summary['out_of_stock']) }}</div>
            <div class="stat-label">Out of Stock</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-value">{{ number_format($summary['expiring_soon']) }}</div>
            <div class="stat-label">Expiring Soon</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value">{{ number_format($summary['expired']) }}</div>
            <div class="stat-label">Expired</div>
        </div>
    </div>

    <h3 class="section-title">
        Items ({{ $items->count() }})
        @if (!empty($filters))
            <span style="font-weight: 500; color: #6b7280; text-transform: none; letter-spacing: 0;">
                — Filtered by: {{ $filters }}
            </span>
        @endif
    </h3>

    @if ($items->isEmpty())
        <div class="empty">No items match the current filters.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th class="num">Qty</th>
                    <th class="num">Reorder</th>
                    <th>Mfg Date</th>
                    <th>Expiry</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    @php
                        $stock  = $item->stockStatus();
                        $expiry = $item->expiryStatus();
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            @if ($item->sku)
                                <div class="sku">{{ $item->sku }}</div>
                            @endif
                        </td>
                        <td>{{ $item->category?->name ?? '—' }}</td>
                        <td>{{ $item->unit_type }}</td>
                        <td class="num"><strong>{{ number_format($item->quantity_on_hand) }}</strong></td>
                        <td class="num muted">{{ number_format($item->reorder_level) }}</td>
                        <td class="muted">{{ $item->manufacturing_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="muted">{{ $item->expiry_date?->format('M j, Y') ?? '—' }}</td>
                        <td>
                            <span class="badge badge-{{ $stock }}">{{ $item->stockLabel() }}</span>
                            @if ($expiry === 'expired')
                                <span class="badge badge-expired">Expired</span>
                            @elseif ($expiry === 'expiring_soon')
                                <span class="badge badge-expiring">Expiring</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span>Printed {{ now()->format('M j, Y g:i A') }}</span>
        <span>
            {{ $items->count() }} {{ \Illuminate\Support\Str::plural('item', $items->count()) }}
            @if (!empty($filters)) (filtered) @endif
        </span>
    </div>
</div>

<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
</script>
</body>
</html>
