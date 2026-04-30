<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Station Access — {{ $event->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
// All class strings must be static (no PHP-assembled strings) so Tailwind keeps them
$roleLabel = match($role) {
    'scanner' => 'Scanner / Queue',
    'loader'  => 'Loader',
    'exit'    => 'Exit',
    default   => 'Intake',
};
@endphp

<body class="min-h-full font-sans flex flex-col" x-data="{ code: '{{ old('code','') }}' }">

{{-- ── Coloured role banner ──────────────────────────────────────────────────── --}}
@switch($role)
    @case('scanner')
        <div class="bg-purple-600 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-purple-200 mb-1">{{ $event->name }}</p>
            <h1 class="text-3xl font-black text-white">Scanner / Queue</h1>
            <p class="text-sm text-purple-200 mt-1">{{ $event->date->format('l, F j, Y') }}</p>
        </div>
        @break
    @case('loader')
        <div class="bg-orange-500 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-orange-100 mb-1">{{ $event->name }}</p>
            <h1 class="text-3xl font-black text-white">Loader Station</h1>
            <p class="text-sm text-orange-100 mt-1">{{ $event->date->format('l, F j, Y') }}</p>
        </div>
        @break
    @case('exit')
        <div class="bg-green-600 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-green-200 mb-1">{{ $event->name }}</p>
            <h1 class="text-3xl font-black text-white">Exit Station</h1>
            <p class="text-sm text-green-200 mt-1">{{ $event->date->format('l, F j, Y') }}</p>
        </div>
        @break
    @default
        <div class="bg-blue-600 px-5 py-7 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-blue-200 mb-1">{{ $event->name }}</p>
            <h1 class="text-3xl font-black text-white">Intake Station</h1>
            <p class="text-sm text-blue-200 mt-1">{{ $event->date->format('l, F j, Y') }}</p>
        </div>
@endswitch

{{-- ── PIN entry card ───────────────────────────────────────────────────────── --}}
<div class="flex-1 flex items-start justify-center pt-8 px-5 pb-6 bg-gray-100">
    <div class="w-full max-w-xs">

        <p class="text-sm font-semibold text-gray-500 text-center mb-5">Enter the 4-digit access code</p>

        <form method="POST"
              action="{{ route('event-day.' . $role . '.auth', $event->id) }}">
            @csrf

            {{-- PIN boxes --}}
            @php
                $activePinClass = match($role) {
                    'scanner' => 'border-purple-500 ring-2 ring-purple-200',
                    'loader'  => 'border-orange-500 ring-2 ring-orange-200',
                    'exit'    => 'border-green-500 ring-2 ring-green-200',
                    default   => 'border-blue-500 ring-2 ring-blue-200',
                };
            @endphp
            <div class="flex gap-3 justify-center mb-5" @click="$refs.pin.focus()">
                @for ($i = 0; $i < 4; $i++)
                <div class="w-16 rounded-2xl bg-white border-2 shadow-sm
                            flex items-center justify-center text-4xl font-black py-4
                            transition-all duration-150"
                     :class="code.length > {{ $i }}
                        ? 'border-gray-400 text-gray-900'
                        : (code.length === {{ $i }} ? '{{ $activePinClass }}' : 'border-gray-200 text-gray-300')">
                    <span x-text="code[{{ $i }}] ? '●' : (code.length === {{ $i }} ? '|' : '·')"></span>
                </div>
                @endfor
            </div>

            {{-- Hidden real input --}}
            <input x-ref="pin" type="text" inputmode="numeric" pattern="[0-9]*"
                   maxlength="4" name="code" x-model="code"
                   @input="code = code.replace(/\D/g,'').slice(0,4)"
                   class="sr-only" autocomplete="off" autofocus>

            @error('code')
                <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5 text-center">
                    <p class="text-red-700 text-sm font-semibold">{{ $message }}</p>
                </div>
            @enderror

            {{-- Number pad --}}
            <div class="grid grid-cols-3 gap-3 mb-4">
                @foreach (['1','2','3','4','5','6','7','8','9','','0','⌫'] as $key)
                    @if ($key === '')
                        <div></div>
                    @elseif ($key === '⌫')
                        <button type="button" @click="code = code.slice(0,-1)"
                                class="h-16 rounded-2xl bg-white border-2 border-gray-200
                                       text-2xl text-gray-500 font-semibold
                                       hover:bg-gray-50 hover:border-gray-300
                                       active:bg-gray-100 transition-colors shadow-sm">⌫</button>
                    @else
                        <button type="button"
                                @click="if(code.length < 4) code += '{{ $key }}'"
                                class="h-16 rounded-2xl bg-white border-2 border-gray-200
                                       text-2xl font-black text-gray-800
                                       hover:bg-gray-50 hover:border-gray-300
                                       active:bg-gray-100 transition-colors shadow-sm">{{ $key }}</button>
                    @endif
                @endforeach
            </div>

            {{-- Submit button — each role gets its own fully-static class string --}}
            @switch($role)
                @case('scanner')
                    <button type="submit" :disabled="code.length < 4"
                            class="w-full py-4 rounded-2xl bg-purple-600 hover:bg-purple-700
                                   text-white font-black text-xl tracking-wide
                                   transition-colors disabled:opacity-30 disabled:cursor-not-allowed shadow-sm">
                        Enter
                    </button>
                    @break
                @case('loader')
                    <button type="submit" :disabled="code.length < 4"
                            class="w-full py-4 rounded-2xl bg-orange-500 hover:bg-orange-600
                                   text-white font-black text-xl tracking-wide
                                   transition-colors disabled:opacity-30 disabled:cursor-not-allowed shadow-sm">
                        Enter
                    </button>
                    @break
                @case('exit')
                    <button type="submit" :disabled="code.length < 4"
                            class="w-full py-4 rounded-2xl bg-green-600 hover:bg-green-700
                                   text-white font-black text-xl tracking-wide
                                   transition-colors disabled:opacity-30 disabled:cursor-not-allowed shadow-sm">
                        Enter
                    </button>
                    @break
                @default
                    <button type="submit" :disabled="code.length < 4"
                            class="w-full py-4 rounded-2xl bg-blue-600 hover:bg-blue-700
                                   text-white font-black text-xl tracking-wide
                                   transition-colors disabled:opacity-30 disabled:cursor-not-allowed shadow-sm">
                        Enter
                    </button>
            @endswitch

        </form>
    </div>
</div>

</body>
</html>
