@extends('layouts.public')
@section('title', 'Registration Successful')

@section('content')

<div class="mb-6">
    <a href="{{ route('public.events') }}"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back to Events
    </a>
</div>

@php $alreadyRegistered = session('already_registered'); $existingNumber = session('attendee_number'); @endphp

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-8 py-14 text-center max-w-md mx-auto">
    @if ($alreadyRegistered)
        {{-- Amber info circle for "already registered" --}}
        <div class="w-16 h-16 rounded-full bg-amber-500 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">You're Already Registered</h1>
        <p class="text-sm text-gray-500 mb-3">Our records show you've already pre-registered for this event.</p>
        @if ($existingNumber)
            <p class="text-xs text-gray-400 mb-8">Reference #{{ $existingNumber }}</p>
        @endif
    @else
        {{-- Green checkmark circle --}}
        <div class="w-16 h-16 rounded-full bg-green-500 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Success</h1>
        <p class="text-sm text-gray-500 mb-8">You have successfully registered for this event</p>
    @endif

    <a href="{{ route('public.events') }}"
       class="block w-full py-3 text-sm font-semibold text-white bg-brand-500 hover:bg-brand-600 rounded-xl transition-colors">
        Back to Events
    </a>
</div>

@endsection
