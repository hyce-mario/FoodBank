@extends('layouts.app')
@section('title', 'Add Volunteer')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Add Volunteer</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteers.index') }}" class="hover:text-brand-500 transition-colors">Volunteers</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">Add Volunteer</span>
        </nav>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        <button type="button" onclick="window.location.reload()"
                class="w-9 h-9 flex items-center justify-center border border-gray-300 rounded-full text-gray-500 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
        </button>
        <a href="{{ route('volunteers.index') }}"
           class="flex items-center gap-2 bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </div>
</div>

<form method="POST" action="{{ route('volunteers.store') }}">
    @csrf
    @include('volunteers._form')
    <div class="flex items-center justify-end gap-3 mt-5 mx-1 sm:mx-4">
        <a href="{{ route('volunteers.index') }}"
           class="px-6 py-3 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl transition-colors">
            Cancel
        </a>
        <button type="submit"
                class="px-6 py-3 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
            Add Volunteer
        </button>
    </div>
</form>

@endsection
