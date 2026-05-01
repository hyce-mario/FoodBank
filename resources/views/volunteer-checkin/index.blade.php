<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Volunteer Check-In{{ $event ? ' — ' . $event->name : '' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        body       { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 antialiased">

@if ($event)
{{-- ══════════════════════════════════════════════════════════════════════
     ACTIVE STATE — current event is running
══════════════════════════════════════════════════════════════════════ --}}
<div x-data="volunteerCheckIn()" x-cloak class="min-h-screen flex flex-col">

    {{-- ── Sticky Header ────────────────────────────────────────────────── --}}
    <div class="sticky top-0 z-30 bg-gradient-to-br from-indigo-950 via-indigo-900 to-indigo-800 shadow-xl">
        <div class="max-w-lg mx-auto px-4 pt-safe pt-5 pb-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-indigo-400 mb-1">
                        Volunteer Check-In
                    </p>
                    <h1 class="text-xl font-extrabold text-white leading-tight truncate">
                        {{ $event->name }}
                    </h1>
                    <p class="text-sm text-indigo-300/80 mt-0.5 font-medium">
                        {{ $event->date->format('l, F j, Y') }}
                    </p>
                </div>
                {{-- Live counter --}}
                <div class="flex-shrink-0">
                    <div class="bg-white/10 border border-white/20 backdrop-blur rounded-2xl px-4 pt-2.5 pb-2 text-center min-w-[72px]">
                        <p class="text-3xl font-extrabold text-white leading-none tabular-nums"
                           x-text="checkedInCount">{{ $checkedInCount }}</p>
                        <p class="text-[10px] font-semibold text-indigo-300 tracking-wide mt-0.5">Checked In</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Search bar (fused to header bottom) --}}
        <div class="bg-slate-100 rounded-t-[1.75rem] pt-4 pb-0">
            <div class="max-w-lg mx-auto px-4">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-4 flex items-center">
                        <svg x-show="!loading" class="w-5 h-5 text-gray-400"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                        <svg x-show="loading" class="w-5 h-5 text-indigo-500 animate-spin"
                             fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                    </div>
                    <input type="text"
                           x-model="query"
                           @input.debounce.350ms="doSearch()"
                           @keydown.escape="clearSearch()"
                           placeholder="Search name, phone, or email…"
                           autocomplete="off" autocorrect="off" spellcheck="false"
                           class="w-full pl-11 pr-10 py-3.5 text-[15px] bg-white border-0 rounded-2xl shadow-sm
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400/30
                                  placeholder:text-gray-400 text-gray-900 font-medium">
                    <button x-show="query.length > 0" @click="clearSearch()"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Scrollable content ───────────────────────────────────────────── --}}
    <div class="flex-1 max-w-lg mx-auto w-full px-4 pt-4 pb-36">

        {{-- Skeleton loaders --}}
        <div x-show="loading" class="space-y-3">
            <template x-for="i in 3" :key="i">
                <div class="bg-white rounded-2xl p-4 shadow-sm overflow-hidden relative">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-gray-200 flex-shrink-0 animate-pulse"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-gray-200 rounded-full animate-pulse" :style="`width:${40+i*15}%`"></div>
                            <div class="h-3 bg-gray-100 rounded-full animate-pulse" :style="`width:${25+i*8}%`"></div>
                        </div>
                        <div class="w-24 h-10 rounded-xl bg-gray-100 flex-shrink-0 animate-pulse"></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- No results --}}
        <div x-show="!loading && searched && results.length === 0"
             class="bg-white rounded-2xl shadow-sm p-8 text-center">
            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"/>
                </svg>
            </div>
            <p class="text-sm font-bold text-gray-700 mb-1">
                No match for "<span x-text="query" class="text-indigo-600"></span>"
            </p>
            <p class="text-xs text-gray-400 leading-relaxed mb-5">
                Not in the system yet? Sign up as a new volunteer below.
            </p>
            <button @click="sheetOpen = true"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700
                           active:scale-95 text-white text-sm font-bold rounded-xl transition-all
                           shadow-lg shadow-indigo-500/25 select-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/>
                </svg>
                Sign Up as New Volunteer
            </button>
        </div>

        {{-- Idle / welcome state --}}
        <div x-show="!loading && !searched && query.length === 0"
             class="text-center py-8">
            <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-indigo-50 rounded-3xl
                        flex items-center justify-center mx-auto mb-4 shadow-inner">
                <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
            </div>
            <p class="text-base font-bold text-gray-700">Welcome! Ready to help?</p>
            <p class="text-sm text-gray-400 mt-1.5 leading-relaxed">
                Search your name above to check in,<br>
                or tap <span class="font-semibold text-indigo-500">New Volunteer</span> if it's your first time.
            </p>
        </div>

        {{-- Volunteer result cards --}}
        <div x-show="!loading && results.length > 0" class="space-y-3">
            <template x-for="vol in results" :key="vol.id">
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden"
                     :class="vol.checked_in ? 'border border-green-200 bg-green-50' : 'border border-gray-100'">
                    <div class="flex items-center gap-3 px-4 py-4">

                        {{-- Avatar --}}
                        <div class="flex-shrink-0">
                            <template x-if="!vol.checked_in">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center
                                            text-white font-bold text-[15px] shadow-sm select-none"
                                     :style="'background:' + avatarColor(vol.full_name)">
                                    <span x-text="initials(vol.full_name)"></span>
                                </div>
                            </template>
                            <template x-if="vol.checked_in">
                                <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center shadow-sm">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                         stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                    </svg>
                                </div>
                            </template>
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-bold text-gray-900 text-[15px] leading-tight"
                                      x-text="vol.full_name"></span>
                                <span x-show="vol.is_assigned && !vol.checked_in"
                                      class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 uppercase tracking-wide">
                                    Assigned
                                </span>
                                <span x-show="vol.checked_in && vol.is_first_timer"
                                      class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">
                                    ★ First Timer
                                </span>
                            </div>
                            <div class="mt-0.5">
                                <span x-show="vol.checked_in"
                                      class="text-xs font-semibold text-green-600"
                                      x-text="'✓ Checked in at ' + (vol.checkin_time || '')"></span>
                                <span x-show="!vol.checked_in && vol.phone"
                                      class="text-xs text-gray-400" x-text="vol.phone"></span>
                                <span x-show="!vol.checked_in && !vol.phone && vol.email"
                                      class="text-xs text-gray-400 truncate block" x-text="vol.email"></span>
                            </div>
                        </div>

                        {{-- Check In button --}}
                        <div class="flex-shrink-0">
                            <template x-if="!vol.checked_in">
                                <button @click="checkIn(vol.id)"
                                        :disabled="checkingIn === vol.id"
                                        class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-sm
                                               bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white
                                               transition-all select-none disabled:opacity-60 disabled:cursor-not-allowed">
                                    <template x-if="checkingIn !== vol.id">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                        </svg>
                                    </template>
                                    <template x-if="checkingIn === vol.id">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                        </svg>
                                    </template>
                                    <span x-text="checkingIn === vol.id ? 'Checking…' : 'Check In'"></span>
                                </button>
                            </template>
                            <template x-if="vol.checked_in && !vol.checked_out">
                                <button @click="checkOut(vol.id)"
                                        :disabled="checkingOut === vol.id"
                                        class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-sm
                                               bg-gray-100 hover:bg-red-50 hover:text-red-600 text-gray-500 border border-gray-200
                                               transition-all select-none disabled:opacity-60 disabled:cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                                    </svg>
                                    <span x-text="checkingOut === vol.id ? 'Saving…' : 'Check Out'"></span>
                                </button>
                            </template>
                            <template x-if="vol.checked_out">
                                <div class="flex flex-col items-end gap-0.5">
                                    <span class="text-xs font-semibold text-gray-400" x-text="'Out ' + vol.checkout_time"></span>
                                    <span class="text-xs text-gray-400" x-text="vol.hours_served + 'h served'"></span>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>
            </template>
        </div>

    </div>

    {{-- ── Floating "New Volunteer" button ────────────────────────────── --}}
    <div class="fixed bottom-6 inset-x-0 flex justify-center z-20 pointer-events-none">
        <button @click="sheetOpen = true"
                class="pointer-events-auto inline-flex items-center gap-2.5 px-7 py-4
                       bg-indigo-600 hover:bg-indigo-700 active:scale-95
                       text-white text-sm font-bold rounded-full
                       shadow-2xl shadow-indigo-600/40 transition-all duration-150 select-none">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/>
            </svg>
            New Volunteer
        </button>
    </div>

    {{-- ── Toast stack ──────────────────────────────────────────────────── --}}
    <div class="fixed bottom-24 inset-x-0 flex flex-col items-center gap-2 z-50 pointer-events-none px-4">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                 class="flex items-center gap-3 bg-gray-900 text-white text-sm font-semibold
                        px-4 py-3.5 rounded-2xl shadow-2xl max-w-sm w-full pointer-events-auto">
                <span x-show="toast.ok"
                      class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                </span>
                <span x-show="!toast.ok"
                      class="flex-shrink-0 w-6 h-6 rounded-full bg-red-500 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </span>
                <span x-text="toast.message" class="flex-1 leading-snug"></span>
            </div>
        </template>
    </div>

    {{-- ── New Volunteer Bottom Sheet ───────────────────────────────────── --}}
    <div x-show="sheetOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-250"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="sheetOpen = false"
         class="fixed inset-0 bg-black/50 z-40 flex items-end"
         style="display:none">

        <div x-show="sheetOpen"
             x-transition:enter="transition ease-out duration-350"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-250"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="w-full bg-white rounded-t-3xl shadow-2xl max-h-[92vh] overflow-y-auto">

            {{-- Drag handle --}}
            <div class="flex justify-center pt-3 pb-2">
                <div class="w-10 h-1.5 bg-gray-300 rounded-full"></div>
            </div>

            <div class="px-6 pt-1 pb-10">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h2 class="text-xl font-extrabold text-gray-900">New Volunteer</h2>
                        <p class="text-sm text-gray-500 mt-0.5">First time here? We'll get you set up!</p>
                    </div>
                    <button @click="sheetOpen = false"
                            class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center
                                   justify-center text-gray-500 transition-colors flex-shrink-0 ml-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form id="newVolForm" @submit.prevent="submitNewVol" class="space-y-4">

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="first_name"
                                   required autocomplete="given-name"
                                   class="w-full px-3.5 py-3.5 text-[15px] border border-gray-200 rounded-xl bg-gray-50
                                          focus:outline-none focus:ring-2 focus:ring-indigo-400/30 focus:border-indigo-400 focus:bg-white
                                          transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="last_name"
                                   required autocomplete="family-name"
                                   class="w-full px-3.5 py-3.5 text-[15px] border border-gray-200 rounded-xl bg-gray-50
                                          focus:outline-none focus:ring-2 focus:ring-indigo-400/30 focus:border-indigo-400 focus:bg-white
                                          transition-all font-medium">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">
                            Phone
                        </label>
                        <input type="tel" name="phone" autocomplete="tel"
                               class="w-full px-3.5 py-3.5 text-[15px] border border-gray-200 rounded-xl bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400/30 focus:border-indigo-400 focus:bg-white
                                      transition-all font-medium">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">
                            Email
                        </label>
                        <input type="email" name="email" autocomplete="email"
                               class="w-full px-3.5 py-3.5 text-[15px] border border-gray-200 rounded-xl bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400/30 focus:border-indigo-400 focus:bg-white
                                      transition-all font-medium">
                    </div>

                    <div x-show="formError"
                         class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700
                                rounded-xl px-3.5 py-3 text-sm font-medium">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <span x-text="formError"></span>
                    </div>

                    <div class="pt-1">
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full py-4 text-base font-extrabold rounded-2xl transition-all duration-150
                                       shadow-lg shadow-indigo-500/25 select-none
                                       disabled:opacity-60 disabled:cursor-not-allowed
                                       bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] text-white">
                            <span x-show="!submitting">Register &amp; Check In</span>
                            <span x-show="submitting" class="flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                Registering…
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>{{-- /x-data volunteerCheckIn --}}

@else
{{-- ══════════════════════════════════════════════════════════════════════
     INACTIVE STATE — no current event running
══════════════════════════════════════════════════════════════════════ --}}
<div class="min-h-screen flex items-center justify-center bg-slate-100 px-4 py-12">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 px-8 py-16 text-center max-w-sm w-full">
        <div class="w-20 h-20 bg-indigo-50 rounded-3xl flex items-center justify-center mx-auto mb-5 shadow-inner">
            <svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
        </div>
        <h2 class="text-xl font-extrabold text-gray-800 mb-2">No Active Event Today</h2>
        <p class="text-sm text-gray-400 leading-relaxed">
            Volunteer check-in opens automatically<br>
            when an event begins. See you then!
        </p>
    </div>
</div>
@endif

@if ($event)
<script>
// ─── Helpers ─────────────────────────────────────────────────────────────────
const AVATAR_COLORS = [
    '#6366f1','#8b5cf6','#ec4899','#f97316',
    '#14b8a6','#0ea5e9','#84cc16','#e11d48',
];

function avatarColor(name) {
    let h = 5381;
    for (let i = 0; i < name.length; i++) h = ((h << 5) + h) + name.charCodeAt(i);
    return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length];
}

function initials(name) {
    return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

// ─── Main component ───────────────────────────────────────────────────────────
function volunteerCheckIn() {
    return {
        checkedInCount: {{ $checkedInCount }},
        query:      '',
        results:    [],
        loading:    false,
        searched:   false,
        checkingIn:  null,  // volunteer id currently being checked in
        checkingOut: null,  // volunteer id currently being checked out
        sheetOpen:  false,
        submitting: false,
        formError:  '',
        toasts:     [],
        _toastSeq:  0,
        _abortCtrl: null,
        _csrf:        document.querySelector('meta[name="csrf-token"]').content,
        _searchUrl:   '{{ route('volunteer-checkin.search') }}',
        _checkInUrl:  '{{ route('volunteer-checkin.checkin') }}',
        _checkOutUrl: '{{ route('volunteer-checkin.checkout') }}',
        _signUpUrl:   '{{ route('volunteer-checkin.signup') }}',

        // ── Search ────────────────────────────────────────────────────────
        async doSearch() {
            const q = this.query.trim();
            if (q.length < 2) { this.results = []; this.searched = false; return; }

            if (this._abortCtrl) this._abortCtrl.abort();
            this._abortCtrl = new AbortController();

            this.loading = true;
            try {
                const res  = await fetch(this._searchUrl + '?q=' + encodeURIComponent(q), {
                    signal:  this._abortCtrl.signal,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.results  = data.results ?? [];
                this.searched = true;
            } catch (e) {
                if (e.name !== 'AbortError') { this.results = []; this.searched = true; }
            } finally {
                this.loading = false;
            }
        },

        clearSearch() {
            this.query    = '';
            this.results  = [];
            this.searched = false;
        },

        // ── Check-in existing volunteer ───────────────────────────────────
        async checkIn(volunteerId) {
            const vol = this.results.find(v => v.id === volunteerId);
            if (!vol || vol.checked_in || this.checkingIn === volunteerId) return;

            this.checkingIn = volunteerId;
            try {
                const res  = await fetch(this._checkInUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  this._csrf,
                    },
                    body: JSON.stringify({ volunteer_id: volunteerId }),
                });
                const data = await res.json();
                if (data.ok) {
                    vol.checked_in     = true;
                    vol.checkin_time   = data.time;
                    vol.is_first_timer = data.is_first_timer;
                    this.checkedInCount = data.checked_in_count;
                    const extra = data.is_first_timer ? ' 🌟 First timer!' : '';
                    this.toast(true, `${data.full_name} checked in at ${data.time}.${extra}`);
                } else {
                    this.toast(false, data.message ?? 'Check-in failed.');
                }
            } catch {
                this.toast(false, 'Network error — please try again.');
            } finally {
                this.checkingIn = null;
            }
        },

        // ── Check-out existing volunteer ──────────────────────────────────
        async checkOut(volunteerId) {
            const vol = this.results.find(v => v.id === volunteerId);
            if (!vol || !vol.checked_in || vol.checked_out || this.checkingOut === volunteerId) return;

            this.checkingOut = volunteerId;
            try {
                const res  = await fetch(this._checkOutUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': this._csrf,
                    },
                    body: JSON.stringify({ volunteer_id: volunteerId }),
                });
                const data = await res.json();
                if (data.ok) {
                    vol.checked_out   = true;
                    vol.checkout_time = data.checkout_time;
                    vol.hours_served  = data.hours_served;
                    this.toast(true, `${data.full_name} checked out at ${data.checkout_time} (${data.hours_served}h).`);
                } else {
                    this.toast(false, data.message ?? 'Check-out failed.');
                }
            } catch {
                this.toast(false, 'Network error — please try again.');
            } finally {
                this.checkingOut = null;
            }
        },

        // ── New volunteer sign-up ─────────────────────────────────────────
        async submitNewVol() {
            const form = document.getElementById('newVolForm');
            const fd   = new FormData(form);
            this.formError  = '';
            this.submitting = true;

            try {
                const res  = await fetch(this._signUpUrl, {
                    method:  'POST',
                    headers: {
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': this._csrf,
                    },
                    body: fd,
                });
                const data = await res.json();
                if (data.ok) {
                    this.checkedInCount = data.checked_in_count;
                    this.sheetOpen = false;
                    form.reset();
                    this.toast(true, `Welcome ${data.full_name}! 🌟 Checked in at ${data.time}.`);
                } else {
                    const errs = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message ?? 'Registration failed.');
                    this.formError = errs;
                }
            } catch {
                this.formError = 'Network error — please try again.';
            } finally {
                this.submitting = false;
            }
        },

        // ── Toast helper ──────────────────────────────────────────────────
        toast(ok, message) {
            const id = ++this._toastSeq;
            this.toasts.push({ id, ok, message, visible: true });
            setTimeout(() => {
                const t = this.toasts.find(t => t.id === id);
                if (t) t.visible = false;
                setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 300);
            }, 3500);
        },

        // ── Expose helpers to template ────────────────────────────────────
        avatarColor,
        initials,
    };
}
</script>
@endif

</body>
</html>
