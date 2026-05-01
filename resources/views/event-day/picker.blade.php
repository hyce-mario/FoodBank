<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ucfirst($role) }} Station — Choose Event</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
// All class strings must be static (no PHP-assembled strings) so Tailwind keeps them.
// Mirror the auth blade pattern exactly so visuals stay consistent across the flow.
$roleLabel = match($role) {
    'scanner' => 'Scanner / Queue',
    'loader'  => 'Loader',
    'exit'    => 'Exit',
    default   => 'Intake',
};
@endphp

<body class="min-h-full font-sans flex flex-col bg-gray-100">

{{-- ── Coloured role banner ──────────────────────────────────────────────────── --}}
@switch($role)
    @case('scanner')
        <div class="bg-purple-600 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-purple-200 mb-1">Station</p>
            <h1 class="text-3xl font-black text-white">Scanner / Queue</h1>
            <p class="text-sm text-purple-200 mt-1">Choose today's event to begin</p>
        </div>
        @break
    @case('loader')
        <div class="bg-orange-500 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-orange-100 mb-1">Station</p>
            <h1 class="text-3xl font-black text-white">Loader Station</h1>
            <p class="text-sm text-orange-100 mt-1">Choose today's event to begin</p>
        </div>
        @break
    @case('exit')
        <div class="bg-green-600 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-green-200 mb-1">Station</p>
            <h1 class="text-3xl font-black text-white">Exit Station</h1>
            <p class="text-sm text-green-200 mt-1">Choose today's event to begin</p>
        </div>
        @break
    @default
        <div class="bg-blue-600 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-blue-200 mb-1">Station</p>
            <h1 class="text-3xl font-black text-white">Intake Station</h1>
            <p class="text-sm text-blue-200 mt-1">Choose today's event to begin</p>
        </div>
@endswitch

{{-- ── Event picker ─────────────────────────────────────────────────────────── --}}
<div class="flex-1 flex items-start justify-center pt-8 px-5 pb-8">
    <div class="w-full max-w-md">

        @if ($events->isEmpty())
            {{-- Empty state — no current events --}}
            <div class="bg-white border-2 border-gray-200 rounded-2xl shadow-sm p-8 text-center">
                <div class="text-5xl mb-3">📅</div>
                <p class="text-lg font-bold text-gray-800 mb-2">No events running right now</p>
                <p class="text-sm text-gray-500">Check back when an event is scheduled to start. This page will update automatically.</p>
            </div>
        @else
            <p class="text-sm font-semibold text-gray-500 text-center mb-5">
                @if ($events->count() === 1)
                    Tap to continue
                @else
                    Tap an event to continue
                @endif
            </p>

            <div class="space-y-3">
                @foreach ($events as $event)
                    @switch($role)
                        @case('scanner')
                            <a href="{{ route('event-day.scanner', $event) }}"
                               class="block bg-white border-2 border-gray-200 rounded-2xl shadow-sm
                                      px-5 py-4 hover:border-purple-400 hover:shadow-md
                                      active:bg-purple-50 transition-all">
                                <p class="text-lg font-black text-gray-900">{{ $event->name }}</p>
                                <p class="text-sm text-gray-500 mt-0.5">{{ $event->date->format('l, F j, Y') }}</p>
                                @if ($event->location)
                                    <p class="text-xs text-gray-400 mt-1">📍 {{ $event->location }}</p>
                                @endif
                                <p class="text-xs uppercase tracking-widest font-semibold text-purple-600 mt-2">Tap to enter →</p>
                            </a>
                            @break
                        @case('loader')
                            <a href="{{ route('event-day.loader', $event) }}"
                               class="block bg-white border-2 border-gray-200 rounded-2xl shadow-sm
                                      px-5 py-4 hover:border-orange-400 hover:shadow-md
                                      active:bg-orange-50 transition-all">
                                <p class="text-lg font-black text-gray-900">{{ $event->name }}</p>
                                <p class="text-sm text-gray-500 mt-0.5">{{ $event->date->format('l, F j, Y') }}</p>
                                @if ($event->location)
                                    <p class="text-xs text-gray-400 mt-1">📍 {{ $event->location }}</p>
                                @endif
                                <p class="text-xs uppercase tracking-widest font-semibold text-orange-600 mt-2">Tap to enter →</p>
                            </a>
                            @break
                        @case('exit')
                            <a href="{{ route('event-day.exit', $event) }}"
                               class="block bg-white border-2 border-gray-200 rounded-2xl shadow-sm
                                      px-5 py-4 hover:border-green-400 hover:shadow-md
                                      active:bg-green-50 transition-all">
                                <p class="text-lg font-black text-gray-900">{{ $event->name }}</p>
                                <p class="text-sm text-gray-500 mt-0.5">{{ $event->date->format('l, F j, Y') }}</p>
                                @if ($event->location)
                                    <p class="text-xs text-gray-400 mt-1">📍 {{ $event->location }}</p>
                                @endif
                                <p class="text-xs uppercase tracking-widest font-semibold text-green-600 mt-2">Tap to enter →</p>
                            </a>
                            @break
                        @default
                            <a href="{{ route('event-day.intake', $event) }}"
                               class="block bg-white border-2 border-gray-200 rounded-2xl shadow-sm
                                      px-5 py-4 hover:border-blue-400 hover:shadow-md
                                      active:bg-blue-50 transition-all">
                                <p class="text-lg font-black text-gray-900">{{ $event->name }}</p>
                                <p class="text-sm text-gray-500 mt-0.5">{{ $event->date->format('l, F j, Y') }}</p>
                                @if ($event->location)
                                    <p class="text-xs text-gray-400 mt-1">📍 {{ $event->location }}</p>
                                @endif
                                <p class="text-xs uppercase tracking-widest font-semibold text-blue-600 mt-2">Tap to enter →</p>
                            </a>
                    @endswitch
                @endforeach
            </div>
        @endif

    </div>
</div>

</body>
</html>
