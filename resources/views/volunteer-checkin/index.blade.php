{{--
    Phase 5.11 — Volunteer Check-In Kiosk Redesign

    Hybrid 4-screen flow optimized for live distribution events:
        welcome  → identify (phone search) → confirm card → success (auto-reset 3s)

    Production constraints baked in:
        • Bundle-frozen Tailwind: every class in this file is verified to
          exist in public/build/assets/app-*.css. Forbidden — and easy to
          re-introduce without realizing — are arbitrary values
          (text-[10px], min-w-[72px], rounded-[1.75rem]), gradient stops
          beyond the 50/100/600/700 navy and 50/100/200/500/600/700/800
          indigo shades in the bundle, pointer-events-auto, h-screen,
          rounded-3xl, max-w-xl, inset-x-0, pb-4. See
          docs/remediation/HANDOFF.md "Tailwind prebuilt CSS" carry-forward.
        • PII: response from /search no longer includes phone/email
          (Phase 5.6.e); this view treats the typed phone as transient
          input only and never echoes it back into the DOM.
        • Safety rails: Phase 5.6.j stale-open auto-close + min-gap rejection
          surface as friendly 422 messages — handled in toast() + screen
          transitions below, never silently swallowed.

    Sound feedback uses Web Audio (no asset payload). Muted preference
    persists in localStorage under 'vol_kiosk_muted'.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e3a8a">
    <title>Volunteer Check-In{{ $event ? ' — ' . $event->name : '' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
        }
        /* Hide scroll bar on iPad kiosk while still scrollable */
        .kiosk-scroll::-webkit-scrollbar { display: none; }
        .kiosk-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        /* Reduced-motion: cut all transitions for users who opt out */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 antialiased text-gray-900">

@if ($event)
{{-- ══════════════════════════════════════════════════════════════════════
     ACTIVE STATE — current event is running
══════════════════════════════════════════════════════════════════════ --}}
<div x-data="kioskApp()" x-cloak
     class="min-h-screen flex flex-col">

    {{-- ── Sticky top bar — solid navy-700 (gradient stops 800-950 missing
         from the prebuilt bundle, so a solid fill is the production-safe
         choice). Counter + mute toggle live here so they're always
         visible across screens. ───────────────────────────────────────── --}}
    <header class="sticky top-0 z-30 bg-navy-700 shadow-lg">
        <div class="max-w-lg mx-auto px-4 py-4 flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-widest text-navy-100">
                    Volunteer Check-In
                </p>
                <h1 class="text-lg font-extrabold text-white leading-tight truncate">
                    {{ $event->name }}
                </h1>
                <p class="text-xs text-navy-100 mt-0.5 font-medium">
                    {{ $event->date->format('l, F j, Y') }}
                </p>
            </div>

            {{-- Live count — bg-navy-600 sits on bg-navy-700 for depth
                 without needing transparency utilities. --}}
            <div class="flex-shrink-0 bg-navy-600 rounded-2xl px-4 py-2 text-center min-w-20">
                <p class="text-2xl font-extrabold text-white leading-none tabular-nums"
                   x-text="checkedInCount">{{ $checkedInCount }}</p>
                <p class="text-xs font-semibold text-navy-100 mt-1">In</p>
            </div>

            {{-- Mute toggle — bottom-most icon button, persisted in
                 localStorage. aria-pressed makes the state announceable. --}}
            <button type="button"
                    @click="toggleMute()"
                    :aria-pressed="muted ? 'true' : 'false'"
                    aria-label="Toggle sound"
                    class="flex-shrink-0 w-10 h-10 rounded-full bg-navy-600 hover:bg-navy-800
                           flex items-center justify-center text-white transition-colors">
                <svg x-show="!muted" class="w-5 h-5" fill="none" stroke="currentColor"
                     stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.506-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z"/>
                </svg>
                <svg x-show="muted" class="w-5 h-5" fill="none" stroke="currentColor"
                     stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.506-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z"/>
                </svg>
            </button>
        </div>
    </header>

    {{-- ════════════════════════════════════════════════════════════════════
         SCREEN STACK — only one rendered at a time. The state machine in
         kioskApp() drives transitions. All screens share max-w-lg + central
         layout for consistent kiosk dimensions on iPad portrait + laptop.
    ════════════════════════════════════════════════════════════════════ --}}
    <main class="flex-1 w-full max-w-lg mx-auto px-4 py-6 flex flex-col">

        {{-- ── SCREEN 1 — Welcome ──────────────────────────────────────── --}}
        <section x-show="screen === 'welcome'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="flex-1 flex flex-col"
                 aria-labelledby="welcomeHeading">
            <div class="text-center pt-4 pb-6">
                <div class="inline-flex w-20 h-20 rounded-2xl bg-indigo-50 items-center justify-center mb-5">
                    <svg class="w-12 h-12 text-indigo-600" fill="none" stroke="currentColor"
                         stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                </div>
                <h2 id="welcomeHeading" class="text-3xl font-extrabold text-gray-900">
                    Welcome!
                </h2>
                <p class="text-base text-gray-600 mt-2 leading-relaxed">
                    Please check in to begin your shift.
                </p>
            </div>

            {{-- Three big buttons. Each is min-h-16 (64px, exceeds the 48px
                 spec for gloved tapping). Distinct color per action so a
                 misfire is unambiguous. --}}
            <div class="space-y-4 mt-2">
                <button type="button"
                        @click="enterIdentify('checkin')"
                        class="w-full flex items-center gap-4 px-6 py-5 rounded-2xl
                               bg-green-600 hover:bg-green-700 active:scale-95
                               text-white text-left transition-transform duration-150
                               select-none focus:outline-none focus:ring-2 focus:ring-green-500"
                        aria-label="Check in to your shift">
                    <span class="flex-shrink-0 w-12 h-12 rounded-full bg-white flex items-center justify-center">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor"
                             stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-xl font-extrabold leading-tight">Check In</span>
                        <span class="block text-sm font-medium text-green-100 mt-0.5">Start your shift</span>
                    </span>
                </button>

                <button type="button"
                        @click="enterIdentify('checkout')"
                        class="w-full flex items-center gap-4 px-6 py-5 rounded-2xl
                               bg-amber-500 hover:bg-amber-600 active:scale-95
                               text-white text-left transition-transform duration-150
                               select-none focus:outline-none focus:ring-2 focus:ring-amber-400"
                        aria-label="Check out of your shift">
                    <span class="flex-shrink-0 w-12 h-12 rounded-full bg-white flex items-center justify-center">
                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor"
                             stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                        </svg>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-xl font-extrabold leading-tight">Check Out</span>
                        <span class="block text-sm font-medium text-amber-100 mt-0.5">End your shift</span>
                    </span>
                </button>

                <button type="button"
                        @click="enterIdentify('status')"
                        class="w-full flex items-center gap-4 px-6 py-5 rounded-2xl
                               bg-white hover:bg-gray-50 active:scale-95 border-2 border-gray-200
                               text-gray-900 text-left transition-transform duration-150
                               select-none focus:outline-none focus:ring-2 focus:ring-gray-400"
                        aria-label="View your current status">
                    <span class="flex-shrink-0 w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"/>
                        </svg>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-xl font-extrabold leading-tight">View My Status</span>
                        <span class="block text-sm font-medium text-gray-500 mt-0.5">See your check-in time and hours</span>
                    </span>
                </button>
            </div>
        </section>

        {{-- ── SCREEN 2 — Identify (phone search) ──────────────────────── --}}
        <section x-show="screen === 'identify'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="flex-1 flex flex-col"
                 aria-labelledby="identifyHeading">

            {{-- Back row + screen title --}}
            <div class="flex items-center gap-3 mb-4">
                <button type="button"
                        @click="goWelcome()"
                        class="flex-shrink-0 w-11 h-11 rounded-full bg-white hover:bg-gray-100
                               border border-gray-200 flex items-center justify-center
                               text-gray-600 transition-colors active:scale-95
                               focus:outline-none focus:ring-2 focus:ring-gray-400"
                        aria-label="Back to welcome">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                         stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <h2 id="identifyHeading" class="text-xl font-extrabold text-gray-900 leading-tight"
                        x-text="screenTitle()"></h2>
                    <p class="text-sm text-gray-500">Enter your phone number</p>
                </div>
            </div>

            {{-- Phone input. inputmode=tel triggers the numeric keypad on
                 iOS/Android. autocomplete=tel surfaces saved numbers. --}}
            <div class="relative">
                {{-- Bundle has pl-3 / pl-9 / pr-3 / pr-10 — pl-4, pl-11, pr-12
                     are NOT compiled. The icon overlay uses pl-3 (12px from
                     edge); the input reserves pl-9 (36px) to clear the
                     20px-wide icon plus a small gap. --}}
                <div class="pointer-events-none absolute top-0 bottom-0 left-0 pl-3 flex items-center">
                    <svg x-show="!loading" class="w-5 h-5 text-gray-400" fill="none"
                         stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <svg x-show="loading" class="w-5 h-5 text-indigo-600 animate-spin" fill="none"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                </div>
                <input x-ref="phoneInput"
                       type="tel"
                       x-model="query"
                       @input.debounce.350ms="doSearch()"
                       @keydown.escape="clearSearch()"
                       placeholder="Phone number"
                       inputmode="tel"
                       autocomplete="tel"
                       autocorrect="off"
                       spellcheck="false"
                       aria-label="Phone number"
                       class="w-full pl-9 pr-10 py-4 text-lg bg-white border-2 border-gray-200
                              rounded-2xl shadow-sm font-medium text-gray-900
                              focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-300
                              placeholder:text-gray-400">
                <button x-show="query.length > 0"
                        @click="clearSearch()"
                        type="button"
                        aria-label="Clear"
                        class="absolute top-0 bottom-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Search results / states --}}
            <div class="mt-5 flex-1">
                {{-- Idle prompt --}}
                <p x-show="!loading && !searched && query.length === 0"
                   class="text-center text-sm text-gray-500 py-8 leading-relaxed">
                    Tap the keypad to enter your phone.<br>
                    We'll find you in the volunteer list.
                </p>

                {{-- Empty result --}}
                <div x-show="!loading && searched && results.length === 0"
                     class="bg-white border-2 border-gray-200 rounded-2xl p-6 text-center">
                    <p class="text-base font-bold text-gray-800 mb-1">
                        No volunteer found for that number
                    </p>
                    <p class="text-sm text-gray-500 leading-relaxed mb-5"
                       x-show="action === 'checkin'">
                        New here? Sign up — takes 30 seconds.
                    </p>
                    <p class="text-sm text-gray-500 leading-relaxed mb-5"
                       x-show="action !== 'checkin'">
                        Double-check the number and try again.
                    </p>
                    <button x-show="action === 'checkin'"
                            type="button"
                            @click="openSheet()"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700
                                   active:scale-95 text-white text-sm font-bold rounded-xl
                                   transition-transform duration-150 select-none
                                   focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Sign Up as New Volunteer
                    </button>
                </div>

                {{-- Result row(s). Tap to advance to Confirm. --}}
                <div x-show="!loading && results.length > 0" class="space-y-3">
                    <template x-for="vol in results" :key="vol.id">
                        <button type="button"
                                @click="selectVolunteer(vol)"
                                class="w-full bg-white hover:bg-gray-50 active:scale-95 border-2 border-gray-200
                                       rounded-2xl p-4 text-left flex items-center gap-3
                                       transition-transform duration-150 select-none
                                       focus:outline-none focus:ring-2 focus:ring-indigo-300">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center
                                        text-white font-bold text-base shadow-sm"
                                 :style="`background:${avatarColor(vol.full_name)}`"
                                 aria-hidden="true">
                                <span x-text="initials(vol.full_name)"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-gray-900 truncate" x-text="vol.full_name"></p>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    <span x-show="vol.checked_in && !vol.checked_out" class="text-green-700 font-semibold">
                                        Checked in <span x-text="vol.checkin_time"></span>
                                    </span>
                                    <span x-show="!vol.checked_in">
                                        Tap to continue
                                    </span>
                                    <span x-show="vol.checked_out" class="text-gray-500">
                                        Checked out <span x-text="vol.checkout_time"></span>
                                    </span>
                                </p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none"
                                 stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                            </svg>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Always-visible "+ New Volunteer" — only when checkin path.
                 Sits below the result list rather than as a floating
                 element (no pointer-events-auto in the bundle). --}}
            <div x-show="action === 'checkin'" class="mt-6">
                <button type="button"
                        @click="openSheet()"
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-4
                               bg-indigo-600 hover:bg-indigo-700 active:scale-95
                               text-white text-base font-bold rounded-2xl
                               transition-transform duration-150 select-none
                               focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    New Volunteer
                </button>
            </div>
        </section>

        {{-- ── SCREEN 3 — Confirm card ─────────────────────────────────── --}}
        <section x-show="screen === 'confirm' && selected"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="flex-1 flex flex-col"
                 aria-labelledby="confirmHeading">

            <div class="flex items-center gap-3 mb-5">
                <button type="button"
                        @click="goIdentify()"
                        class="flex-shrink-0 w-11 h-11 rounded-full bg-white hover:bg-gray-100
                               border border-gray-200 flex items-center justify-center
                               text-gray-600 transition-colors active:scale-95
                               focus:outline-none focus:ring-2 focus:ring-gray-400"
                        aria-label="Back to search">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                </button>
                <h2 id="confirmHeading" class="text-xl font-extrabold text-gray-900"
                    x-text="confirmTitle()"></h2>
            </div>

            <template x-if="selected">
                <div class="bg-white border-2 border-gray-200 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-16 h-16 rounded-full flex items-center justify-center
                                    text-white font-bold text-xl shadow-sm"
                             :style="`background:${avatarColor(selected.full_name)}`"
                             aria-hidden="true">
                            <span x-text="initials(selected.full_name)"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xl font-extrabold text-gray-900 leading-tight" x-text="selected.full_name"></p>
                            <div x-show="selected.is_assigned" class="mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full
                                             bg-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wide">
                                    Pre-Assigned
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Team / group badges — id+name only per /search response --}}
                    <div x-show="selected.groups && selected.groups.length > 0"
                         class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Team</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="g in selected.groups" :key="g.id">
                                <span class="inline-flex items-center px-3 py-1 rounded-full
                                             bg-indigo-50 border border-indigo-100 text-indigo-700 text-sm font-semibold"
                                      x-text="g.name"></span>
                            </template>
                        </div>
                    </div>

                    {{-- Live status block — varies by action.
                         checkin: shows "already in" if applicable
                         checkout: shows current check-in time + live elapsed
                         status: same as checkout but read-only --}}
                    <div class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                        <template x-if="action === 'checkin' && selected.checked_in && !selected.checked_out">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm">
                                <p class="font-bold text-amber-800">Already checked in</p>
                                <p class="text-amber-700 mt-0.5">
                                    You checked in at <span class="font-bold" x-text="selected.checkin_time"></span>.
                                </p>
                            </div>
                        </template>

                        <template x-if="(action === 'checkout' || action === 'status') && selected.checked_in && !selected.checked_out">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 font-medium">Checked in</span>
                                    <span class="font-bold text-gray-900" x-text="selected.checkin_time"></span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 font-medium">On shift</span>
                                    <span class="font-bold text-green-700 tabular-nums" x-text="elapsedDisplay"></span>
                                </div>
                            </div>
                        </template>

                        <template x-if="(action === 'checkout' || action === 'status') && !selected.checked_in">
                            <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-600">
                                You're not currently checked in.
                            </div>
                        </template>

                        <template x-if="(action === 'checkout' || action === 'status') && selected.checked_out">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 font-medium">Last shift</span>
                                    <span class="font-bold text-gray-900">
                                        <span x-text="selected.checkin_time"></span>
                                        →
                                        <span x-text="selected.checkout_time"></span>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 font-medium">Hours served</span>
                                    <span class="font-bold text-gray-900 tabular-nums">
                                        <span x-text="selected.hours_served"></span> h
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Action button — text + handler vary by action+state --}}
                    <div class="mt-5">
                        {{-- CHECKIN — primary green button (or disabled if already in) --}}
                        <template x-if="action === 'checkin'">
                            <button type="button"
                                    @click="doCheckIn()"
                                    :disabled="busy || (selected.checked_in && !selected.checked_out)"
                                    class="w-full inline-flex items-center justify-center gap-2 px-6 py-4
                                           bg-green-600 hover:bg-green-700 active:scale-95 text-white text-lg font-extrabold
                                           rounded-2xl transition-transform duration-150 select-none
                                           focus:outline-none focus:ring-2 focus:ring-green-500
                                           disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="!busy" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                                <svg x-show="busy" class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                <span x-show="!busy">Confirm Check-In</span>
                                <span x-show="busy">Checking in…</span>
                            </button>
                        </template>

                        {{-- CHECKOUT — amber button --}}
                        <template x-if="action === 'checkout' && selected.checked_in && !selected.checked_out">
                            <button type="button"
                                    @click="doCheckOut()"
                                    :disabled="busy"
                                    class="w-full inline-flex items-center justify-center gap-2 px-6 py-4
                                           bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-lg font-extrabold
                                           rounded-2xl transition-transform duration-150 select-none
                                           focus:outline-none focus:ring-2 focus:ring-amber-400
                                           disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="!busy" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                                </svg>
                                <svg x-show="busy" class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                <span x-show="!busy">Confirm Check-Out</span>
                                <span x-show="busy">Checking out…</span>
                            </button>
                        </template>

                        {{-- STATUS — read-only Done button --}}
                        <template x-if="action === 'status'">
                            <button type="button"
                                    @click="goWelcome()"
                                    class="w-full inline-flex items-center justify-center gap-2 px-6 py-4
                                           bg-indigo-600 hover:bg-indigo-700 active:scale-95
                                           text-white text-lg font-extrabold rounded-2xl
                                           transition-transform duration-150 select-none
                                           focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                Done
                            </button>
                        </template>
                    </div>

                    {{-- Cancel — back to identify (not welcome) so the user
                         can quickly correct a mis-tap without redoing the
                         action selection. Hidden on status flow since the
                         primary button already says Done. --}}
                    <button x-show="action !== 'status'"
                            type="button"
                            @click="goIdentify()"
                            class="w-full mt-3 px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-800 transition-colors">
                        Cancel
                    </button>
                </div>
            </template>
        </section>

        {{-- ── SCREEN 4 — Success ──────────────────────────────────────── --}}
        <section x-show="screen === 'success'"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="flex-1 flex flex-col items-center justify-center text-center py-8"
                 role="status"
                 aria-live="polite">

            {{-- Big check mark in a circle --}}
            <div class="w-32 h-32 rounded-full bg-green-500 flex items-center justify-center shadow-lg mb-6"
                 :class="successMode === 'checkout' ? 'bg-amber-500' : 'bg-green-500'">
                <svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
            </div>

            <h2 class="text-3xl font-extrabold text-gray-900 mb-2" x-text="successHeadline"></h2>
            <p class="text-lg text-gray-600 mb-6" x-text="successName"></p>

            <div class="w-full max-w-sm bg-white border-2 border-gray-200 rounded-2xl p-5 space-y-3 text-left">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500" x-text="successMode === 'checkout' ? 'Checked out at' : 'Checked in at'"></span>
                    <span class="font-bold text-gray-900 tabular-nums" x-text="successTime"></span>
                </div>
                <div x-show="successMode === 'checkout'" class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500">Hours served</span>
                    <span class="font-bold text-gray-900 tabular-nums">
                        <span x-text="successHours"></span> h
                    </span>
                </div>
                <div x-show="successTeam" class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500">Team</span>
                    <span class="font-bold text-gray-900" x-text="successTeam"></span>
                </div>
                <div x-show="successFirstTimer" class="flex items-center gap-2 pt-2 border-t border-gray-100 text-amber-700">
                    <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <span class="text-sm font-bold">Welcome — first time volunteering!</span>
                </div>
            </div>

            <p class="text-sm text-gray-500 mt-6" aria-live="off">
                Returning to home in <span class="font-bold text-gray-700 tabular-nums" x-text="resetCountdown"></span>s
            </p>
        </section>

    </main>

    {{-- ────────────────────────────────────────────────────────────────────
         New Volunteer modal (full-screen overlay; bundle has bg-gray-900/50
         but not bg-black/50, and no pointer-events-auto so we use a
         straightforward inset-0 wrapper)
    ──────────────────────────────────────────────────────────────────── --}}
    <div x-show="sheetOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="sheetOpen = false"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-gray-900/50 px-4 py-6"
         role="dialog"
         aria-modal="true"
         aria-labelledby="newVolHeading"
         style="display:none">
        <div @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-4 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
                <h2 id="newVolHeading" class="text-xl font-extrabold text-gray-900">New Volunteer</h2>
                <button type="button"
                        @click="sheetOpen = false"
                        class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center
                               justify-center text-gray-500 transition-colors"
                        aria-label="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="newVolForm" @submit.prevent="submitNewVol()" class="px-6 py-6 space-y-4">
                <x-bot-defense />
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="newVolFirstName" class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input id="newVolFirstName" type="text" name="first_name"
                               required autocomplete="given-name"
                               class="w-full px-3 py-3 text-base bg-gray-50 border-2 border-gray-200
                                      rounded-xl font-medium
                                      focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-300 focus:bg-white">
                    </div>
                    <div>
                        <label for="newVolLastName" class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input id="newVolLastName" type="text" name="last_name"
                               required autocomplete="family-name"
                               class="w-full px-3 py-3 text-base bg-gray-50 border-2 border-gray-200
                                      rounded-xl font-medium
                                      focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-300 focus:bg-white">
                    </div>
                </div>
                <div>
                    <label for="newVolPhone" class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">
                        Phone <span class="text-red-500">*</span>
                    </label>
                    <input id="newVolPhone" type="tel" name="phone" autocomplete="tel"
                           required inputmode="tel"
                           class="w-full px-3 py-3 text-base bg-gray-50 border-2 border-gray-200
                                  rounded-xl font-medium
                                  focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-300 focus:bg-white">
                </div>
                <div>
                    <label for="newVolEmail" class="block text-xs font-bold text-gray-600 uppercase tracking-widest mb-1.5">
                        Email
                    </label>
                    <input id="newVolEmail" type="email" name="email" autocomplete="email"
                           class="w-full px-3 py-3 text-base bg-gray-50 border-2 border-gray-200
                                  rounded-xl font-medium
                                  focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-300 focus:bg-white">
                </div>

                <div x-show="formError"
                     class="bg-red-50 border-2 border-red-200 text-red-700 rounded-xl px-3 py-3 text-sm font-medium">
                    <span x-text="formError"></span>
                </div>

                <button type="submit"
                        :disabled="submitting"
                        class="w-full px-6 py-4 bg-indigo-600 hover:bg-indigo-700 active:scale-95
                               text-white text-base font-extrabold rounded-2xl
                               transition-transform duration-150 select-none
                               focus:outline-none focus:ring-2 focus:ring-indigo-300
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!submitting">Register & Check In</span>
                    <span x-show="submitting">Registering…</span>
                </button>
            </form>
        </div>
    </div>

    {{-- ── Toast stack ─────────────────────────────────────────────────── --}}
    <div class="fixed bottom-6 left-0 right-0 flex flex-col items-center gap-2 z-50 px-4"
         aria-hidden="true">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="flex items-center gap-3 bg-gray-900 text-white text-sm font-semibold
                        px-4 py-3 rounded-2xl shadow-xl max-w-sm w-full">
                <span x-show="toast.ok"
                      class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                </span>
                <span x-show="!toast.ok"
                      class="flex-shrink-0 w-6 h-6 rounded-full bg-red-500 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </span>
                <span x-text="toast.message" class="flex-1 leading-snug"></span>
            </div>
        </template>
    </div>

    {{-- aria-live for screen reader announcements (visually hidden) --}}
    <div role="status" aria-live="polite" class="sr-only" x-text="liveAnnounce"></div>

</div>{{-- /x-data kioskApp --}}

@else
{{-- ══════════════════════════════════════════════════════════════════════
     INACTIVE STATE — no current event running
══════════════════════════════════════════════════════════════════════ --}}
<div class="min-h-screen flex items-center justify-center bg-slate-100 px-4 py-12">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-8 py-16 text-center max-w-sm w-full">
        <div class="w-20 h-20 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
            <svg class="w-12 h-12 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
        </div>
        <h2 class="text-xl font-extrabold text-gray-800 mb-2">No Active Event Today</h2>
        <p class="text-sm text-gray-500 leading-relaxed">
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
    '#1e40af', '#7c3aed', '#0d9488',
    '#0284c7', '#65a30d', '#dc2626', '#ea580c',
];

function avatarColor(name) {
    let h = 5381;
    for (let i = 0; i < name.length; i++) h = ((h << 5) + h) + name.charCodeAt(i);
    return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length];
}

function initials(name) {
    return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

// ─── Sound (Web Audio) ───────────────────────────────────────────────────────
// Lazily-initialized AudioContext — Safari requires it be created on a user
// gesture or it throws on the first beep. We init on first play attempt.
let _audioCtx = null;
function getAudioCtx() {
    if (_audioCtx) return _audioCtx;
    try {
        _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    } catch { _audioCtx = null; }
    return _audioCtx;
}

function playTone(freq, durationMs, type = 'sine', startGain = 0.18) {
    const ctx = getAudioCtx();
    if (!ctx) return;
    const osc  = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type   = type;
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(startGain, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + durationMs / 1000);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + durationMs / 1000 + 0.02);
}

function playSuccessBeep() {
    playTone(800, 100);
    setTimeout(() => playTone(1100, 140), 90);
}

function playErrorBeep() {
    playTone(280, 220, 'sawtooth', 0.14);
}

// ─── Main component ──────────────────────────────────────────────────────────
function kioskApp() {
    return {
        // ── Routes (server-rendered) ─────────────────────────────────────
        _searchUrl:   '{{ route('volunteer-checkin.search') }}',
        _checkInUrl:  '{{ route('volunteer-checkin.checkin') }}',
        _checkOutUrl: '{{ route('volunteer-checkin.checkout') }}',
        _signUpUrl:   '{{ route('volunteer-checkin.signup') }}',
        _csrf:        document.querySelector('meta[name="csrf-token"]').content,

        // ── State machine ────────────────────────────────────────────────
        screen:    'welcome',          // welcome | identify | confirm | success
        action:    null,               // checkin | checkout | status
        query:     '',
        results:   [],
        selected:  null,
        loading:   false,
        searched:  false,
        busy:      false,

        // ── Modal state ──────────────────────────────────────────────────
        sheetOpen:  false,
        submitting: false,
        formError:  '',

        // ── Counters ─────────────────────────────────────────────────────
        checkedInCount: {{ $checkedInCount }},

        // ── Success screen payload ───────────────────────────────────────
        successMode:       null,       // 'checkin' | 'checkout'
        successHeadline:   '',
        successName:       '',
        successTime:       '',
        successHours:      '',
        successTeam:       '',
        successFirstTimer: false,
        resetCountdown:    3,
        _resetTimer:       null,
        _countdownTimer:   null,

        // ── Live elapsed clock for confirm screen (status / checkout) ────
        elapsedDisplay: '',
        _elapsedTimer:  null,

        // ── Sound preference ─────────────────────────────────────────────
        muted:      false,

        // ── Toasts ───────────────────────────────────────────────────────
        toasts:     [],
        _toastSeq:  0,

        // ── Search request abort ─────────────────────────────────────────
        _abortCtrl: null,

        // ── Accessibility ────────────────────────────────────────────────
        liveAnnounce: '',

        // ════════════════════════════════════════════════════════════════
        // INIT — restore mute pref, watch screen for focus management
        // ════════════════════════════════════════════════════════════════
        init() {
            try {
                this.muted = localStorage.getItem('vol_kiosk_muted') === '1';
            } catch { this.muted = false; }

            this.$watch('screen', (next) => this.onScreenEnter(next));
        },

        // ════════════════════════════════════════════════════════════════
        // SCREEN TRANSITIONS
        // ════════════════════════════════════════════════════════════════
        enterIdentify(action) {
            this.action   = action;
            this.query    = '';
            this.results  = [];
            this.searched = false;
            this.selected = null;
            this.screen   = 'identify';
            this.announce(`${this.screenTitle()} — enter your phone number.`);
        },

        goWelcome() {
            this.cancelReset();
            this.cancelElapsed();
            this.screen   = 'welcome';
            this.action   = null;
            this.query    = '';
            this.results  = [];
            this.searched = false;
            this.selected = null;
        },

        goIdentify() {
            this.cancelElapsed();
            this.selected = null;
            this.screen   = 'identify';
        },

        onScreenEnter(screen) {
            // Auto-focus the right element for keyboard / screen-reader users.
            // $nextTick gives Alpine time to mount the screen's DOM.
            this.$nextTick(() => {
                if (screen === 'identify' && this.$refs.phoneInput) {
                    this.$refs.phoneInput.focus();
                }
            });
        },

        screenTitle() {
            return {
                checkin:  'Check In',
                checkout: 'Check Out',
                status:   'My Status',
            }[this.action] ?? '';
        },

        confirmTitle() {
            return {
                checkin:  'Confirm Check-In',
                checkout: 'Confirm Check-Out',
                status:   'Your Status',
            }[this.action] ?? '';
        },

        // ════════════════════════════════════════════════════════════════
        // SEARCH
        // ════════════════════════════════════════════════════════════════
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
                if (e.name !== 'AbortError') {
                    this.results  = [];
                    this.searched = true;
                }
            } finally {
                this.loading = false;
            }
        },

        clearSearch() {
            this.query    = '';
            this.results  = [];
            this.searched = false;
            this.$nextTick(() => this.$refs.phoneInput?.focus());
        },

        // ════════════════════════════════════════════════════════════════
        // SELECT VOLUNTEER → CONFIRM SCREEN
        // ════════════════════════════════════════════════════════════════
        selectVolunteer(vol) {
            this.selected = JSON.parse(JSON.stringify(vol));
            this.screen   = 'confirm';
            this.startElapsedClock();
            this.announce(`${vol.full_name} selected.`);
        },

        // Live elapsed clock — recomputes "On shift" every second from
        // checked_in_at_iso. Cleared when leaving the confirm screen.
        startElapsedClock() {
            this.cancelElapsed();
            const tick = () => {
                if (!this.selected || !this.selected.checked_in_at_iso) {
                    this.elapsedDisplay = '';
                    return;
                }
                const start = Date.parse(this.selected.checked_in_at_iso);
                if (Number.isNaN(start)) { this.elapsedDisplay = ''; return; }
                const ms   = Date.now() - start;
                const totalMin = Math.max(0, Math.floor(ms / 60000));
                const h    = Math.floor(totalMin / 60);
                const m    = totalMin % 60;
                this.elapsedDisplay = h > 0
                    ? `${h}h ${String(m).padStart(2, '0')}m`
                    : `${m}m`;
            };
            tick();
            this._elapsedTimer = setInterval(tick, 1000);
        },

        cancelElapsed() {
            if (this._elapsedTimer) {
                clearInterval(this._elapsedTimer);
                this._elapsedTimer = null;
            }
            this.elapsedDisplay = '';
        },

        // ════════════════════════════════════════════════════════════════
        // CHECK-IN
        // ════════════════════════════════════════════════════════════════
        async doCheckIn() {
            if (!this.selected || this.busy) return;
            this.busy = true;
            try {
                const res  = await fetch(this._checkInUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  this._csrf,
                    },
                    body: JSON.stringify({ volunteer_id: this.selected.id }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.checkedInCount = data.checked_in_count;
                    this.showSuccess('checkin', {
                        name:        data.full_name,
                        time:        data.time,
                        firstTimer:  data.is_first_timer,
                        team:        this.primaryTeam(this.selected),
                    });
                } else {
                    this.toast(false, data.message ?? 'Check-in failed.');
                }
            } catch {
                this.toast(false, 'Network error — please try again.');
            } finally {
                this.busy = false;
            }
        },

        // ════════════════════════════════════════════════════════════════
        // CHECK-OUT
        // ════════════════════════════════════════════════════════════════
        async doCheckOut() {
            if (!this.selected || this.busy) return;
            this.busy = true;
            try {
                const res  = await fetch(this._checkOutUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': this._csrf,
                    },
                    body: JSON.stringify({ volunteer_id: this.selected.id }),
                });
                const data = await res.json();
                if (data.ok) {
                    this.showSuccess('checkout', {
                        name:  data.full_name,
                        time:  data.checkout_time,
                        hours: data.hours_served,
                        team:  this.primaryTeam(this.selected),
                    });
                } else {
                    this.toast(false, data.message ?? 'Check-out failed.');
                }
            } catch {
                this.toast(false, 'Network error — please try again.');
            } finally {
                this.busy = false;
            }
        },

        primaryTeam(vol) {
            if (!vol || !Array.isArray(vol.groups) || vol.groups.length === 0) return '';
            return vol.groups[0].name;
        },

        // ════════════════════════════════════════════════════════════════
        // SIGN UP (new volunteer modal submit)
        // ════════════════════════════════════════════════════════════════
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
                    this.showSuccess('checkin', {
                        name:       data.full_name,
                        time:       data.time,
                        firstTimer: data.is_first_timer && !data.is_existing,
                        team:       '',
                    });
                } else {
                    const errs = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message ?? 'Registration failed.');
                    this.formError = errs;
                    if (!this.muted) playErrorBeep();
                }
            } catch {
                this.formError = 'Network error — please try again.';
                if (!this.muted) playErrorBeep();
            } finally {
                this.submitting = false;
            }
        },

        openSheet() {
            this.sheetOpen = true;
            this.formError = '';
            this.$nextTick(() => {
                const phoneInput = document.querySelector('#newVolForm input[name="phone"]');
                if (phoneInput && this.query.trim()) phoneInput.value = this.query.trim();
                document.getElementById('newVolFirstName')?.focus();
            });
        },

        // ════════════════════════════════════════════════════════════════
        // SUCCESS SCREEN — show, beep, auto-reset after 3s
        // ════════════════════════════════════════════════════════════════
        showSuccess(mode, payload) {
            this.successMode       = mode;
            this.successHeadline   = mode === 'checkout' ? "You're Checked Out!" : "You're Checked In!";
            this.successName       = payload.name ?? '';
            this.successTime       = payload.time ?? '';
            this.successHours      = payload.hours ?? '';
            this.successTeam       = payload.team  ?? '';
            this.successFirstTimer = !!payload.firstTimer;
            this.screen            = 'success';
            this.announce(`${this.successHeadline} ${this.successName} at ${this.successTime}.`);

            if (!this.muted) playSuccessBeep();

            // Countdown ticker — visible to user, also drives the auto-reset
            this.cancelReset();
            this.resetCountdown = 3;
            this._countdownTimer = setInterval(() => {
                this.resetCountdown = Math.max(0, this.resetCountdown - 1);
            }, 1000);
            this._resetTimer = setTimeout(() => this.goWelcome(), 3000);
        },

        cancelReset() {
            if (this._resetTimer)     { clearTimeout(this._resetTimer);   this._resetTimer = null; }
            if (this._countdownTimer) { clearInterval(this._countdownTimer); this._countdownTimer = null; }
            this.resetCountdown = 3;
        },

        // ════════════════════════════════════════════════════════════════
        // SOUND + ACCESSIBILITY
        // ════════════════════════════════════════════════════════════════
        toggleMute() {
            this.muted = !this.muted;
            try { localStorage.setItem('vol_kiosk_muted', this.muted ? '1' : '0'); }
            catch { /* private browsing — non-fatal */ }
            this.announce(this.muted ? 'Sound off.' : 'Sound on.');
        },

        announce(message) {
            // aria-live regions read on update — nudge the screen reader
            // by clearing then setting (otherwise repeat messages silent)
            this.liveAnnounce = '';
            this.$nextTick(() => { this.liveAnnounce = message; });
        },

        // ════════════════════════════════════════════════════════════════
        // TOAST (used for inline errors that don't warrant a full screen)
        // ════════════════════════════════════════════════════════════════
        toast(ok, message) {
            const id = ++this._toastSeq;
            this.toasts.push({ id, ok, message, visible: true });
            if (!this.muted) (ok ? playSuccessBeep : playErrorBeep)();
            this.announce(message);
            setTimeout(() => {
                const t = this.toasts.find(t => t.id === id);
                if (t) t.visible = false;
                setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 250);
            }, 3500);
        },

        // ── Expose helpers to template ───────────────────────────────────
        avatarColor,
        initials,
    };
}
</script>
@endif

</body>
</html>
