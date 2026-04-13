@extends('layouts.guest')
@section('title', 'Sign In')

@section('content')
<div class="min-h-screen flex">

    {{-- ── Left panel (branding) — hidden on small screens ─── --}}
    <div class="hidden lg:flex lg:w-1/2 bg-navy-700 flex-col justify-between p-12 relative overflow-hidden">
        {{-- Background pattern --}}
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-72 h-72 bg-brand-500 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-brand-500 rounded-full translate-x-1/3 translate-y-1/3"></div>
        </div>

        <div class="relative z-10">
            {{-- Logo --}}
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand-500 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                </div>
                <span class="text-white font-bold text-xl tracking-tight">FoodBank</span>
            </div>
        </div>

        <div class="relative z-10">
            <h1 class="text-4xl font-bold text-white leading-tight mb-4">
                Feeding communities,<br>
                <span class="text-brand-400">one family at a time.</span>
            </h1>
            <p class="text-navy-100 text-lg leading-relaxed opacity-80">
                Manage distributions, volunteers, and inventory — all in one place.
            </p>
        </div>

        <div class="relative z-10 flex items-center gap-4">
            <div class="flex -space-x-2">
                @foreach(['J','M','S','A'] as $i)
                <div class="w-8 h-8 rounded-full bg-brand-500 border-2 border-navy-700 flex items-center justify-center text-white text-xs font-bold">{{ $i }}</div>
                @endforeach
            </div>
            <p class="text-sm text-white opacity-70">Trusted by 200+ food bank staff</p>
        </div>
    </div>

    {{-- ── Right panel (login form) ─────────────────────────── --}}
    <div class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">

            {{-- Mobile logo --}}
            <div class="flex justify-center mb-8 lg:hidden">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 bg-brand-500 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                        </svg>
                    </div>
                    <span class="text-navy-700 font-bold text-lg">FoodBank</span>
                </div>
            </div>

            <h2 class="text-2xl font-bold text-gray-900 mb-1">Welcome back</h2>
            <p class="text-gray-500 text-sm mb-8">Sign in to your account to continue</p>

            {{-- Session error --}}
            @if (session('error'))
                <div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700
                            rounded-xl px-4 py-3 text-sm">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Email address
                    </label>
                    <input id="email"
                           name="email"
                           type="email"
                           autocomplete="email"
                           required
                           value="{{ old('email') }}"
                           class="w-full px-4 py-3 rounded-xl border text-sm transition-colors
                                  @error('email') border-red-400 bg-red-50 @else border-gray-300 bg-white @enderror
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500
                                  placeholder:text-gray-400"
                           placeholder="admin@foodbank.local">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div x-data="{ show: false }">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Password
                    </label>
                    <div class="relative">
                        <input id="password"
                               name="password"
                               :type="show ? 'text' : 'password'"
                               autocomplete="current-password"
                               required
                               class="w-full px-4 py-3 pr-11 rounded-xl border text-sm transition-colors
                                      @error('password') border-red-400 bg-red-50 @else border-gray-300 bg-white @enderror
                                      focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500
                                      placeholder:text-gray-400"
                               placeholder="••••••••">
                        <button type="button"
                                @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            <svg x-show="show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember" value="1"
                               class="w-4 h-4 rounded border-gray-300 text-brand-500
                                      focus:ring-brand-500/30 accent-brand-500">
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 active:bg-brand-700
                               text-white font-semibold py-3 px-4 rounded-xl text-sm
                               transition-colors focus:outline-none focus:ring-2
                               focus:ring-brand-500/50 focus:ring-offset-2">
                    Sign in
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} FoodBank Management System
            </p>
        </div>
    </div>
</div>
@endsection
