@extends('layouts.app')
@section('title', $role->display_name)

@section('content')
<div>

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold text-gray-900">{{ $role->display_name }}</h1>
            @if ($role->name === 'ADMIN')
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide
                             bg-amber-100 text-amber-700 border border-amber-200 rounded px-2 py-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                    System
                </span>
            @endif
        </div>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('roles.index') }}" class="hover:text-brand-500">Roles</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">{{ $role->display_name }}</span>
        </nav>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('roles.edit', $role) }}"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white
                  font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
            </svg>
            Edit
        </a>
        <a href="{{ route('roles.index') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900
                  border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
            Back
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

    {{-- ── Left: Role Info ──────────────────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-5">

        {{-- Details card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Role Info</h2>
            <dl class="space-y-3">
                <div>
                    <dt class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Identifier</dt>
                    <dd class="text-sm font-mono font-semibold text-gray-800 bg-gray-100 inline-block px-2 py-0.5 rounded">{{ $role->name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Display Name</dt>
                    <dd class="text-sm text-gray-900 font-medium">{{ $role->display_name }}</dd>
                </div>
                @if ($role->description)
                <div>
                    <dt class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Description</dt>
                    <dd class="text-sm text-gray-600">{{ $role->description }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Created</dt>
                    <dd class="text-sm text-gray-600">{{ $role->created_at->format('M j, Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Assigned Users card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                Assigned Users
                <span class="ml-1 bg-gray-100 text-gray-600 rounded-full px-2 py-0.5 font-semibold text-xs">
                    {{ $role->users->count() }}
                </span>
            </h2>
            @if ($role->users->isEmpty())
                <p class="text-sm text-gray-400 text-center py-4">No users assigned to this role.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($role->users as $user)
                    <li class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-navy-700 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $user->email }}</p>
                        </div>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Danger zone --}}
        @if ($role->name !== 'ADMIN')
        <div class="bg-white rounded-2xl border border-red-100 shadow-sm p-5">
            <h2 class="text-xs font-semibold text-red-500 uppercase tracking-wide mb-3">Danger Zone</h2>
            <p class="text-xs text-gray-500 mb-3">
                Deleting a role is permanent. Roles with assigned users cannot be deleted.
            </p>
            <form method="POST" action="{{ route('roles.destroy', $role) }}"
                  onsubmit="return confirm('Delete role \'{{ addslashes($role->display_name) }}\'? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 text-sm font-semibold text-red-600
                               border border-red-200 rounded-lg px-4 py-2 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                    </svg>
                    Delete Role
                </button>
            </form>
        </div>
        @endif

    </div>

    {{-- ── Right: Permissions ───────────────────────────────────────── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">Permissions</h2>
                <span class="text-xs text-gray-400">
                    {{ $role->permissions->count() }} {{ Str::plural('permission', $role->permissions->count()) }}
                </span>
            </div>

            @if (in_array('*', $rolePermissions))
                <div class="rounded-xl bg-purple-50 border border-purple-100 p-5 text-center">
                    <svg class="w-6 h-6 mx-auto mb-2 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                    </svg>
                    <p class="text-sm font-semibold text-purple-800">Full Access</p>
                    <p class="text-xs text-purple-600 mt-0.5">This role has the <code class="bg-purple-100 px-1 rounded">*</code> wildcard — it can perform any action in the system.</p>
                </div>
            @elseif (empty($rolePermissions))
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 text-center">
                    <p class="text-sm text-gray-400">No permissions assigned to this role.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($groups as $resource => $actions)
                        @php
                            $resourcePerms = collect($actions)
                                ->map(fn($a) => "{$resource}.{$a}")
                                ->filter(fn($p) => in_array($p, $rolePermissions))
                                ->values();
                        @endphp
                        @if ($resourcePerms->isNotEmpty())
                        <div class="border border-gray-100 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-100">
                                <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">{{ ucfirst($resource) }}</span>
                            </div>
                            <div class="px-4 py-3 flex flex-wrap gap-2">
                                @foreach ($actions as $action)
                                    @php $perm = "{$resource}.{$action}"; @endphp
                                    @if (in_array($perm, $rolePermissions))
                                        <span class="inline-flex items-center gap-1 text-xs font-medium
                                                     bg-green-50 text-green-700 border border-green-100
                                                     rounded-full px-2.5 py-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                            </svg>
                                            {{ $action }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
</div>
@endsection
