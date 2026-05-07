@extends('layouts.app')

@section('title', 'Settings — ' . $groupLabel)

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-screen-xl mx-auto">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Settings</h1>
        <p class="mt-1 text-sm text-gray-500">Manage application-wide configuration and preferences.</p>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
            <p class="font-medium mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 2-column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6 items-start">

        {{-- LEFT: nav partial --}}
        @include('settings._nav')

        {{-- RIGHT: form content --}}
        <div class="min-w-0 space-y-0">
            {{-- Sections that need to render OUTSIDE the parent PUT form
                 (e.g. logo/favicon upload, which carries its own POST + DELETE
                 forms) provide a sibling `<group>_above.blade.php` partial.
                 Rendered before the form so its content sits above. The
                 @stack legacy hook is kept for any future pushed extras. --}}
            @includeIf('settings.sections.' . $group . '_above', [
                'settings'    => $settings,
                'definitions' => $definitions,
            ])
            @stack('settings_standalone_forms')

            @cannot('settings.update')
                <div class="mb-4 flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 18.549 2.799a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                    </svg>
                    <div>
                        <p class="font-medium">Read-only view</p>
                        <p class="text-xs text-amber-700 mt-0.5">Your role can view settings but does not have permission to make changes. Contact an administrator if you need to update these values.</p>
                    </div>
                </div>
            @endcannot

            <form method="POST" action="{{ route('settings.update', $group) }}">
                @csrf
                @method('PUT')

                {{-- Disable every input under this group when the user cannot
                     update settings. Belt-and-braces: the controller + route
                     middleware also reject. --}}
                @cannot('settings.update')
                    <fieldset disabled class="space-y-0">
                @endcannot

                @include('settings.sections.' . $group, ['settings' => $settings, 'definitions' => $definitions])

                @cannot('settings.update')
                    </fieldset>
                @endcannot

                {{-- Save bar — hidden for read-only users so a stale-cache
                     middleware bypass cannot accidentally let them save. --}}
                @can('settings.update')
                <div class="mt-6 flex items-center justify-between bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-4">
                    <p class="text-sm text-gray-500">
                        Saving updates <span class="font-semibold text-gray-700">{{ $groupLabel }}</span> settings only.
                    </p>
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
                                   text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/>
                        </svg>
                        Save {{ $groupLabel }} Settings
                    </button>
                </div>
                @endcan
            </form>
        </div>

    </div>
</div>
@endsection
