@extends('layouts.app')
@section('title', 'Edit Volunteer')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Edit Volunteer</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteers.index') }}" class="hover:text-brand-500 transition-colors">Volunteers</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteers.show', $volunteer) }}" class="hover:text-brand-500 transition-colors">{{ $volunteer->full_name }}</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">Edit</span>
        </nav>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('volunteers.show', $volunteer) }}"
           class="flex items-center gap-2 bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </div>
</div>

<form method="POST" action="{{ route('volunteers.update', $volunteer) }}">
    @csrf
    @method('PUT')
    @include('volunteers._form')
    <div class="flex items-center justify-end gap-3 mt-5 mx-1 sm:mx-4">
        <a href="{{ route('volunteers.show', $volunteer) }}"
           class="px-6 py-3 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl transition-colors">
            Cancel
        </a>
        <button type="submit"
                class="px-6 py-3 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
            Save Changes
        </button>
    </div>
</form>

@endsection
