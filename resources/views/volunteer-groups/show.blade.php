@extends('layouts.app')
@section('title', $volunteerGroup->name)

@section('content')
<div x-data="{ deleteOpen: false }">

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">{{ $volunteerGroup->name }}</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteers.index') }}" class="hover:text-brand-500 transition-colors">Volunteers</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteer-groups.index') }}" class="hover:text-brand-500 transition-colors">Groups</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">{{ $volunteerGroup->name }}</span>
        </nav>
    </div>
    <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
        <a href="{{ route('volunteer-groups.members.edit', $volunteerGroup) }}"
           class="flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
            Manage Members
        </a>
        <a href="{{ route('volunteer-groups.edit', $volunteerGroup) }}"
           class="flex items-center gap-1.5 bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
            Edit
        </a>
        <button type="button" @click="deleteOpen = true"
                class="flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
            Delete
        </button>
    </div>
</div>

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Stat Cards --}}
<div class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Members</p>
        <p class="text-3xl font-bold text-gray-900">{{ $volunteerGroup->volunteers->count() }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Created</p>
        <p class="text-xl font-bold text-gray-900">{{ $volunteerGroup->created_at->format('M d, Y') }}</p>
    </div>
</div>

{{-- Group info + Members --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Group Details --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">Group Details</h2>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Name</p>
                <p class="text-sm font-bold text-gray-900">{{ $volunteerGroup->name }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Description</p>
                @if ($volunteerGroup->description)
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $volunteerGroup->description }}</p>
                @else
                    <p class="text-sm text-gray-400 italic">No description.</p>
                @endif
            </div>
            <div class="pt-3 border-t border-gray-100">
                <a href="{{ route('volunteer-groups.members.edit', $volunteerGroup) }}"
                   class="w-full flex items-center justify-center gap-2 py-2.5 text-sm font-semibold
                          bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                    Manage Members
                </a>
            </div>
        </div>
    </div>

    {{-- Member List --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">
                Members
                <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $volunteerGroup->volunteers->count() }})</span>
            </h2>
            <a href="{{ route('volunteer-groups.members.edit', $volunteerGroup) }}"
               class="text-xs text-brand-600 hover:text-brand-700 font-semibold transition-colors">
                Edit members
            </a>
        </div>

        {{-- Desktop table --}}
        <div class="hidden sm:block">
            @if ($volunteerGroup->volunteers->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-gray-400">
                    No members yet.
                    <a href="{{ route('volunteer-groups.members.edit', $volunteerGroup) }}"
                       class="text-brand-600 hover:text-brand-700 font-semibold ml-1">Add members</a>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/60">
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($volunteerGroup->volunteers as $vol)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('volunteers.show', $vol) }}"
                                       class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">
                                        {{ $vol->full_name }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $vol->phone ?: '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $vol->email ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($vol->role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                                            {{ $vol->role }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-400">
                                    {{ $vol->pivot->joined_at ? \Carbon\Carbon::parse($vol->pivot->joined_at)->format('M d, Y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Mobile member cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @if ($volunteerGroup->volunteers->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-gray-400">
                    No members yet.
                    <a href="{{ route('volunteer-groups.members.edit', $volunteerGroup) }}" class="text-brand-600 font-semibold ml-1">Add members</a>
                </div>
            @else
                @foreach ($volunteerGroup->volunteers as $vol)
                    <div class="p-4 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <a href="{{ route('volunteers.show', $vol) }}"
                               class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">
                                {{ $vol->full_name }}
                            </a>
                            @if ($vol->role)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                                    {{ $vol->role }}
                                </span>
                            @endif
                        </div>
                        @if ($vol->phone)
                            <p class="text-xs text-gray-500">{{ $vol->phone }}</p>
                        @endif
                        @if ($vol->email)
                            <p class="text-xs text-gray-500">{{ $vol->email }}</p>
                        @endif
                        <p class="text-xs text-gray-400">
                            Joined {{ $vol->pivot->joined_at ? \Carbon\Carbon::parse($vol->pivot->joined_at)->format('M d, Y') : '—' }}
                        </p>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

</div>

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
            Delete <strong class="text-gray-700">{{ $volunteerGroup->name }}</strong>? All memberships will be removed.
        </p>
        <div class="flex items-center gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form action="{{ route('volunteer-groups.destroy', $volunteerGroup) }}" method="POST" class="flex-1">
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
