@extends('layouts.app')
@section('title', $household->full_name)

@section('content')
<div x-data="showPage()" x-init="init()">

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">{{ $household->full_name }}</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('households.index') }}" class="hover:text-brand-500 transition-colors">Households</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">{{ $household->full_name }}</span>
        </nav>
    </div>
    <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
        <button type="button" @click="openQr()"
                class="flex items-center gap-1.5 bg-brand-500 hover:bg-brand-600 text-white
                       text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75Z"/>
            </svg>
            QR Code
        </button>
        <a href="{{ route('households.edit', $household) }}"
           class="flex items-center gap-1.5 bg-navy-700 hover:bg-navy-800 text-white
                  text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
            </svg>
            Edit
        </a>
        @can('delete', $household)
            @if ($mergeCandidates->isNotEmpty())
                <button type="button" @click="mergeOpen = true"
                        class="flex items-center gap-1.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors"
                        title="Merge this duplicate household into another record">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                    Merge
                </button>
            @endif
        @endcan
        <button type="button" @click="deleteOpen = true"
                class="flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white
                       text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
            </svg>
            Delete
        </button>
    </div>
</div>

{{-- Flash --}}
@if (session('error'))
    <div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700
                rounded-xl px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        {{ session('error') }}
    </div>
@endif
@if (session('success'))
    <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700
                rounded-xl px-4 py-3 text-sm">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
        {{ session('success') }}
    </div>
@endif

{{-- ── Stat Cards ────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Total Visits</p>
        <p class="text-3xl font-bold text-gray-900">{{ $historyStats['total_visits'] }}</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Total Bags Received</p>
        <p class="text-3xl font-bold text-gray-900">{{ $historyStats['total_bags_received'] }}</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Household Size</p>
        <p class="text-3xl font-bold text-gray-900">{{ $household->household_size }}</p>
        <div class="flex items-center gap-2 mt-1">
            @if ($household->children_count > 0)
                <span class="text-xs text-blue-600 font-medium">{{ $household->children_count }}C</span>
            @endif
            @if ($household->adults_count > 0)
                <span class="text-xs text-green-600 font-medium">{{ $household->adults_count }}A</span>
            @endif
            @if ($household->seniors_count > 0)
                <span class="text-xs text-purple-600 font-medium">{{ $household->seniors_count }}S</span>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Last Served</p>
        @if ($historyStats['last_served_at'])
            <p class="text-2xl font-bold text-gray-900">{{ $historyStats['last_served_at']->format('M j, Y') }}</p>
        @else
            <p class="text-2xl font-bold text-gray-400">—</p>
        @endif
    </div>

</div>

{{-- ── Contact + Details ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    {{-- Contact Information --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">Contact Information</h2>
        </div>
        <div class="p-5 space-y-3.5">
            @if ($household->city || $household->state || $household->zip)
                <div class="flex items-start gap-3 text-sm text-gray-700">
                    <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                    </svg>
                    <span>{{ implode(', ', array_filter([$household->city, $household->state, $household->zip])) }}</span>
                </div>
            @endif
            @if ($household->phone)
                <div class="flex items-center gap-3 text-sm text-gray-700">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>
                    </svg>
                    <span>{{ $household->phone }}</span>
                </div>
            @endif
            @if ($household->email)
                <div class="flex items-center gap-3 text-sm text-gray-700">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                    </svg>
                    <span>{{ $household->email }}</span>
                </div>
            @endif
            @if (! $household->city && ! $household->phone && ! $household->email)
                <p class="text-sm text-gray-400 italic">No contact information on file.</p>
            @endif
            @if ($household->notes)
                <div class="pt-3 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Notes</p>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $household->notes }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Household Details --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">Household Details</h2>
        </div>
        <div class="p-5 space-y-5">

            {{-- Top grid --}}
            <div class="grid grid-cols-2 gap-y-5 gap-x-6">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Household ID</p>
                    <p class="text-sm font-bold text-gray-900">#{{ $household->household_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Total Size</p>
                    <p class="text-sm font-bold text-gray-900">
                        {{ $household->household_size }} {{ $household->household_size == 1 ? 'member' : 'members' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Registered</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $household->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Location</p>
                    <p class="text-sm font-semibold text-gray-900">
                        {{ $household->city ? $household->city.($household->state ? ', '.$household->state : '') : '—' }}
                        {{ $household->zip ? ' ' . $household->zip : '' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Vehicle</p>
                    @if ($household->vehicle_label)
                        <p class="text-sm font-semibold text-gray-900">{{ $household->vehicle_label }}</p>
                    @else
                        <p class="text-sm text-gray-400">Not provided</p>
                    @endif
                </div>
            </div>

            {{-- Demographic breakdown --}}
            <div class="pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Household Composition</p>
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-blue-700">{{ $household->children_count }}</p>
                        <p class="text-xs font-semibold text-blue-500 mt-0.5">Children</p>
                        <p class="text-xs text-blue-400">Under 18</p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-green-700">{{ $household->adults_count }}</p>
                        <p class="text-xs font-semibold text-green-600 mt-0.5">Adults</p>
                        <p class="text-xs text-green-400">18 – 64</p>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-xl px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-purple-700">{{ $household->seniors_count }}</p>
                        <p class="text-xs font-semibold text-purple-600 mt-0.5">Seniors</p>
                        <p class="text-xs text-purple-400">65+</p>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between bg-navy-700 text-white rounded-xl px-4 py-2.5"
                     x-data="{ showDemo: false }">
                    <span class="text-sm font-semibold">Total Household Size</span>
                    <span @mouseenter="showDemo = true" @mouseleave="showDemo = false"
                          @click.stop="showDemo = !showDemo"
                          class="relative inline-block cursor-help align-middle">
                        <span class="text-sm font-bold">1 Family</span>
                        <span x-show="showDemo" style="display:none"
                              x-transition:enter="transition ease-out duration-150"
                              x-transition:enter-start="opacity-0 translate-y-1"
                              x-transition:enter-end="opacity-100 translate-y-0"
                              class="absolute right-0 top-full mt-1 z-30 min-w-[10rem] bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                            <span class="block text-sm font-semibold text-gray-900 mb-2">
                                {{ $household->household_size }} {{ $household->household_size == 1 ? 'Member' : 'Members' }}
                            </span>
                            <span class="block text-xs text-gray-600 space-y-1">
                                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span><span class="font-semibold text-gray-800">{{ $household->children_count }}</span><span>{{ $household->children_count == 1 ? 'Child' : 'Children' }}</span></span>
                                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span><span class="font-semibold text-gray-800">{{ $household->adults_count }}</span><span>{{ $household->adults_count == 1 ? 'Adult' : 'Adults' }}</span></span>
                                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span><span class="font-semibold text-gray-800">{{ $household->seniors_count }}</span><span>{{ $household->seniors_count == 1 ? 'Senior' : 'Seniors' }}</span></span>
                            </span>
                        </span>
                    </span>
                </div>
            </div>

        </div>
    </div>

</div>

{{-- ── Representative Relationship ───────────────────────────────────── --}}
@if ($household->representative || $household->representedHouseholds->isNotEmpty())
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    {{-- This household IS represented by another --}}
    @if ($household->representative)
    <div class="bg-amber-50 border border-amber-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-amber-200 bg-amber-100/50">
            <h2 class="text-sm font-semibold text-amber-800">Represented By</h2>
        </div>
        <div class="p-5">
            <p class="text-xs text-amber-600 mb-3">This household's food is being picked up by:</p>
            <div class="flex items-center justify-between bg-white border border-amber-200 rounded-xl px-4 py-3">
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ $household->representative->full_name }}</p>
                    <p class="text-xs text-gray-500">#{{ $household->representative->household_number }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('households.show', $household->representative) }}"
                       class="text-xs font-semibold text-amber-700 hover:text-amber-900 underline underline-offset-2">
                        View
                    </a>
                    <form action="{{ route('households.detach', [$household->representative, $household]) }}" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="text-xs font-semibold text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50"
                                onclick="return confirm('Unlink this household from its representative?')">
                            Unlink
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- This household IS the representative for others --}}
    @if ($household->representedHouseholds->isNotEmpty())
    <div class="bg-indigo-50 border border-indigo-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-indigo-200 bg-indigo-100/50">
            <h2 class="text-sm font-semibold text-indigo-800">
                Representing {{ $household->representedHouseholds->count() }}
                {{ $household->representedHouseholds->count() == 1 ? 'Household' : 'Households' }}
            </h2>
        </div>
        <div class="p-5 space-y-2">
            @foreach ($household->representedHouseholds as $rep)
            <div class="flex items-center justify-between bg-white border border-indigo-200 rounded-xl px-4 py-2.5">
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $rep->full_name }}</p>
                    <p class="text-xs text-gray-400">
                        #{{ $rep->household_number }}
                        &middot; {{ $rep->household_size }} {{ $rep->household_size == 1 ? 'person' : 'people' }}
                        @if ($rep->children_count || $rep->seniors_count)
                            ({{ $rep->children_count }}C {{ $rep->adults_count }}A {{ $rep->seniors_count }}S)
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('households.show', $rep) }}"
                       class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 underline underline-offset-2">
                        View
                    </a>
                    <form action="{{ route('households.detach', [$household, $rep]) }}" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="text-xs font-semibold text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50"
                                onclick="return confirm('Unlink {{ addslashes($rep->full_name) }} from this household?')">
                            Unlink
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endif

{{-- ── Attach Existing Household ──────────────────────────────────────── --}}
@if (! $household->is_represented && $attachCandidates->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-4">
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
        <h2 class="text-sm font-semibold text-gray-800">Link an Existing Household</h2>
        <p class="text-xs text-gray-400 mt-0.5">Attach another household that this person will pick up food for.</p>
    </div>
    <div class="p-5">
        <form action="{{ route('households.attach', $household) }}" method="POST" class="flex items-center gap-3">
            @csrf
            <select name="represented_id"
                    class="flex-1 px-4 py-2.5 text-sm border border-gray-300 rounded-xl bg-white
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                <option value="">— Select a household to link —</option>
                @foreach ($attachCandidates as $candidate)
                    <option value="{{ $candidate->id }}">
                        {{ $candidate->full_name }} (#{{ $candidate->household_number }})
                    </option>
                @endforeach
            </select>
            <button type="submit"
                    class="flex-shrink-0 px-4 py-2.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition-colors">
                Link
            </button>
        </form>
    </div>
</div>
@endif

{{-- ── Event Report ──────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Table toolbar --}}
    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Event Report</h2>
        <div class="flex items-center gap-1.5">
            <a href="{{ route('households.event-report.pdf', $household) }}"
               class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-500 hover:bg-red-600 transition-colors"
               title="Export PDF">
                <span class="text-[10px] font-bold text-white leading-none">PDF</span>
            </a>
            <a href="{{ route('households.event-report.xlsx', $household) }}"
               class="w-8 h-8 flex items-center justify-center rounded-lg bg-green-600 hover:bg-green-700 transition-colors"
               title="Export Excel">
                <span class="text-[10px] font-bold text-white leading-none">XLS</span>
            </a>
            <a href="{{ route('households.event-report.print', $household) }}" target="_blank" rel="noopener"
               class="h-8 px-3 flex items-center gap-1.5 text-xs font-semibold text-gray-600
                      border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                </svg>
                Print
            </a>
        </div>
    </div>

    {{-- Desktop table --}}
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Event</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Bags Served</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($eventHistory as $visit)
                    @php
                        $event       = $visit->event;
                        $ruleset     = $event?->ruleset;
                        $snapshotSize = (int) ($visit->pivot->household_size ?? $household->household_size);
                        $bags        = $ruleset ? (int) $ruleset->getBagsFor($snapshotSize) : 0;
                        $statusLabel = $visit->visit_status === 'exited' ? 'Served' : 'In Progress';
                        $statusColor = $visit->visit_status === 'exited'
                            ? 'bg-green-100 text-green-700 ring-green-200'
                            : 'bg-amber-100 text-amber-700 ring-amber-200';
                        // Picked up by: when this visit's primary household
                        // (first attached) is not THIS household, the listed
                        // primary is the rep driver. Skip if the visit only
                        // has one household (i.e., self-pickup).
                        $primary    = $visit->households->first();
                        $pickedUpBy = ($primary && $primary->id !== $household->id) ? $primary : null;
                    @endphp
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3.5">
                            @if ($event)
                                <a href="{{ route('events.show', $event) }}"
                                   class="font-semibold text-gray-900 hover:text-brand-600 hover:underline underline-offset-2">
                                    {{ $event->name }}
                                </a>
                                @if ($pickedUpBy)
                                    <p class="text-xs text-amber-700 mt-0.5">
                                        ★ Picked up by
                                        <a href="{{ route('households.show', $pickedUpBy) }}"
                                           class="font-semibold hover:underline">{{ $pickedUpBy->full_name }}</a>
                                    </p>
                                @endif
                            @else
                                <span class="text-gray-400 italic">Event removed</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-600">
                            {{ $event?->date?->format('M j, Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-600">
                            {{ $event?->location ?: '—' }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-sm font-semibold text-gray-900">{{ $bags }}</span>
                            <span class="text-xs text-gray-400">{{ $bags == 1 ? 'bag' : 'bags' }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 {{ $statusColor }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">
                            No event history yet. Visits will appear here once this household checks in.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="sm:hidden divide-y divide-gray-100">
        @forelse ($eventHistory as $visit)
            @php
                $event        = $visit->event;
                $ruleset      = $event?->ruleset;
                $snapshotSize = (int) ($visit->pivot->household_size ?? $household->household_size);
                $bags         = $ruleset ? (int) $ruleset->getBagsFor($snapshotSize) : 0;
                $statusLabel  = $visit->visit_status === 'exited' ? 'Served' : 'In Progress';
                $statusColor  = $visit->visit_status === 'exited'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-amber-100 text-amber-700';
                $primary      = $visit->households->first();
                $pickedUpBy   = ($primary && $primary->id !== $household->id) ? $primary : null;
            @endphp
            <div class="px-4 py-3">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <div class="min-w-0">
                        @if ($event)
                            <a href="{{ route('events.show', $event) }}" class="font-semibold text-gray-900 hover:underline">
                                {{ $event->name }}
                            </a>
                        @else
                            <span class="text-gray-400 italic">Event removed</span>
                        @endif
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $event?->date?->format('M j, Y') ?? '—' }}
                            @if ($event?->location)
                                &middot; {{ $event->location }}
                            @endif
                        </p>
                        @if ($pickedUpBy)
                            <p class="text-xs text-amber-700 mt-0.5">★ Picked up by {{ $pickedUpBy->full_name }}</p>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusColor }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <p class="text-xs text-gray-500">
                    <span class="font-semibold text-gray-900">{{ $bags }}</span> {{ $bags == 1 ? 'bag' : 'bags' }} received
                </p>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-gray-400">No event history yet.</div>
        @endforelse
    </div>

    {{-- Table footer --}}
    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50/50">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>Rows per page</span>
            <select onchange="window.location.href=this.value"
                    class="text-sm border border-gray-200 rounded-lg px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                @foreach ([10, 25, 50] as $pp)
                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => 1]) }}"
                            @selected(request('per_page', 10) == $pp)>{{ $pp }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-1 text-sm">
            @if ($eventHistory->onFirstPage())
                <span class="w-8 h-8 flex items-center justify-center text-gray-300 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
                </span>
            @else
                <a href="{{ $eventHistory->previousPageUrl() }}"
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
                </a>
            @endif

            @foreach ($eventHistory->getUrlRange(max(1, $eventHistory->currentPage() - 2), min($eventHistory->lastPage(), $eventHistory->currentPage() + 2)) as $page => $url)
                <a href="{{ $url }}"
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition-colors
                          {{ $page == $eventHistory->currentPage() ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $page }}
                </a>
            @endforeach

            @if ($eventHistory->hasMorePages())
                <a href="{{ $eventHistory->nextPageUrl() }}"
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @else
                <span class="w-8 h-8 flex items-center justify-center text-gray-300 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </span>
            @endif
        </div>
    </div>

</div>

{{-- ═══ QR CODE MODAL ════════════════════════════════════════════════ --}}
<div x-show="qrOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="qrOpen = false" style="display:none;">
    <div x-show="qrOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 text-center">
        <h2 class="text-base font-bold text-gray-900 mb-0.5">QR Code</h2>
        <p class="text-xs text-gray-400 mb-4">Scan to identify this household at check-in</p>
        <p class="text-xl font-bold text-gray-900 mb-3">#{{ $household->household_number }}</p>
        <div class="flex justify-center mb-4">
            <div class="border-2 border-gray-200 rounded-xl p-3">
                <canvas id="qrCanvas" width="180" height="180"></canvas>
            </div>
        </div>
        <p class="font-semibold text-gray-900 text-sm">{{ $household->full_name }}</p>

        {{-- Family tag — same hover/tap-revealed demographic chip used on intake/scanner --}}
        <div class="flex justify-center mt-1.5 mb-5" x-data="{ showDemo: false }">
            <span @mouseenter="showDemo = true" @mouseleave="showDemo = false"
                  @click.stop="showDemo = !showDemo"
                  class="relative inline-block cursor-help align-middle">
                <span class="text-sm font-semibold text-gray-700">1 Family</span>
                <span x-show="showDemo" style="display:none"
                      x-transition:enter="transition ease-out duration-150"
                      x-transition:enter-start="opacity-0 translate-y-1"
                      x-transition:enter-end="opacity-100 translate-y-0"
                      class="absolute left-1/2 -translate-x-1/2 top-full mt-1 z-30 min-w-[10rem] bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                    <span class="block text-sm font-semibold text-gray-900 mb-2">
                        {{ $household->household_size }} {{ $household->household_size == 1 ? 'Member' : 'Members' }}
                    </span>
                    <span class="block text-xs text-gray-600 space-y-1">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span><span class="font-semibold text-gray-800">{{ $household->children_count }}</span><span>{{ $household->children_count == 1 ? 'Child' : 'Children' }}</span></span>
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span><span class="font-semibold text-gray-800">{{ $household->adults_count }}</span><span>{{ $household->adults_count == 1 ? 'Adult' : 'Adults' }}</span></span>
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span><span class="font-semibold text-gray-800">{{ $household->seniors_count }}</span><span>{{ $household->seniors_count == 1 ? 'Senior' : 'Seniors' }}</span></span>
                    </span>
                </span>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <button @click="qrOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Close
            </button>
            <button onclick="printQr()"
                    class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659"/>
                </svg>
                Print
            </button>
            <form action="{{ route('households.regenerate-qr', $household) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center gap-1.5 py-2.5 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    Regenerate
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ═══ MERGE MODAL (Phase 6.5.d) ════════════════════════════════════ --}}
@can('delete', $household)
    @if ($mergeCandidates->isNotEmpty())
    <div x-show="mergeOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
         @click.self="mergeOpen = false" style="display:none;">
        <div x-show="mergeOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">

            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                </div>
                <h2 class="text-base font-bold text-gray-900">Merge Household Record</h2>
            </div>

            <p class="text-sm text-gray-600 leading-relaxed mb-4">
                Pick the household record to merge <strong>{{ $household->full_name }}</strong> (#{{ $household->household_number }}) into.
                All visits, pre-registrations, pledges, and represented households will move to the chosen record,
                and <strong>{{ $household->full_name }}</strong> will be deleted. This cannot be undone.
            </p>

            <form method="POST" action="{{ route('households.merge', $household) }}">
                @csrf
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                    Keeper (the record to keep) <span class="text-red-500">*</span>
                </label>
                <select name="keeper_id" required x-model="mergeKeeperId"
                        class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl bg-white
                               focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400">
                    <option value="">Select a household…</option>
                    @foreach ($mergeCandidates as $cand)
                        <option value="{{ $cand->id }}">
                            {{ trim($cand->first_name . ' ' . $cand->last_name) }} (#{{ $cand->household_number }})@if ($cand->phone) — {{ $cand->phone }}@endif
                        </option>
                    @endforeach
                </select>

                <div class="bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-2.5 text-xs text-amber-800 mt-3 leading-relaxed">
                    <strong>Heads up:</strong> if both households have an active visit at the same event,
                    the merge will be refused — please complete or exit one of them first. Conflicting
                    pre-registrations on the duplicate are auto-cancelled to avoid same-event collisions.
                </div>

                <div class="flex items-center gap-3 mt-5">
                    <button type="button" @click="mergeOpen = false"
                            class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="!mergeKeeperId"
                            class="flex-1 py-2.5 text-sm font-semibold bg-orange-600 hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl transition-colors">
                        Merge
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endcan

{{-- ═══ DELETE MODAL ═════════════════════════════════════════════════ --}}
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
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </div>
        <h2 class="text-base font-bold text-gray-900 mb-2">Delete Household</h2>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Are you sure you want to delete <strong class="text-gray-700">{{ $household->full_name }}</strong>?
            This action cannot be undone.
        </p>
        <div class="flex items-center gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <form action="{{ route('households.destroy', $household) }}" method="POST" class="flex-1">
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

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
<script>
function showPage() {
    return {
        qrOpen: false,
        deleteOpen: false,
        mergeOpen: false,
        mergeKeeperId: '',
        init() {},
        openQr() {
            this.qrOpen = true;
            this.$nextTick(() => {
                const canvas = document.getElementById('qrCanvas');
                if (canvas) {
                    new QRious({
                        element: canvas,
                        value: '{{ $household->qr_token ?? $household->household_number }}',
                        size: 180,
                        foreground: '#1b2b4b',
                        background: '#ffffff',
                        level: 'H',
                    });
                }
            });
        },
    };
}

function printQr() {
    const canvas = document.getElementById('qrCanvas');
    const dataUrl = canvas.toDataURL('image/png');
    const win = window.open('', '_blank');
    win.document.write(`
        <html><head><title>QR – {{ $household->full_name }}</title>
        <style>
            body { font-family: sans-serif; text-align: center; padding: 40px; }
            img { width: 200px; height: 200px; border: 2px solid #e5e7eb; border-radius: 12px; padding: 8px; }
            h2 { color: #1b2b4b; margin-bottom: 4px; }
            p { color: #9ca3af; font-size: 14px; }
        </style></head>
        <body>
            <h2>#{{ $household->household_number }}</h2>
            <img src="${dataUrl}" />
            <h3>{{ $household->full_name }}</h3>
            <p>{{ $household->household_size }} {{ $household->household_size == 1 ? 'person' : 'people' }}</p>
            <script>window.onload = () => { window.print(); window.close(); }<\/script>
        </body></html>`);
    win.document.close();
}
</script>
@endpush
