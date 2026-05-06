@extends('layouts.app')
@section('title', 'Event Summary — ' . $event->name)

@section('content')
@php
    /*
     * Vertical-tab Event Summary report.
     *
     * Each section in $sections renders as a tab on the left rail. Active
     * tab state is in Alpine. Section partials live in events/summary/sections/_*.blade.php
     * and receive the section payload as $data plus shared $event, $branding.
     *
     * The export query is rebuilt with sections[]= so Print/PDF/Export
     * download exactly the slice the user is looking at.
     */
    $sectionMeta = [
        'event_details' => ['label' => 'Event Details', 'icon' => 'M21 7.5l-9-5.25L3 7.5m18 0v9l-9 5.25M21 7.5l-9 5.25m0 9V12.75m0 9L3 16.5v-9m9 5.25L3 7.5'],
        'attendees'     => ['label' => 'Attendees',     'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
        'volunteers'    => ['label' => 'Volunteers',    'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z'],
        'reviews'       => ['label' => 'Reviews',       'icon' => 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z'],
        'inventory'     => ['label' => 'Inventory',     'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z'],
        'finance'       => ['label' => 'Finance',       'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3-3.75h.008v.008H18V8.25Zm-12 0h.008v.008H6V8.25Z'],
        'queue'         => ['label' => 'Queue Summary', 'icon' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z'],
        'evaluation'    => ['label' => 'Evaluation',    'icon' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z'],
    ];
    $exportQuery = ['sections' => $sections];
@endphp

<div class="mb-5 flex items-start justify-between gap-3 flex-wrap">
    <div>
        <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Event Summary</div>
        <h1 class="text-2xl font-black text-gray-900">{{ $event->name }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ $event->date?->format('D, M j, Y') }}
            @if($event->location) · {{ $event->location }} @endif
            · {{ count($sections) }} {{ count($sections) === 1 ? 'section' : 'sections' }}
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('events.show', $event) }}"
           class="text-sm font-medium text-gray-600 hover:text-gray-900 px-3 py-2 rounded-lg hover:bg-gray-100">
            ← Back to event
        </a>
        <a href="{{ route('events.summary.print', array_merge(['event' => $event->id], $exportQuery)) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 rounded-lg px-3 py-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
            Print
        </a>
        <a href="{{ route('events.summary.pdf', array_merge(['event' => $event->id], $exportQuery)) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 rounded-lg px-3 py-2">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 1.5L18.5 9H13zM8 17v-1h8v1zm0-3v-1h8v1zm0-3V10h4v1z"/></svg>
            PDF
        </a>
        <a href="{{ route('events.summary.xlsx', array_merge(['event' => $event->id], $exportQuery)) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 rounded-lg px-3 py-2">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 1.5L18.5 9H13zm-3 8.5 2 3h-1.3l-1.2-2-1.2 2H8l2-3-2-3h1.3l1.2 2 1.2-2H13z"/></svg>
            Excel
        </a>
    </div>
</div>

<div x-data="{ active: '{{ $sections[0] ?? 'event_details' }}' }" class="space-y-5">

    {{-- Horizontal tab strip — scrollable on small screens. --}}
    <nav class="bg-white border border-gray-200 rounded-2xl p-1.5 flex gap-1 overflow-x-auto">
        @foreach ($sections as $section)
            @php $meta = $sectionMeta[$section] ?? ['label' => ucfirst($section), 'icon' => '']; @endphp
            <button type="button" @click="active = '{{ $section }}'"
                    :class="active === '{{ $section }}'
                        ? 'bg-navy-700 text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-100'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex-shrink-0 whitespace-nowrap">
                @if($meta['icon'])
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/>
                    </svg>
                @endif
                <span>{{ $meta['label'] }}</span>
            </button>
        @endforeach
    </nav>

    {{-- Content panels --}}
    <div class="min-w-0">
        @foreach ($sections as $section)
            <section x-show="active === '{{ $section }}'" x-cloak>
                @include('events.summary.sections._' . $section, [
                    'data'  => $data[$section] ?? null,
                    'event' => $event,
                ])
            </section>
        @endforeach
    </div>
</div>
@endsection
