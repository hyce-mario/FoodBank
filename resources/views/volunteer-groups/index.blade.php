@extends('layouts.app')
@section('title', 'Volunteer Groups')

@section('content')
<div x-data="{ deleteId: null, deleteName: '', deleteOpen: false }">

{{-- Header --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Volunteer Groups</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteers.index') }}" class="hover:text-brand-500">Volunteers</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Groups</span>
        </nav>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('volunteers.index') }}"
           class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50
                  font-semibold text-sm rounded-lg px-3 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Volunteers
        </a>
        <a href="{{ route('volunteer-groups.create') }}"
           class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            New Group
        </a>
    </div>
</div>

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Search bar --}}
<form method="GET" action="{{ route('volunteer-groups.index') }}"
      class="flex gap-2 mb-4">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Search groups..."
           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white
                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                  placeholder:text-gray-400">
    <button type="submit"
            class="px-4 py-2 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-lg transition-colors">
        Search
    </button>
    @if (request('search'))
        <a href="{{ route('volunteer-groups.index') }}"
           class="px-4 py-2 text-sm font-semibold border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
            Clear
        </a>
    @endif
</form>

{{-- Group Cards --}}
@if ($groups->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-8 py-16 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
        </svg>
        <p class="text-sm font-medium text-gray-500 mb-2">No groups yet</p>
        <a href="{{ route('volunteer-groups.create') }}" class="text-sm text-brand-600 hover:text-brand-700 font-semibold">
            Create the first group
        </a>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($groups as $group)
            {{-- Phase 5.10 — each card has its own menuOpen state so a
                 kebab opened on one card doesn't toggle others. The
                 parent x-data still owns the deleteOpen modal state
                 (Delete is a menu item that flips the parent flags). --}}
            <div x-data="{ menuOpen: false }"
                 class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-brand-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                            </svg>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                            {{ $group->volunteers_count }}
                            {{ $group->volunteers_count == 1 ? 'member' : 'members' }}
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 mb-1">{{ $group->name }}</h3>
                    @if ($group->description)
                        <p class="text-sm text-gray-500 leading-relaxed line-clamp-2">{{ $group->description }}</p>
                    @else
                        <p class="text-sm text-gray-400 italic">No description.</p>
                    @endif
                </div>
                {{-- Action footer: View (primary, full-flex) + kebab. Pre-fix
                     this row had 4 stacked buttons (View / Members / Edit /
                     Delete) which wrapped awkwardly on iPad-portrait widths. --}}
                <div class="flex items-stretch border-t border-gray-100 relative">
                    <a href="{{ route('volunteer-groups.show', $group) }}"
                       class="flex-1 py-2.5 text-xs font-semibold text-center text-brand-600 hover:bg-brand-50 transition-colors">
                        View
                    </a>
                    <button type="button"
                            @click="menuOpen = !menuOpen"
                            @click.away="menuOpen = false"
                            @keydown.escape.window="menuOpen = false"
                            aria-haspopup="menu"
                            :aria-expanded="menuOpen ? 'true' : 'false'"
                            class="border-l border-gray-100 px-4 text-gray-500 hover:bg-gray-50 transition-colors flex items-center justify-center"
                            title="More actions">
                        {{-- Vertical kebab --}}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"/>
                        </svg>
                    </button>

                    <div x-show="menuOpen" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         role="menu"
                         class="absolute bottom-full right-2 mb-1 w-44 bg-white border border-gray-200 rounded-xl shadow-lg z-20 overflow-hidden origin-bottom-right"
                         style="display:none;">
                        <a href="{{ route('volunteer-groups.members.edit', $group) }}"
                           role="menuitem"
                           class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold text-brand-600 hover:bg-brand-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                            Members
                        </a>
                        <a href="{{ route('volunteer-groups.edit', $group) }}"
                           role="menuitem"
                           class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                            Edit
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <button type="button"
                                role="menuitem"
                                @click="menuOpen = false; deleteId={{ $group->id }}; deleteName='{{ addslashes($group->name) }}'; deleteOpen=true"
                                class="w-full flex items-center gap-2 px-3.5 py-2.5 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($groups->hasPages())
        <div class="mt-4">{{ $groups->links() }}</div>
    @endif
@endif

{{-- Delete Modal --}}
<div x-show="deleteOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="deleteOpen = false" style="display:none;">
    <div x-show="deleteOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </div>
        <h2 class="text-base font-bold text-gray-900 mb-2">Delete Group</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Delete <strong x-text="deleteName" class="text-gray-700"></strong>? All memberships will be removed. This cannot be undone.
        </p>
        <div class="flex items-center gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form :action="'/volunteer-groups/' + deleteId" method="POST" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full py-2.5 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>

</div>
@endsection
