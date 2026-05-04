@extends('layouts.public')
@section('title', 'Upcoming Events')

@section('content')

@if ($events->isEmpty())
    <div class="text-center py-20">
        <svg class="w-14 h-14 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
        </svg>
        <p class="text-base font-semibold text-gray-500">No upcoming events at this time.</p>
        <p class="text-sm text-gray-400 mt-1">Please check back later.</p>
    </div>
@else
    <div class="space-y-4">
        @foreach ($events as $event)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-3">{{ $event->name }}</h2>

                <div class="space-y-2 mb-6">
                    <div class="flex items-center gap-2.5 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                        {{ $event->date->format('D, M j, Y') }}
                    </div>
                    @if ($event->location)
                        <div class="flex items-center gap-2.5 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                            </svg>
                            {{ $event->location }}
                        </div>
                    @endif
                </div>

                <a href="{{ route('public.register', $event) }}"
                   class="block w-full py-3 text-center text-sm font-semibold text-white bg-brand-500 hover:bg-brand-600 rounded-xl transition-colors">
                    Register
                </a>
            </div>
        @endforeach
    </div>
@endif

@endsection
