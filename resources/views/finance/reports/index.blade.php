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
        'Analysis'    => 'bg-emerald-50 text-emerald-600 border-green-100',
        'Compliance'  => 'bg-amber-50 text-amber-700 border-amber-100',
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
    @foreach ($reports as $r)
        @php
            $tagClass = $categoryColors[$r['category']] ?? 'bg-gray-50 text-gray-600 border-gray-200';
        @endphp

        @if ($r['live'])
            <a href="{{ route($r['route']) }}"
               class="block bg-white rounded-2xl border border-gray-200 hover:border-navy-700 hover:shadow-md transition-all p-5 group">
        @else
            <div class="block bg-white rounded-2xl border border-gray-200 p-5 opacity-75 cursor-not-allowed" aria-disabled="true">
        @endif

            <div class="flex items-start justify-between mb-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide border {{ $tagClass }}">
                    {{ $r['category'] }}
                </span>
                @if ($r['live'])
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">
                        Live
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">
                        Coming soon
                    </span>
                @endif
            </div>

            <h3 class="text-base font-bold text-gray-900 mb-1.5
                       {{ $r['live'] ? 'group-hover:text-navy-700' : '' }}">
                {{ $r['title'] }}
            </h3>
            <p class="text-sm text-gray-500 leading-relaxed mb-4">{{ $r['description'] }}</p>

            <div class="flex items-center gap-1.5 pt-3 border-t border-gray-100">
                <span class="text-xs font-semibold text-gray-400">Exports:</span>
                <span class="text-xs font-semibold text-gray-600">Print</span>
                <span class="text-gray-300">·</span>
                <span class="text-xs font-semibold text-gray-600">PDF</span>
                <span class="text-gray-300">·</span>
                <span class="text-xs font-semibold text-gray-600">CSV</span>
            </div>

        @if ($r['live'])
            </a>
        @else
            </div>
        @endif
    @endforeach
</div>

@endsection
