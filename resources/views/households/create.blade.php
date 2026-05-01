@extends('layouts.app')
@section('title', 'Create Household')

@section('content')

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Create Household</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('households.index') }}" class="hover:text-brand-500 transition-colors">Households</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">Create Household</span>
        </nav>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        {{-- Refresh --}}
        <button type="button" onclick="window.location.reload()"
                class="w-9 h-9 flex items-center justify-center border border-gray-300 rounded-full
                       text-gray-500 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
            </svg>
        </button>
        {{-- Scroll to top --}}
        <button type="button" onclick="window.scrollTo({top:0,behavior:'smooth'})"
                class="w-9 h-9 flex items-center justify-center border border-gray-300 rounded-full
                       text-gray-500 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
            </svg>
        </button>
        {{-- Back --}}
        <a href="{{ route('households.index') }}"
           class="flex items-center gap-2 bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold
                  rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
            Back to Households
        </a>
    </div>
</div>

@php $potentialDuplicates = session('potential_duplicates'); @endphp

{{-- ── Phase 6.5.c: duplicate-detection warning panel ─────────────────── --}}
@if ($potentialDuplicates && $potentialDuplicates->isNotEmpty())
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-5 mx-1 sm:mx-4">
    <div class="flex items-start gap-3 mb-3">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <div class="flex-1">
            <h2 class="text-sm font-bold text-amber-900">Potential duplicate{{ $potentialDuplicates->count() > 1 ? 's' : '' }} found</h2>
            <p class="text-xs text-amber-800 mt-1">
                {{ $potentialDuplicates->count() }} existing household{{ $potentialDuplicates->count() > 1 ? 's' : '' }} matching name, email, phone, or sound-alike spelling. Verify before creating a new record to avoid duplicate entries.
            </p>
        </div>
    </div>

    <div class="space-y-2">
        @foreach ($potentialDuplicates as $dup)
        <div class="bg-white border border-amber-200 rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-bold text-gray-900">{{ $dup->first_name }} {{ $dup->last_name }}</span>
                    <span class="text-xs font-mono text-gray-400">#{{ $dup->household_number }}</span>
                </div>
                <div class="text-xs text-gray-500 mt-0.5 space-x-3">
                    @if ($dup->email)<span>{{ $dup->email }}</span>@endif
                    @if ($dup->phone)<span>{{ $dup->phone }}</span>@endif
                    @if ($dup->zip)<span>ZIP {{ $dup->zip }}</span>@endif
                </div>
            </div>
            <a href="{{ route('households.show', $dup) }}" target="_blank"
               class="text-xs font-semibold text-amber-700 hover:text-amber-800 underline">
                View existing →
            </a>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Form ─────────────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('households.store') }}">
    @csrf

    @include('households._form')

    {{-- Actions --}}
    <div class="flex items-center justify-end gap-3 mt-5 mx-1 sm:mx-4">
        <a href="{{ route('households.index') }}"
           class="px-6 py-3 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl transition-colors">
            Cancel
        </a>
        @if ($potentialDuplicates && $potentialDuplicates->isNotEmpty())
            <button type="submit" name="force_create" value="1"
                    class="px-6 py-3 text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white rounded-xl transition-colors">
                Create Anyway
            </button>
        @else
            <button type="submit"
                    class="px-6 py-3 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
                Add Household
            </button>
        @endif
    </div>
</form>

@endsection
