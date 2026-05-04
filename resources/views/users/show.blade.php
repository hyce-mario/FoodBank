@extends('layouts.app')
@section('title', $user->name)

@section('content')
<div>

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold text-gray-900">{{ $user->name }}</h1>
            @if ($user->id === auth()->id())
                <span class="text-[10px] font-bold uppercase tracking-wide bg-brand-100 text-brand-700 border border-brand-200 rounded px-2 py-0.5">You</span>
            @endif
        </div>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('users.index') }}" class="hover:text-brand-500">Users</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">{{ $user->name }}</span>
        </nav>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('users.edit', $user) }}"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white
                  font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
            </svg>
            <span class="hidden sm:inline">Edit</span>
        </a>
        <a href="{{ route('users.index') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900
                  border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
            <span class="hidden sm:inline">Back</span>
        </a>
    </div>
</div>

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ── Left: Profile ────────────────────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-5">

        {{-- Identity card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            {{-- Avatar + name --}}
            <div class="flex flex-col items-center text-center mb-5">
                <div class="w-16 h-16 rounded-full bg-navy-700 flex items-center justify-center mb-3">
                    <span class="text-2xl font-bold text-white">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                </div>
                <p class="text-base font-bold text-gray-900">{{ $user->name }}</p>
                <p class="text-sm text-gray-400 mt-0.5">{{ $user->email }}</p>
                @if ($user->role)
                    <span class="mt-2 inline-flex items-center text-xs font-semibold bg-gray-100 text-gray-700 rounded-full px-3 py-1">
                        {{ $user->role->display_name }}
                    </span>
                @endif
            </div>

            <dl class="space-y-3 border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between">
                    <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Joined</dt>
                    <dd class="text-sm text-gray-700">{{ $user->created_at->format('M j, Y') }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Updated</dt>
                    <dd class="text-sm text-gray-700">{{ $user->updated_at->diffForHumans() }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Verified</dt>
                    <dd>
                        @if ($user->email_verified_at)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 bg-green-50 border border-green-100 rounded-full px-2 py-0.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                Yes
                            </span>
                        @else
                            <span class="text-xs text-gray-400">Not verified</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Danger zone --}}
        @if ($user->id !== auth()->id())
        <div class="bg-white rounded-2xl border border-red-100 shadow-sm p-5">
            <h2 class="text-xs font-semibold text-red-500 uppercase tracking-wide mb-3">Danger Zone</h2>
            <p class="text-xs text-gray-500 mb-3">
                Permanently deletes this user account. This action cannot be undone.
            </p>
            <form method="POST" action="{{ route('users.destroy', $user) }}"
                  onsubmit="return confirm('Delete {{ addslashes($user->name) }}? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 text-sm font-semibold text-red-600
                               border border-red-200 rounded-lg px-4 py-2 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                    </svg>
                    Delete User
                </button>
            </form>
        </div>
        @endif

    </div>

    {{-- ── Right: Role & Permissions ────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Role detail --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Assigned Role</h2>

            @if ($user->role)
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $user->role->display_name }}</p>
                        @if ($user->role->description)
                            <p class="text-sm text-gray-500 mt-0.5">{{ $user->role->description }}</p>
                        @endif
                        <p class="text-xs font-mono text-gray-400 mt-1">{{ $user->role->name }}</p>
                    </div>
                    <a href="{{ route('roles.show', $user->role) }}"
                       class="flex-shrink-0 text-xs font-semibold text-brand-600 border border-brand-200 bg-brand-50
                              rounded-lg px-3 py-1.5 hover:bg-brand-100 transition-colors">
                        View Role
                    </a>
                </div>

                {{-- Permissions summary --}}
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Permissions</p>

                    @if ($user->role->permissions->where('permission', '*')->isNotEmpty())
                        <div class="rounded-xl bg-purple-50 border border-purple-100 p-4 text-center">
                            <svg class="w-5 h-5 mx-auto mb-1.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                            </svg>
                            <p class="text-sm font-semibold text-purple-800">Full Access</p>
                            <p class="text-xs text-purple-600 mt-0.5">This role has the <code class="bg-purple-100 px-1 rounded">*</code> wildcard permission.</p>
                        </div>
                    @elseif ($user->role->permissions->isEmpty())
                        <p class="text-sm text-gray-400">No permissions assigned to this role.</p>
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($user->role->permissions as $permission)
                                <span class="inline-flex items-center text-xs font-medium bg-gray-100 text-gray-600 rounded-full px-2.5 py-1">
                                    {{ $permission->permission }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center py-6">
                    <p class="text-sm text-gray-400 mb-3">No role assigned to this user.</p>
                    <a href="{{ route('users.edit', $user) }}"
                       class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                        Assign a role →
                    </a>
                </div>
            @endif
        </div>

    </div>

</div>
</div>
@endsection
