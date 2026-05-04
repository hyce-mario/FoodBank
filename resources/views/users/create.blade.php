@extends('layouts.app')
@section('title', 'New User')

@section('content')
<div>

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">New User</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('users.index') }}" class="hover:text-brand-500">Users</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">New User</span>
        </nav>
    </div>
    <a href="{{ route('users.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900
              border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back
    </a>
</div>

<form method="POST" action="{{ route('users.store') }}">
@csrf

<div class="max-w-2xl space-y-5">

    {{-- ── Account Info ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Account Information</h2>

        <div class="space-y-4">
            {{-- Name --}}
            <div>
                <label for="name" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" id="name"
                       value="{{ old('name') }}"
                       placeholder="e.g. Jane Smith"
                       autocomplete="name"
                       class="w-full px-3 py-2.5 text-sm border rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              @error('name') border-red-400 bg-red-50 @else border-gray-200 bg-gray-50 @enderror">
                @error('name')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Email Address <span class="text-red-500">*</span>
                </label>
                <input type="email" name="email" id="email"
                       value="{{ old('email') }}"
                       placeholder="jane@example.com"
                       autocomplete="email"
                       class="w-full px-3 py-2.5 text-sm border rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              @error('email') border-red-400 bg-red-50 @else border-gray-200 bg-gray-50 @enderror">
                @error('email')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Role --}}
            <div>
                <label for="role_id" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Role <span class="text-red-500">*</span>
                </label>
                <select name="role_id" id="role_id"
                        class="w-full px-3 py-2.5 text-sm border rounded-lg cursor-pointer
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                               @error('role_id') border-red-400 bg-red-50 @else border-gray-200 bg-gray-50 @enderror">
                    <option value="">Select a role…</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>
                            {{ $role->display_name }}
                            @if ($role->description) — {{ $role->description }} @endif
                        </option>
                    @endforeach
                </select>
                @error('role_id')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- ── Password ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-1">Password</h2>
        <p class="text-xs text-gray-500 mb-4">Minimum 8 characters.</p>

        <div class="space-y-4">
            <div>
                <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" id="password"
                       autocomplete="new-password"
                       class="w-full px-3 py-2.5 text-sm border rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              @error('password') border-red-400 bg-red-50 @else border-gray-200 bg-gray-50 @enderror">
                @error('password')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Confirm Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                       autocomplete="new-password"
                       class="w-full px-3 py-2.5 text-sm border border-gray-200 bg-gray-50 rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
            </div>
        </div>
    </div>

    {{-- ── Submit ───────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <button type="submit"
                class="flex-1 sm:flex-none sm:min-w-[140px] bg-brand-500 hover:bg-brand-600 text-white
                       font-semibold text-sm rounded-lg px-5 py-3 transition-colors text-center">
            Create User
        </button>
        <a href="{{ route('users.index') }}"
           class="flex-1 sm:flex-none sm:min-w-[100px] flex items-center justify-center text-sm font-medium
                  text-gray-600 border border-gray-200 rounded-lg px-5 py-3 hover:bg-gray-50 transition-colors">
            Cancel
        </a>
    </div>

</div>
</form>
</div>
@endsection
