<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'FoodBank') }} &mdash; @yield('title', 'Event Day')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-full bg-gray-100 font-sans flex flex-col">

{{-- Each page provides its own coloured header via @section('header') --}}
@yield('header')

<main class="flex-1 overflow-y-auto">
    @yield('content')
</main>

<footer class="shrink-0 px-4 py-3 border-t border-gray-200 bg-white">
    @yield('footer-action')
    <form method="POST"
          action="{{ route('event-day.' . ($role ?? 'intake') . '.logout', $event->id) }}">
        @csrf
        <button type="submit"
                class="w-full py-3 rounded-xl text-sm font-semibold text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors">
            Log out of this station
        </button>
    </form>
</footer>

@stack('scripts')
<script>
(function () {
    function pad(n) { return n < 10 ? '0' + n : n; }
    function tick() {
        const d = new Date();
        let h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        const el = document.getElementById('ed-clock');
        if (el) el.textContent = h + ':' + pad(m) + ' ' + ap;
    }
    tick();
    setInterval(tick, 15000);
})();
</script>
</body>
</html>
