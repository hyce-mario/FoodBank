@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')

@php
    // Whether any filter is active — used to surface a Clear button + the
    // "filtered" indicator in the print-only header.
    $hasActiveFilters = collect(['user_id', 'action', 'model', 'from', 'to'])
        ->some(fn ($k) => filled(request($k)));

    // Pretty labels for the print-only header (so the printed page makes
    // sense without seeing the filter dropdowns).
    $activeUserName = optional($users->firstWhere('id', (int) request('user_id')))->name;
    $actionLabels   = [
        'created'             => 'Created',
        'updated'             => 'Updated',
        'deleted'             => 'Deleted',
        'permissions_changed' => 'Permissions Changed',
    ];
@endphp

{{-- ── Header ────────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 no-print">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Audit Log</h1>
        <p class="text-sm text-gray-500 mt-0.5">Track who changed what, and when.</p>
    </div>
</div>

{{-- ── Print-only header ─────────────────────────────────────────────── --}}
{{-- Renders only when the page is being printed (or saved as PDF from the
     browser). Gives the printout context — org name + active filter
     state + total entry count — since the toolbar/sidebar are hidden. --}}
@php
    $orgName = \App\Services\SettingService::get('organization.name', config('app.name', 'Food Bank'));
@endphp
<div class="print-only print-header" aria-hidden="true">
    <div class="ph-row">
        <div class="ph-org">
            <strong>{{ $orgName }}</strong>
            <span class="ph-meta">Audit Log</span>
        </div>
        <div class="ph-meta-block">
            <div>{{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}@if ($hasActiveFilters) <span style="color:#92400e; font-weight:600;">(filtered)</span>@endif</div>
            <div>Generated {{ now()->format('M j, Y g:i A') }}</div>
        </div>
    </div>
    @if ($hasActiveFilters)
        <div class="ph-filters">
            <strong>Filters:</strong>
            @if ($activeUserName)
                <span>User = {{ $activeUserName }}</span>
            @endif
            @if (request('action'))
                <span>Action = {{ $actionLabels[request('action')] ?? request('action') }}</span>
            @endif
            @if (request('model'))
                <span>Model contains "{{ request('model') }}"</span>
            @endif
            @if (request('from'))
                <span>From {{ \Carbon\Carbon::parse(request('from'))->format('M j, Y') }}</span>
            @endif
            @if (request('to'))
                <span>To {{ \Carbon\Carbon::parse(request('to'))->format('M j, Y') }}</span>
            @endif
        </div>
    @endif
</div>

{{-- ── Filter toolbar — single inline row, mirrors volunteers / finance
        reports pattern. Uses placeholders + selects on one flex-wrap line
        so iPad-portrait doesn't waste vertical space. ─────────────────── --}}
<form method="GET" action="{{ route('audit-logs.index') }}"
      class="flex flex-wrap items-center gap-2 px-4 py-3 mb-5 border border-gray-200 rounded-xl bg-white shadow-sm no-print">
    <select name="user_id"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">All users</option>
        @foreach ($users as $u)
            <option value="{{ $u->id }}" @selected((int) request('user_id') === $u->id)>{{ $u->name }}</option>
        @endforeach
    </select>

    <select name="action"
            class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
        <option value="">All actions</option>
        @foreach ($actionLabels as $value => $label)
            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <input type="text" name="model" value="{{ request('model') }}"
           placeholder="Model (e.g. Household)"
           class="flex-1 min-w-[160px] px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                  placeholder:text-gray-400">

    <input type="date" name="from" value="{{ request('from') }}"
           title="From date"
           class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
    <span class="text-gray-400 text-xs">to</span>
    <input type="date" name="to" value="{{ request('to') }}"
           title="To date"
           class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">

    {{-- Preserve the per-page choice through filter submits --}}
    @if ($pp = request('per_page'))
        <input type="hidden" name="per_page" value="{{ $pp }}">
    @endif

    <button type="submit"
            class="px-4 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
        Apply
    </button>

    @if ($hasActiveFilters)
        <a href="{{ route('audit-logs.index') }}"
           class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Clear
        </a>
    @endif

    {{-- Vertical divider — visible on sm+ where the row stays single-line --}}
    <span class="hidden sm:block w-px h-7 bg-gray-300 mx-1" aria-hidden="true"></span>

    {{-- Print — fires window.print() on the current page; @media print
         CSS below hides the toolbar, app chrome, and pagination, and
         reveals the print-only header above. The current filter +
         page (i.e. visible rows) get printed exactly as the user sees
         them — no separate "print all rows" mode. --}}
    <button type="button"
            onclick="window.print()"
            title="Print this view"
            aria-label="Print"
            class="w-9 h-9 inline-flex items-center justify-center border border-gray-300 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
    </button>
</form>

{{-- ── Results table ─────────────────────────────────────────────────── --}}
<div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between no-print">
        <span class="text-sm font-semibold text-gray-700">
            {{ number_format($logs->total()) }} {{ Str::plural('entry', $logs->total()) }}
            @if ($hasActiveFilters)
                <span class="text-xs font-normal text-amber-600 ml-1">(filtered)</span>
            @endif
        </span>
    </div>

    @if ($logs->isEmpty())
        <div class="px-5 py-16 text-center text-sm text-gray-400">No audit log entries found.</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">When</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Who</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Model</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($logs as $log)
                        @php
                            $actionColor = match ($log->action) {
                                'created'             => 'bg-green-100 text-green-700',
                                'updated'             => 'bg-blue-100 text-blue-700',
                                'deleted'             => 'bg-red-100 text-red-700',
                                'permissions_changed' => 'bg-purple-100 text-purple-700',
                                default               => 'bg-gray-100 text-gray-600',
                            };
                            $actionLabel = $log->action === 'permissions_changed' ? 'Permissions' : ucfirst($log->action);
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $log->created_at->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ $log->user?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge {{ $actionColor }}">{{ $actionLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $log->targetLabel() }} #{{ $log->target_id }}
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                @if ($log->action === 'permissions_changed')
                                    @php
                                        $before  = collect($log->before_json['permissions'] ?? []);
                                        $after   = collect($log->after_json['permissions']  ?? []);
                                        $granted = $after->diff($before)->values();
                                        $revoked = $before->diff($after)->values();
                                    @endphp
                                    @if ($granted->isNotEmpty())
                                        <div class="text-xs text-green-600 truncate">
                                            <span class="font-medium">+ Granted:</span> {{ $granted->implode(', ') }}
                                        </div>
                                    @endif
                                    @if ($revoked->isNotEmpty())
                                        <div class="text-xs text-red-500 truncate">
                                            <span class="font-medium">− Revoked:</span> {{ $revoked->implode(', ') }}
                                        </div>
                                    @endif
                                    @if ($granted->isEmpty() && $revoked->isEmpty())
                                        <span class="text-xs text-gray-400">No change</span>
                                    @endif
                                @elseif ($log->action === 'updated' && $log->before_json)
                                    @foreach ($log->after_json ?? [] as $field => $newVal)
                                        <div class="text-xs text-gray-500 truncate">
                                            <span class="font-medium text-gray-700">{{ $field }}:</span>
                                            <span class="line-through text-red-500">{{ is_array($log->before_json[$field] ?? null) ? '…' : ($log->before_json[$field] ?? '—') }}</span>
                                            →
                                            <span class="text-green-600">{{ is_array($newVal) ? '…' : $newVal }}</span>
                                        </div>
                                    @endforeach
                                @elseif ($log->action === 'created')
                                    <span class="text-xs text-gray-400">New record</span>
                                @elseif ($log->action === 'deleted')
                                    <span class="text-xs text-red-400">Record removed</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer — per-page selector + Showing X–Y of Z + page links.
             Per-page selector lives in its own tiny GET form so changing
             the dropdown reloads the page with the new per_page while
             preserving the existing filter query string. ─────────────── --}}
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 no-print">
            <form method="GET" action="{{ route('audit-logs.index') }}"
                  class="flex items-center gap-2 text-xs text-gray-600"
                  x-data x-on:change="$el.submit()">
                @foreach (['user_id', 'action', 'model', 'from', 'to'] as $k)
                    @if ($v = request($k))
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <label for="per_page" class="font-medium text-gray-500">Show</label>
                <select id="per_page" name="per_page"
                        class="px-2 py-1.5 text-sm border border-gray-300 rounded-lg bg-white
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 text-gray-700">
                    @foreach ([15, 25, 50, 100] as $opt)
                        <option value="{{ $opt }}" @selected((int) request('per_page', 25) === $opt)>{{ $opt }} per page</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="px-2 py-1 text-xs font-semibold border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50">Apply</button>
                </noscript>
                <span class="text-gray-400">·</span>
                <span class="text-gray-500">
                    Showing <strong class="text-gray-700">{{ $logs->firstItem() ?? 0 }}</strong>–<strong class="text-gray-700">{{ $logs->lastItem() ?? 0 }}</strong>
                    of <strong class="text-gray-700">{{ $logs->total() }}</strong>
                </span>
            </form>
            @if ($logs->hasPages())
                <div>{{ $logs->links() }}</div>
            @endif
        </div>
    @endif
</div>

{{-- ── Print CSS — hide the chrome, reveal the print-only header,
        keep table rows together across page breaks. Scoped to this
        view via @push so it doesn't leak to other pages. ────────────── --}}
@push('styles')
<style>
    .print-only { display: none; }

    @media print {
        /* Hide app chrome (sidebar, top header, footer) */
        aside.fixed,
        header.bg-white.border-b,
        header.sticky,
        footer,
        .no-print { display: none !important; }

        /* Show print-only header */
        .print-only { display: block !important; }

        /* Full-bleed main + drop card border so print doesn't waste space */
        body, html { background: white !important; }
        main { margin: 0 !important; padding: 0 !important; max-width: none !important; }

        /* Force colored badges to print rather than white-out */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Keep rows whole; let the table flow across pages */
        .bg-white.rounded-2xl { border: 1px solid #e5e7eb; box-shadow: none; }
        table { page-break-inside: auto; font-size: 10pt; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }

        /* A4 portrait, modest margins */
        @page { margin: 12mm; size: A4 portrait; }

        /* Print-only header style */
        .print-header {
            border-bottom: 2px solid #1b2b4b;
            padding-bottom: 8mm;
            margin-bottom: 6mm;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
        }
        .print-header .ph-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 8mm;
        }
        .print-header .ph-org strong { font-size: 13pt; display: block; color: #1b2b4b; }
        .print-header .ph-org .ph-meta { font-size: 10pt; color: #6b7280; }
        .print-header .ph-meta-block { font-size: 9pt; color: #6b7280; text-align: right; line-height: 1.4; }
        .print-header .ph-filters {
            margin-top: 5mm;
            font-size: 9pt;
            color: #4b5563;
        }
        .print-header .ph-filters strong { color: #1b2b4b; margin-right: 6px; }
        .print-header .ph-filters span {
            display: inline-block;
            margin-right: 12px;
            padding: 2px 6px;
            background: #f3f4f6;
            border-radius: 3px;
        }
    }
</style>
@endpush

@endsection
