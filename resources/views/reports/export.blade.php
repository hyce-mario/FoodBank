@extends('layouts.app')
@section('title', 'Reports — Exports')

@section('content')

<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Export report data as CSV</p>
    </div>
</div>

@include('reports._nav')
@include('reports._filter', ['formAction' => route('reports.export')])

{{-- ═══ Export Cards ═══════════════════════════════════════════════════ --}}
@php
$exports = [
    [
        'type'    => 'events',
        'title'   => 'Event Summary',
        'desc'    => 'One row per event with households served, bags distributed, completion rate, average service time, and rating.',
        'cols'    => 'Event, Date, Location, Lanes, Visits, Households, People, Bags, Complete %, Avg Time, Rating, Reviews',
        'color'   => 'navy',
        'icon'    => 'calendar',
    ],
    [
        'type'    => 'visits',
        'title'   => 'Visit Log',
        'desc'    => 'Detailed row per visit with household info, timestamps, stage durations, and bag count.',
        'cols'    => 'Visit ID, Event, Date, Lane, Status, Household #, Name, People, ZIP, City, Timestamps, Stage Durations',
        'color'   => 'blue',
        'icon'    => 'log',
    ],
    [
        'type'    => 'households',
        'title'   => 'Household Service Export',
        'desc'    => 'One row per household served in the period with contact info, family composition, visit count, and bags received.',
        'cols'    => 'Household #, Name, Email, Phone, City, State, ZIP, People, Children, Adults, Seniors, Visits in Period, Bags Received, First/Last Visit',
        'color'   => 'brand',
        'icon'    => 'home',
    ],
    [
        'type'    => 'demographics',
        'title'   => 'ZIP / Demographic Export',
        'desc'    => 'Households served grouped by ZIP code for geographic reporting.',
        'cols'    => 'ZIP Code, Households Served',
        'color'   => 'purple',
        'icon'    => 'map',
    ],
    [
        'type'    => 'reviews',
        'title'   => 'Review Export',
        'desc'    => 'All reviews for events in the selected period with ratings, text, and reviewer info.',
        'cols'    => 'Event, Event Date, Rating, Review Text, Reviewer, Email, Submitted',
        'color'   => 'amber',
        'icon'    => 'star',
    ],
    [
        'type'    => 'volunteers',
        'title'   => 'Volunteer Participation Export',
        'desc'    => 'All volunteers with their role, groups, and hours served in the period.',
        'cols'    => 'Name, Role, Groups, Events in Period, Hours in Period, Total Events, Total Hours, First/Last Service, Status',
        'color'   => 'green',
        'icon'    => 'volunteer',
    ],
    [
        'type'    => 'inventory',
        'title'   => 'Inventory Usage Export',
        'desc'    => 'Per-event item-level allocation, distribution, and remaining quantity for events in the period.',
        'cols'    => 'Event Date, Event, Status, Category, Item, Unit, Allocated, Distributed, Returned, Remaining',
        'color'   => 'blue',
        'icon'    => 'inventory',
    ],
    [
        'type'    => 'first-timers',
        'title'   => 'First-Timers Export',
        'desc'    => 'Households whose first event falls within the period — useful for new-household outreach and onboarding reporting.',
        'cols'    => 'Household #, First/Last Name, Phone, Email, City, ZIP, First Event Date, First Event, Events Attended, Represented',
        'color'   => 'amber',
        'icon'    => 'first-timer',
    ],
];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
    @foreach($exports as $exp)
    @php
    $url = route('reports.download', array_merge(
        request()->only(['preset', 'date_from', 'date_to']),
        ['type' => $exp['type']]
    ));

    $colorBg = match($exp['color']) {
        'navy'   => 'bg-navy-700',
        'blue'   => 'bg-blue-600',
        'brand'  => 'bg-brand-500',
        'purple' => 'bg-purple-600',
        'amber'  => 'bg-amber-500',
        'green'  => 'bg-green-600',
        default  => 'bg-gray-600',
    };
    @endphp

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm flex flex-col overflow-hidden">
        {{-- Card header --}}
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl {{ $colorBg }} flex items-center justify-center flex-shrink-0">
                @if($exp['icon'] === 'calendar')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                @elseif($exp['icon'] === 'log')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                    </svg>
                @elseif($exp['icon'] === 'home')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                @elseif($exp['icon'] === 'map')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                    </svg>
                @elseif($exp['icon'] === 'star')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
                    </svg>
                @elseif($exp['icon'] === 'volunteer')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                @elseif($exp['icon'] === 'inventory')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                @elseif($exp['icon'] === 'first-timer')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                @endif
            </div>
            <div>
                <h3 class="text-sm font-bold text-gray-900">{{ $exp['title'] }}</h3>
            </div>
        </div>

        {{-- Card body --}}
        <div class="px-5 py-4 flex-1">
            <p class="text-sm text-gray-600 leading-relaxed mb-3">{{ $exp['desc'] }}</p>
            <p class="text-xs text-gray-400">
                <span class="font-semibold text-gray-500">Columns:</span> {{ $exp['cols'] }}
            </p>
        </div>

        {{-- Card footer --}}
        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50">
            <div class="flex items-center gap-3">
                <a href="{{ $url }}"
                   class="inline-flex items-center gap-2 px-4 py-2 {{ $colorBg }} text-white text-sm font-semibold rounded-xl hover:opacity-90 transition-opacity">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Download CSV
                </a>
                <span class="text-xs text-gray-400">
                    {{ $filter['from']->format('M j') }} – {{ $filter['to']->format('M j, Y') }}
                </span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Note about Excel --}}
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl px-5 py-4">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-blue-800">CSV Format</p>
            <p class="text-xs text-blue-600 mt-0.5">
                All exports are in CSV format, compatible with Excel, Google Sheets, and other spreadsheet tools.
                Open in Excel using <em>Data → From Text/CSV</em> for best results with UTF-8 encoding.
            </p>
        </div>
    </div>
</div>

@endsection
