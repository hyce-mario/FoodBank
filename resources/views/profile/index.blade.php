@extends('layouts.app')
@section('title', 'My Profile')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">My Profile</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Profile</span>
        </nav>
    </div>
</div>

{{-- Flash --}}
@if (session('success'))
<div class="mb-5 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- ── Layout ───────────────────────────────────────────────────────────── --}}
<div x-data="{ tab: '{{ session('open_tab', 'info') }}' }" class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ── Left: Identity card ──────────────────────────────────────────── --}}
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">

            {{-- Avatar --}}
            <div class="relative inline-block mb-4">
                <div class="w-20 h-20 rounded-full bg-brand-500 flex items-center justify-center text-white text-2xl font-bold mx-auto select-none">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                {{-- Online dot --}}
                <span class="absolute bottom-1 right-1 w-4 h-4 bg-green-400 border-2 border-white rounded-full"></span>
            </div>

            <h2 class="text-base font-bold text-gray-900">{{ $user->name }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ $user->email }}</p>

            @if ($user->role)
            <span class="inline-flex items-center gap-1.5 mt-3 px-3 py-1 rounded-full text-xs font-semibold
                         bg-navy-50 text-navy-700 border border-navy-100">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                </svg>
                {{ $user->role->display_name }}
            </span>
            @endif

            <div class="mt-5 pt-5 border-t border-gray-100 space-y-2.5 text-left">
                <div class="flex items-center gap-2.5 text-sm text-gray-500">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                    <span>Joined {{ $user->created_at->format('M j, Y') }}</span>
                </div>
                @if ($user->email_verified_at)
                <div class="flex items-center gap-2.5 text-sm text-green-600">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <span>Email verified</span>
                </div>
                @else
                <div class="flex items-center gap-2.5 text-sm text-amber-600">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                    </svg>
                    <span>Email not verified</span>
                </div>
                @endif
            </div>

            {{-- Permissions list --}}
            @if ($user->role && $user->role->permissions->count())
            <div class="mt-5 pt-5 border-t border-gray-100 text-left">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Permissions</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($user->role->permissions->sortBy('permission') as $perm)
                        @if ($perm->permission === '*')
                            <span class="px-2 py-0.5 rounded-md text-xs font-semibold bg-navy-700 text-white">Full Access</span>
                            @break
                        @else
                            <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-600">
                                {{ $perm->permission }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Right: Tab panels ────────────────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Tab Switcher --}}
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <nav class="flex border-b border-gray-100">
                <button @click="tab = 'info'"
                        :class="tab === 'info'
                            ? 'border-b-2 border-brand-500 text-brand-600 bg-brand-50/50'
                            : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'"
                        class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                    Account Info
                </button>
                <button @click="tab = 'password'"
                        :class="tab === 'password'
                            ? 'border-b-2 border-brand-500 text-brand-600 bg-brand-50/50'
                            : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'"
                        class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                    Change Password
                </button>
            </nav>

            {{-- ── Account Info Panel ────────────────────────────────────── --}}
            <div x-show="tab === 'info'" x-cloak class="p-6">
                <form method="POST" action="{{ route('profile.info') }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-5">
                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                   value="{{ old('name', $user->name) }}"
                                   required maxlength="255"
                                   class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                          @error('name') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                            @error('name')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="email" name="email"
                                   value="{{ old('email', $user->email) }}"
                                   required maxlength="255"
                                   class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-gray-50
                                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                          @error('email') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                            @error('email')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Role (read-only) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
                            <div class="flex items-center gap-2 px-3.5 py-2.5 text-sm border border-gray-200
                                        rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed select-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                                </svg>
                                {{ $user->role?->display_name ?? 'No role assigned' }}
                                <span class="ml-auto text-xs text-gray-400">Managed by admin</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6 pt-5 border-t border-gray-100">
                        <button type="submit"
                                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
                                       font-semibold text-sm rounded-lg px-5 py-2.5 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            {{-- ── Change Password Panel ─────────────────────────────────── --}}
            <div x-show="tab === 'password'" x-cloak class="p-6"
                 x-data="{ showCurrent: false, showNew: false, showConfirm: false }">

                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-5">
                        {{-- Current password --}}
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Current Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input :type="showCurrent ? 'text' : 'password'"
                                       id="current_password" name="current_password"
                                       required autocomplete="current-password"
                                       class="w-full px-3.5 py-2.5 pr-10 text-sm border rounded-lg bg-gray-50
                                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                              @error('current_password') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                                <button type="button" @click="showCurrent = !showCurrent"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg x-show="!showCurrent" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                    <svg x-show="showCurrent" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                    </svg>
                                </button>
                            </div>
                            @error('current_password')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- New password --}}
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                                New Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input :type="showNew ? 'text' : 'password'"
                                       id="password" name="password"
                                       required autocomplete="new-password"
                                       class="w-full px-3.5 py-2.5 pr-10 text-sm border rounded-lg bg-gray-50
                                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                              @error('password') border-red-400 bg-red-50 @else border-gray-200 @enderror">
                                <button type="button" @click="showNew = !showNew"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg x-show="!showNew" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                    <svg x-show="showNew" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Minimum 8 characters with uppercase, lowercase, and a number.</p>
                            @error('password')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Confirm password --}}
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Confirm New Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input :type="showConfirm ? 'text' : 'password'"
                                       id="password_confirmation" name="password_confirmation"
                                       required autocomplete="new-password"
                                       class="w-full px-3.5 py-2.5 pr-10 text-sm border border-gray-200 rounded-lg bg-gray-50
                                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                                <button type="button" @click="showConfirm = !showConfirm"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                    <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6 pt-5 border-t border-gray-100">
                        <button type="submit"
                                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
                                       font-semibold text-sm rounded-lg px-5 py-2.5 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                            </svg>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>

        </div>{{-- /tab card --}}

    </div>{{-- /right col --}}

</div>{{-- /grid --}}

@endsection

@push('scripts')
<script>
    // Re-open password tab if there were validation errors on that form
    @if ($errors->has('current_password') || $errors->has('password'))
        document.addEventListener('alpine:init', () => {
            Alpine.store && true; // ensure Alpine is ready
        });
        // The x-data tab default already reads session('open_tab')
    @endif
</script>
@endpush
