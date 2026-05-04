<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'FoodBank') }} &mdash; @yield('title', 'Event Registration')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans relative overflow-x-hidden">

{{-- Decorative background shape --}}
<div class="fixed inset-0 pointer-events-none z-0" aria-hidden="true">
    <div class="absolute bottom-0 left-0 right-0 h-[55%]"
         style="background-color: #FDF0E7; clip-path: polygon(0 18%, 100% 0%, 100% 100%, 0% 100%);"></div>
</div>

{{-- Page wrapper --}}
<div class="relative z-10 min-h-screen flex flex-col">

    {{-- Header --}}
    <header class="w-full px-6 py-5">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            {{-- Logo --}}
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                </div>
                <div>
                    <span class="text-navy-700 font-bold text-base tracking-tight">FoodBank</span>
                    <span class="hidden sm:block text-[10px] text-gray-400 font-medium -mt-0.5 tracking-wide uppercase">Management</span>
                </div>
            </div>

            {{-- Login button --}}
            <a href="{{ route('login') }}"
               class="px-5 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
                Login
            </a>
        </div>
    </header>

    {{-- Main content --}}
    <main class="flex-1 w-full px-6 py-6">
        <div class="max-w-3xl mx-auto">
            @yield('content')
        </div>
    </main>

    {{-- Footer --}}
    <footer class="w-full px-6 py-5 text-center">
        <p class="text-xs text-gray-400">Copyrights &copy; {{ date('Y') }} - Food Bank</p>
    </footer>

</div>

@stack('scripts')
</body>
</html>
