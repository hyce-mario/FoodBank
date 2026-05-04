@extends('layouts.app')
@section('title', 'Finance — Reports')

@section('content')

{{-- ── Header ──────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance Reports</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.dashboard') }}" class="hover:text-brand-500">Finance</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Reports</span>
        </nav>
    </div>
</div>

@include('finance._nav')

{{-- ── Card grid ───────────────────────────────────────────────────── --}}
<p class="text-sm text-gray-500 mb-5">
    Board-ready financial reports. Each report supports Print, PDF, and CSV exports with the active period and filters preserved.
</p>

@php
    $categoryColors = [
        'Statements'  => 'bg-navy-50 text-navy-700 border-navy-100',
        'Detail'      => 'bg-indigo-50 text-indigo-700 border-indigo-100',
        'Analysis'    => 'bg-amber-50 text-amber-700 border-amber-100',
        'Compliance'  => 'bg-gray-50 text-gray-700 border-gray-200',
    ];
@endphp

{{-- Single-column row list. Each card is a horizontal banner — title +
     description on the left, category tag + status + export pills on
     the right. Reads top-to-bottom like a contents page, scannable at
     iPad portrait widths. --}}
<div class="space-y-3">
    @foreach ($reports as $r)
        @php
            $tagClass = $categoryColors[$r['category']] ?? 'bg-gray-50 text-gray-600 border-gray-200';
        @endphp

        @if ($r['live'])
            <a href="{{ route($r['route']) }}"
               class="block bg-white rounded-2xl border border-gray-200 hover:border-navy-700 hover:shadow-md transition-all px-5 py-4 group">
        @else
            <div class="block bg-white rounded-2xl border border-gray-200 px-5 py-4 opacity-70 cursor-not-allowed" aria-disabled="true">
        @endif

            <div class="flex flex-col md:flex-row md:items-center gap-3">
                {{-- Left: title + description ──────────────────────── --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <h3 class="text-base font-bold text-gray-900 {{ $r['live'] ? 'group-hover:text-navy-700' : '' }}">
                            {{ $r['title'] }}
                        </h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide border {{ $tagClass }}">
                            {{ $r['category'] }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 leading-relaxed">{{ $r['description'] }}</p>
                </div>

                {{-- Right: status + export pills ───────────────────── --}}
                <div class="flex items-center gap-3 md:flex-shrink-0">
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="font-semibold text-gray-400">Exports:</span>
                        <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 font-semibold">Print</span>
                        <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-700 font-semibold">PDF</span>
                        <span class="px-1.5 py-0.5 rounded bg-green-50 text-green-700 font-semibold">CSV</span>
                    </div>
                    @if ($r['live'])
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">
                            Live
                        </span>
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-navy-700" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">
                            Coming soon
                        </span>
                    @endif
                </div>
            </div>

        @if ($r['live'])
            </a>
        @else
            </div>
        @endif
    @endforeach
</div>

@endsection
