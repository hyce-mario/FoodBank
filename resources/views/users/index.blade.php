@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div>

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Users</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Users</span>
        </nav>
    </div>
    <a href="{{ route('users.create') }}"
       class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
              font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        <span class="hidden sm:inline">New User</span>
        <span class="sm:hidden">Add</span>
    </a>
</div>

{{-- Flash --}}
@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif
@if (session('error'))
<div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    {{ session('error') }}
</div>
@endif

{{-- ── Main Card ────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Toolbar --}}
    <form method="GET" action="{{ route('users.index') }}"
          class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">

        {{-- Search --}}
        <div class="relative flex-1 min-w-[160px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search name or email..."
                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                          placeholder:text-gray-400">
        </div>

        {{-- Role filter --}}
        <select name="role_id"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50
                       focus:outline-none focus:ring-2 focus:ring-brand-500/20
                       text-gray-600 cursor-pointer min-w-[130px]">
            <option value="">All Roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>
                    {{ $role->display_name }}
                </option>
            @endforeach
        </select>

        <button type="submit"
                class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg
                       bg-gray-50 hover:bg-gray-100 transition-colors">
            Filter
        </button>

        @if (request()->hasAny(['search', 'role_id']))
            <a href="{{ route('users.index') }}"
               class="text-xs text-gray-500 hover:text-gray-700 px-2 py-2 hover:bg-gray-100 rounded-lg transition-colors">
                Clear
            </a>
        @endif

        {{-- Count --}}
        <span class="ml-auto text-xs text-gray-400 hidden md:block">
            {{ $users->total() }} {{ Str::plural('user', $users->total()) }}
        </span>
    </form>

    @if ($users->isEmpty())
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-600">No users found</p>
            @if (request()->hasAny(['search', 'role_id']))
                <p class="text-xs text-gray-400 mt-1">Try adjusting your search or filter</p>
            @else
                <a href="{{ route('users.create') }}"
                   class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Create the first user
                </a>
            @endif
        </div>
    @else
        {{-- ── Desktop Table ───────────────────────────────────────────── --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">User</th>
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Role</th>
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Joined</th>
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 py-3">Last Updated</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($users as $user)
                    <tr class="hover:bg-gray-50/60 transition-colors group">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-navy-700 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold text-white">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $user->email }}</p>
                                </div>
                                @if ($user->id === auth()->id())
                                    <span class="text-[10px] font-bold uppercase tracking-wide bg-brand-100 text-brand-700 border border-brand-200 rounded px-1.5 py-0.5">You</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->role)
                                <span class="inline-flex items-center text-xs font-semibold bg-gray-100 text-gray-700 rounded-full px-2.5 py-1">
                                    {{ $user->role->display_name }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">No role</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $user->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $user->updated_at->diffForHumans() }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('users.show', $user) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-500 hover:bg-brand-50 rounded-lg transition-colors"
                                   title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.573-3.007-9.963-7.178Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('users.edit', $user) }}"
                                   class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                    </svg>
                                </a>
                                @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $user) }}"
                                      onsubmit="return confirm('Delete {{ addslashes($user->name) }}? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ── Mobile Cards ─────────────────────────────────────────────── --}}
        <div class="md:hidden divide-y divide-gray-100">
            @foreach ($users as $user)
            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-navy-700 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-white">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <p class="font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                                @if ($user->id === auth()->id())
                                    <span class="text-[10px] font-bold uppercase tracking-wide bg-brand-100 text-brand-700 border border-brand-200 rounded px-1.5 py-0.5 flex-shrink-0">You</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-400 truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                    {{-- Role badge --}}
                    @if ($user->role)
                        <span class="flex-shrink-0 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full px-2.5 py-1">
                            {{ $user->role->display_name }}
                        </span>
                    @endif
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <p class="text-xs text-gray-400">Joined {{ $user->created_at->format('M j, Y') }}</p>
                    {{-- Actions --}}
                    <div class="flex items-center gap-2">
                        <a href="{{ route('users.show', $user) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600
                                  border border-brand-200 bg-brand-50 rounded-lg px-3 py-1.5 hover:bg-brand-100 transition-colors">
                            View
                        </a>
                        <a href="{{ route('users.edit', $user) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600
                                  border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50 transition-colors">
                            Edit
                        </a>
                        @if ($user->id !== auth()->id())
                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                              onsubmit="return confirm('Delete {{ addslashes($user->name) }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center text-xs font-semibold text-red-600
                                           border border-red-200 bg-red-50 rounded-lg px-3 py-1.5 hover:bg-red-100 transition-colors">
                                Delete
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($users->hasPages())
        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/60">
            {{ $users->links() }}
        </div>
        @endif

        {{-- Footer count --}}
        <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50/60">
            <p class="text-xs text-gray-400">
                Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }} {{ Str::plural('user', $users->total()) }}
            </p>
        </div>
    @endif

</div>
</div>
@endsection
