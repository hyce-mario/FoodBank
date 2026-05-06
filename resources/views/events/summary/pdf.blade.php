<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Summary — {{ $event->name }}</title>
    @php
        $orgEmail   = \App\Services\SettingService::get('organization.email',   '');
        $orgPhone   = \App\Services\SettingService::get('organization.phone',   '');
        $orgWebsite = \App\Services\SettingService::get('organization.website', '');
        $sectionLabels = [
            'event_details' => 'Event Details',
            'attendees'     => 'Attendees',
            'volunteers'    => 'Volunteers',
            'reviews'       => 'Reviews',
            'inventory'     => 'Inventory',
            'finance'       => 'Finance',
            'queue'         => 'Queue Summary',
            'evaluation'    => 'Evaluation',
        ];
    @endphp
    <style>
        /* DomPDF-friendly: table layout, DejaVu font, no flex/grid. */
        @page { margin: 24mm 18mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.45;
        }

        /* Header */
        table.header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #1b2b4b;
            margin-bottom: 16px;
        }
        table.header td { vertical-align: top; padding: 0 0 12px 0; }
        table.header td.right { text-align: right; }
        .org img { max-height: 50px; max-width: 200px; margin-bottom: 4px; }
        .org h1 { margin: 0; font-size: 14px; color: #1b2b4b; font-weight: bold; }
        .org p  { margin: 2px 0 0; color: #6b7280; font-size: 9px; }
        .doc-title h2 { margin: 0; font-size: 16px; color: #111; letter-spacing: 0.05em; }
        .doc-title .event-name { font-size: 12px; font-weight: bold; color: #374151; margin-top: 3px; }
        .doc-title .meta { font-size: 9px; color: #6b7280; margin-top: 2px; }

        /* Section blocks */
        .section {
            page-break-inside: avoid;
            margin-bottom: 16px;
        }
        .section-title {
            background: #1b2b4b;
            color: #fff;
            padding: 7px 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .section-body {
            padding: 0 4px;
        }

        /* Stat tile grid (table-based) */
        table.stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 8px;
        }
        table.stats td {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background: #fafafa;
            padding: 8px 10px;
            vertical-align: top;
            width: 25%;
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
        .stat-sub { font-size: 8px; color: #9ca3af; margin-top: 1px; }

        /* Key-value list */
        table.kv {
            width: 100%;
            border-collapse: collapse;
        }
        table.kv td {
            padding: 4px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        table.kv td.label {
            color: #6b7280;
            width: 35%;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: bold;
        }

        /* Data tables */
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-top: 4px;
        }
        table.data thead th {
            background: #f3f4f6;
            color: #374151;
            text-align: left;
            padding: 5px 7px;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            border-bottom: 1px solid #d1d5db;
        }
        table.data tbody td {
            padding: 4px 7px;
            border-bottom: 1px solid #f3f4f6;
        }
        table.data td.num { text-align: right; }

        /* Bar chart row */
        .bar-row { margin-bottom: 4px; }
        .bar-track {
            width: 100%;
            background: #f3f4f6;
            height: 6px;
            border-radius: 3px;
            position: relative;
            overflow: hidden;
        }
        .bar-fill {
            height: 6px;
            border-radius: 3px;
        }

        /* Insight cards (Evaluation) */
        .insight {
            border: 1px solid #e5e7eb;
            border-left-width: 3px;
            padding: 6px 10px;
            margin-bottom: 5px;
            font-size: 9px;
        }
        .insight.positive   { border-left-color: #10b981; background: #ecfdf5; }
        .insight.neutral    { border-left-color: #3b82f6; background: #eff6ff; }
        .insight.concerning { border-left-color: #ef4444; background: #fef2f2; }
        .insight .cat {
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }
        .insight .msg { margin-top: 2px; color: #1f2937; }

        /* Footer */
        .footer {
            margin-top: 18px;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #6b7280;
        }
        .footer table { width: 100%; }
        .footer td.right { text-align: right; }
    </style>
</head>
<body>

{{-- Cover header --}}
<table class="header">
    <tr>
        <td class="org">
            @if(! empty($branding['logo_src']))
                <img src="{{ $branding['logo_src'] }}" alt="{{ $branding['app_name'] }}">
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
            <p>
                @if($orgEmail) {{ $orgEmail }} @endif
                @if($orgPhone) · {{ $orgPhone }} @endif
                @if($orgWebsite) · {{ $orgWebsite }} @endif
            </p>
        </td>
        <td class="right doc-title">
            <h2>EVENT SUMMARY</h2>
            <div class="event-name">{{ $event->name }}</div>
            <div class="meta">
                {{ $event->date?->format('D, M j, Y') }}
                @if($event->location) · {{ $event->location }} @endif
            </div>
        </td>
    </tr>
</table>

@foreach ($sections as $section)
    @php $d = $data[$section] ?? null; @endphp
    @continue(! $d && $section !== 'evaluation')

    <div class="section">
        <div class="section-title">{{ $sectionLabels[$section] ?? ucfirst($section) }}</div>
        <div class="section-body">

            {{-- ── Event Details ─────────────────────────────────────────────── --}}
            @if ($section === 'event_details')
                <table class="kv">
                    <tr><td class="label">Name</td><td>{{ $d['name'] }}</td></tr>
                    <tr><td class="label">Date</td><td>{{ $d['date']?->format('D, M j, Y') ?? '—' }}</td></tr>
                    <tr><td class="label">Location</td><td>{{ $d['location'] ?: '—' }}</td></tr>
                    <tr><td class="label">Lanes</td><td>{{ $d['lanes'] ?? '—' }}</td></tr>
                    <tr><td class="label">Description</td><td>{{ $d['description'] ?: '—' }}</td></tr>
                    <tr><td class="label">Volunteer Group</td>
                        <td>{{ $d['group']['name'] ?? '—' }}
                            @if(! empty($d['group'])) ({{ $d['group']['roster_count'] }} on roster, {{ $d['assigned_count'] }} assigned) @endif
                        </td></tr>
                    <tr><td class="label">Allocation Ruleset</td>
                        <td>{{ $d['ruleset']['name'] ?? '—' }}
                            @if(! empty($d['ruleset'])) (max size {{ $d['ruleset']['max_household_size'] }}) @endif
                        </td></tr>
                </table>
                @if (! empty($d['ruleset']['rules']))
                    <table class="data" style="margin-top: 6px;">
                        <thead><tr><th>Household Size</th><th class="num">Bags</th></tr></thead>
                        <tbody>
                            @foreach ($d['ruleset']['rules'] as $rule)
                                <tr>
                                    <td>{{ $rule['min'] ?? '?' }}@if(! empty($rule['max'])) – {{ $rule['max'] }}@else+@endif</td>
                                    <td class="num">{{ $rule['bags'] ?? '?' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

            {{-- ── Attendees ─────────────────────────────────────────────────── --}}
            @elseif ($section === 'attendees')
                <table class="stats"><tr>
                    <td><div class="stat-value">{{ number_format($d['pre_registered_total']) }}</div>
                        <div class="stat-label">Pre-Registered</div>
                        <div class="stat-sub">{{ $d['pre_reg_attended'] }} attended · {{ $d['pre_reg_no_show'] }} no-show</div></td>
                    <td><div class="stat-value">{{ number_format($d['walk_ins']) }}</div>
                        <div class="stat-label">Walk-Ins</div></td>
                    <td><div class="stat-value">{{ number_format($d['total_households']) }}</div>
                        <div class="stat-label">Households Served</div></td>
                    <td><div class="stat-value">{{ number_format($d['total_persons']) }}</div>
                        <div class="stat-label">People Served</div>
                        <div class="stat-sub">avg {{ $d['avg_household_size'] }} / household</div></td>
                </tr></table>
                <table class="kv" style="margin-top: 6px;">
                    <tr><td class="label">Children</td><td>{{ number_format($d['children']) }}</td></tr>
                    <tr><td class="label">Adults</td><td>{{ number_format($d['adults']) }}</td></tr>
                    <tr><td class="label">Seniors</td><td>{{ number_format($d['seniors']) }}</td></tr>
                    @if ($d['pre_reg_match_rate'] !== null)
                        <tr><td class="label">Show-up rate</td><td>{{ round($d['pre_reg_match_rate'] * 100) }}%</td></tr>
                    @endif
                </table>

            {{-- ── Volunteers ────────────────────────────────────────────────── --}}
            @elseif ($section === 'volunteers')
                <table class="stats"><tr>
                    <td><div class="stat-value">{{ $d['scheduled'] }}</div><div class="stat-label">Scheduled</div></td>
                    <td><div class="stat-value">{{ $d['pre_assigned_in'] }}</div><div class="stat-label">Showed Up</div></td>
                    <td><div class="stat-value">{{ $d['walk_ins'] }}</div><div class="stat-label">Walk-Ins</div></td>
                    <td><div class="stat-value">{{ $d['total_check_ins'] }}</div><div class="stat-label">Total</div></td>
                </tr></table>
                <table class="kv" style="margin-top: 6px;">
                    <tr><td class="label">New volunteers</td><td>{{ $d['new_volunteers'] }}</td></tr>
                    <tr><td class="label">First-timers</td><td>{{ $d['first_timers'] }}</td></tr>
                    <tr><td class="label">Total hours</td><td>{{ $d['total_hours'] }}h</td></tr>
                    <tr><td class="label">Avg per volunteer</td><td>{{ $d['avg_hours'] }}h</td></tr>
                </table>

            {{-- ── Reviews ───────────────────────────────────────────────────── --}}
            @elseif ($section === 'reviews')
                @if ($d['total'] === 0)
                    <p style="color:#9ca3af; font-style:italic;">No reviews submitted.</p>
                @else
                    <table class="stats"><tr>
                        <td style="width:33%"><div class="stat-value">{{ $d['avg_rating'] }}/5</div><div class="stat-label">Avg Rating</div></td>
                        <td style="width:67%"><div class="stat-value">{{ $d['total'] }}</div><div class="stat-label">Total reviews</div>
                            <div class="stat-sub">
                                @foreach([5,4,3,2,1] as $s)
                                    {{ $s }}★ {{ $d['distribution'][$s] }}@if(!$loop->last) · @endif
                                @endforeach
                            </div></td>
                    </tr></table>
                    @if ($d['good_reviews']->isNotEmpty())
                        <p style="font-weight:bold; color:#065f46; margin-top:10px; margin-bottom:4px;">Top Positive (4–5★)</p>
                        <table class="data">
                            <thead><tr><th>Reviewer</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
                            <tbody>
                                @foreach ($d['good_reviews'] as $r)
                                    <tr>
                                        <td>{{ $r->reviewer_name ?: 'Anonymous' }}</td>
                                        <td>{{ $r->rating }}★</td>
                                        <td>{{ $r->review_text }}</td>
                                        <td>{{ $r->created_at?->format('M j, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if ($d['bad_reviews']->isNotEmpty())
                        <p style="font-weight:bold; color:#991b1b; margin-top:10px; margin-bottom:4px;">Top Concerns (1–2★)</p>
                        <table class="data">
                            <thead><tr><th>Reviewer</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
                            <tbody>
                                @foreach ($d['bad_reviews'] as $r)
                                    <tr>
                                        <td>{{ $r->reviewer_name ?: 'Anonymous' }}</td>
                                        <td>{{ $r->rating }}★</td>
                                        <td>{{ $r->review_text }}</td>
                                        <td>{{ $r->created_at?->format('M j, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @endif

            {{-- ── Inventory ─────────────────────────────────────────────────── --}}
            @elseif ($section === 'inventory')
                @if ($d['total_items'] === 0)
                    <p style="color:#9ca3af; font-style:italic;">No inventory was allocated to this event.</p>
                @else
                    <table class="stats"><tr>
                        <td><div class="stat-value">{{ number_format($d['total_allocated']) }}</div><div class="stat-label">Allocated</div></td>
                        <td><div class="stat-value">{{ number_format($d['total_distributed']) }}</div><div class="stat-label">Distributed</div></td>
                        <td><div class="stat-value">{{ number_format($d['total_returned']) }}</div><div class="stat-label">Returned</div></td>
                        <td><div class="stat-value">{{ round($d['distribution_rate'] * 100) }}%</div><div class="stat-label">Rate</div></td>
                    </tr></table>
                    <table class="data">
                        <thead><tr>
                            <th>Item</th><th class="num">Allocated</th><th class="num">Distributed</th>
                            <th class="num">Returned</th><th class="num">Remaining</th><th class="num">Rate</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($d['rows'] as $r)
                                <tr>
                                    <td>{{ $r['name'] }}</td>
                                    <td class="num">{{ number_format($r['allocated']) }}</td>
                                    <td class="num">{{ number_format($r['distributed']) }}</td>
                                    <td class="num">{{ number_format($r['returned']) }}</td>
                                    <td class="num">{{ number_format($r['remaining']) }}</td>
                                    <td class="num">{{ round($r['rate'] * 100) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

            {{-- ── Finance ───────────────────────────────────────────────────── --}}
            @elseif ($section === 'finance')
                @if (! empty($d['gated']))
                    <p style="color:#9ca3af; font-style:italic;">Finance data requires the finance.view permission.</p>
                @else
                    <table class="stats"><tr>
                        <td style="width:33%"><div class="stat-value" style="color:#065f46;">${{ number_format($d['income']['total'], 2) }}</div><div class="stat-label">Income</div></td>
                        <td style="width:33%"><div class="stat-value" style="color:#991b1b;">${{ number_format($d['expense']['total'], 2) }}</div><div class="stat-label">Expense</div></td>
                        <td style="width:34%"><div class="stat-value">{{ $d['net'] >= 0 ? '+' : '−' }}${{ number_format(abs($d['net']), 2) }}</div><div class="stat-label">Net</div></td>
                    </tr></table>
                    @foreach (['income' => 'Top Income Sources', 'expense' => 'Top Expense Sources'] as $kind => $title)
                        @if (! empty($d[$kind]['top_sources']))
                            <p style="font-weight:bold; margin-top:10px; margin-bottom:4px;">{{ $title }}</p>
                            <table class="data">
                                <thead><tr><th>Source</th><th class="num">Amount</th><th class="num">Share</th></tr></thead>
                                <tbody>
                                    @foreach ($d[$kind]['top_sources'] as $src)
                                        <tr>
                                            <td>{{ $src['name'] }}</td>
                                            <td class="num">${{ number_format($src['amount'], 2) }}</td>
                                            <td class="num">{{ round($src['pct'] * 100) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    @endforeach
                @endif

            {{-- ── Queue ─────────────────────────────────────────────────────── --}}
            @elseif ($section === 'queue')
                @if ($d['total_visits'] === 0)
                    <p style="color:#9ca3af; font-style:italic;">No visits recorded.</p>
                @else
                    @php $hm = fn ($m) => \App\Services\EventSummaryService::formatHm($m); @endphp
                    <table class="stats"><tr>
                        <td><div class="stat-value">{{ $hm($d['avg_total_time']) }}</div><div class="stat-label">Total Visit</div></td>
                        <td><div class="stat-value">{{ $hm($d['avg_checkin_to_queue']) }}</div><div class="stat-label">Check-in → Queue</div></td>
                        <td><div class="stat-value">{{ $hm($d['avg_queue_to_loaded']) }}</div><div class="stat-label">Queue → Loaded</div></td>
                        <td><div class="stat-value">{{ $hm($d['avg_loaded_to_exited']) }}</div><div class="stat-label">Loaded → Exit</div></td>
                    </tr></table>
                    <table class="kv" style="margin-top: 6px;">
                        <tr><td class="label">Total visits</td><td>{{ number_format($d['total_visits']) }}</td></tr>
                        <tr><td class="label">Completed</td><td>{{ number_format($d['completed_visits']) }}</td></tr>
                        <tr><td class="label">Lanes</td><td>{{ $d['lanes'] }}</td></tr>
                        <tr><td class="label">Bags distributed</td><td>{{ number_format($d['bags_distributed']) }}</td></tr>
                    </table>
                @endif

            {{-- ── Evaluation ────────────────────────────────────────────────── --}}
            @elseif ($section === 'evaluation')
                @if (empty($d))
                    <p style="color:#9ca3af; font-style:italic;">Not enough data to evaluate this event.</p>
                @else
                    @foreach ($d as $i)
                        <div class="insight {{ $i['kind'] }}">
                            <span class="cat">{{ $i['category'] }}</span>
                            <div class="msg">{{ $i['message'] }}</div>
                        </div>
                    @endforeach
                @endif
            @endif

        </div>
    </div>
@endforeach

<div class="footer">
    <table>
        <tr>
            <td>Generated {{ now()->format('M j, Y g:i A') }}</td>
            <td class="right">{{ count($sections) }} section{{ count($sections) === 1 ? '' : 's' }} · {{ $branding['app_name'] }}</td>
        </tr>
    </table>
</div>

</body>
</html>
