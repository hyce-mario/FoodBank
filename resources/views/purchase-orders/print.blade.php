<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $po->po_number }}</title>
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
        .sheet { max-width: 800px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .org h1 { margin: 0; font-size: 20px; letter-spacing: -0.01em; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 11px; }
        .org img { max-height: 56px; max-width: 220px; margin-bottom: 6px; display: block; }
        .doc-title {
            text-align: right;
        }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: 0.02em; color: #111; }
        .doc-title .po-num {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            margin-top: 4px;
            color: #111;
        }
        .doc-title .status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 6px;
            border: 1px solid currentColor;
        }
        .status-draft     { color: #b45309; }
        .status-received  { color: #15803d; }
        .status-cancelled { color: #b91c1c; }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        .meta-block h3 {
            margin: 0 0 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
        }
        .meta-block .value { font-size: 13px; font-weight: 600; color: #111; }
        .meta-block .sub   { font-size: 11px; color: #6b7280; margin-top: 1px; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        thead th {
            text-align: left;
            border-bottom: 2px solid #111;
            padding: 8px 6px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }
        tbody td {
            padding: 10px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .center { text-align: center; }

        tfoot td {
            padding: 10px 6px;
            font-size: 13px;
        }
        tfoot .total-label { text-align: right; font-weight: 600; color: #6b7280; }
        tfoot .total-amount {
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            color: #111;
            border-top: 2px solid #111;
        }

        .notes {
            margin: 24px 0;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background: #f9fafb;
        }
        .notes h3 {
            margin: 0 0 6px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
        }
        .notes p { margin: 0; white-space: pre-wrap; }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 48px;
        }
        .sig-block .line {
            border-bottom: 1px solid #111;
            margin-bottom: 4px;
            height: 32px;
        }
        .sig-block .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }

        .footer {
            margin-top: 32px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #9ca3af;
            display: flex;
            justify-content: space-between;
        }

        .toolbar {
            position: fixed;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 8px;
            z-index: 999;
        }
        .toolbar button {
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            padding: 8px 14px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
        }
        .toolbar button.primary {
            background: #111;
            color: white;
            border-color: #111;
        }

        @media print {
            body { padding: 0; }
            .toolbar { display: none !important; }
            .sheet  { max-width: none; }
            @page  { margin: 16mm; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()" class="primary">Print</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="sheet">

    <div class="header">
        <div class="org">
            @if ($logoPath)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($logoPath) }}" alt="{{ $orgName }}">
            @endif
            <h1>{{ $orgName }}</h1>
            @if ($orgEmail || $orgPhone)
                <p>
                    {{ $orgEmail }}{{ $orgEmail && $orgPhone ? ' · ' : '' }}{{ $orgPhone }}
                </p>
            @endif
            @if ($orgWebsite)
                <p>{{ $orgWebsite }}</p>
            @endif
        </div>

        <div class="doc-title">
            <h2>Purchase Order</h2>
            <div class="po-num">{{ $po->po_number }}</div>
            <span class="status status-{{ $po->status }}">{{ ucfirst($po->status) }}</span>
        </div>
    </div>

    <div class="meta">
        <div class="meta-block">
            <h3>Supplier</h3>
            <div class="value">{{ $po->supplier_name }}</div>
        </div>
        <div class="meta-block">
            <h3>Order Date</h3>
            <div class="value">{{ $po->order_date?->format('M j, Y') }}</div>
            @if ($po->received_date)
                <div class="sub">Received {{ $po->received_date->format('M j, Y') }}</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:8%">#</th>
                <th style="width:42%">Item</th>
                <th class="num" style="width:12%">Qty</th>
                <th class="num" style="width:18%">Unit Cost</th>
                <th class="num" style="width:20%">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($po->items as $i => $line)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $line->item?->name ?? '(item removed)' }}</strong>
                    @if ($line->item?->category)
                        <div style="color:#6b7280;font-size:11px;margin-top:1px;">{{ $line->item->category->name }}</div>
                    @endif
                </td>
                <td class="num">{{ number_format($line->quantity) }}</td>
                <td class="num">{{ fmt_currency((float) $line->unit_cost) }}</td>
                <td class="num"><strong>{{ fmt_currency((float) $line->line_total) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="total-label">PO Total</td>
                <td class="total-amount">{{ fmt_currency((float) $po->total_amount) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($po->notes)
    <div class="notes">
        <h3>Notes</h3>
        <p>{{ $po->notes }}</p>
    </div>
    @endif

    <div class="signatures">
        <div class="sig-block">
            <div class="line"></div>
            <div class="label">Authorised By</div>
        </div>
        <div class="sig-block">
            <div class="line"></div>
            <div class="label">Received By &amp; Date</div>
        </div>
    </div>

    <div class="footer">
        <span>Generated {{ now()->format('M j, Y g:i A') }}@if ($po->creator) by {{ $po->creator->name }}@endif</span>
        <span>{{ $po->po_number }}</span>
    </div>

</div>

<script>
    // Auto-fire the print dialog after a beat so the layout is fully painted.
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
</script>

</body>
</html>
