@extends('layouts.app')
@section('title', 'New Role')

@section('content')
<div x-data="roleForm()" x-init="init()">

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">New Role</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('roles.index') }}" class="hover:text-brand-500">Roles</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">New Role</span>
        </nav>
    </div>
    <a href="{{ route('roles.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900
              border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back
    </a>
</div>

<form method="POST" action="{{ route('roles.store') }}">
@csrf

{{-- Top-level validation summary — surfaces every error, including
     ones on fields that don't have an inline @error display
     (like permissions[]). Without this the form silently 302s back
     to itself when permissions or another non-displayed field fails. --}}
@if ($errors->any())
    <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <p class="font-semibold mb-1">Please fix the following:</p>
        <ul class="list-disc list-inside space-y-0.5 text-xs">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ── Left: Role Details ───────────────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-5">

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Role Details</h2>

            {{-- Name --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Role Identifier <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" id="name"
                       value="{{ old('name') }}"
                       placeholder="e.g. MY_ROLE"
                       class="w-full px-3 py-2 text-sm border rounded-lg font-mono uppercase
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              @error('name') border-red-400 bg-red-50 @else border-gray-200 bg-gray-50 @enderror">
                <p class="text-[11px] text-gray-400 mt-1">Uppercase letters, numbers, underscores only. Cannot be changed later.</p>
                @error('name')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Display Name --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Display Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="display_name"
                       value="{{ old('display_name') }}"
                       placeholder="e.g. Intake Staff"
                       class="w-full px-3 py-2 text-sm border rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              @error('display_name') border-red-400 bg-red-50 @else border-gray-200 bg-gray-50 @enderror">
                @error('display_name')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Description
                </label>
                <textarea name="description" rows="3"
                          placeholder="Brief description of this role's purpose..."
                          class="w-full px-3 py-2 text-sm border border-gray-200 bg-gray-50 rounded-lg
                                 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                 resize-none @error('description') border-red-400 bg-red-50 @enderror">{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Wildcard card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <label class="flex items-start gap-3 cursor-pointer group">
                <div class="mt-0.5">
                    <input type="checkbox"
                           x-model="wildcard"
                           @change="toggleWildcard()"
                           class="w-4 h-4 rounded border-gray-300 text-brand-500
                                  focus:ring-brand-500/20 cursor-pointer">
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-brand-600">Full Access (Wildcard)</p>
                    <p class="text-xs text-gray-500 mt-0.5">Grant <code class="bg-gray-100 px-1 rounded">*</code> permission — role can perform any action in the system.</p>
                </div>
            </label>
            {{-- When wildcard is on, ship a hidden permissions[]=* so the
                 backend sees the wildcard. The previous version also had a
                 second hidden input with value="" alongside this one to
                 "ensure permissions[] always exists in the request" — but
                 Laravel's ConvertEmptyStringsToNull middleware rewrote that
                 "" to null before validation, which then 422'd on the
                 permissions.* string rule and silently 302'd back to this
                 form. Removed; Laravel handles a missing permissions[] key
                 fine via `nullable|array` + `?? []` in the service. --}}
            <template x-if="wildcard">
                <input type="hidden" name="permissions[]" value="*">
            </template>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="submit"
                    class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold
                           text-sm rounded-lg px-4 py-2.5 transition-colors text-center">
                Create Role
            </button>
            <a href="{{ route('roles.index') }}"
               class="px-4 py-2.5 text-sm font-medium text-gray-600 border border-gray-200
                      rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </a>
        </div>

    </div>

    {{-- ── Right: Permissions ───────────────────────────────────────── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">Permissions</h2>
                <div class="flex items-center gap-2" x-show="!wildcard">
                    <button type="button" @click="selectAll()"
                            class="text-xs text-brand-600 hover:text-brand-700 font-medium">
                        Select All
                    </button>
                    <span class="text-gray-300">|</span>
                    <button type="button" @click="clearAll()"
                            class="text-xs text-gray-500 hover:text-gray-700 font-medium">
                        Clear All
                    </button>
                </div>
            </div>

            <div x-show="wildcard"
                 class="rounded-xl bg-purple-50 border border-purple-100 p-4 text-sm text-purple-700 text-center">
                <svg class="w-5 h-5 mx-auto mb-1.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                </svg>
                <strong>Full Access</strong> — this role has permission to perform all actions.
            </div>

            <div x-show="!wildcard" class="space-y-5">
                @foreach ($groups as $resource => $actions)
                <div class="border border-gray-100 rounded-xl overflow-hidden">
                    {{-- Resource header --}}
                    <div class="flex items-center justify-between bg-gray-50 px-4 py-2.5 border-b border-gray-100">
                        <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">{{ ucfirst($resource) }}</span>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-500 hover:text-gray-700">
                                <input type="checkbox"
                                       @change="toggleResource('{{ $resource }}', $event.target.checked)"
                                       :checked="resourceAllChecked('{{ $resource }}')"
                                       class="w-3.5 h-3.5 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 cursor-pointer">
                                All
                            </label>
                        </div>
                    </div>
                    {{-- Actions --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-0 divide-x divide-y divide-gray-100">
                        @foreach ($actions as $action)
                        <label class="flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="{{ $resource }}.{{ $action }}"
                                   x-model="selected"
                                   :value="'{{ $resource }}.{{ $action }}'"
                                   {{ in_array("{$resource}.{$action}", old('permissions', [])) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-gray-300 text-brand-500
                                          focus:ring-brand-500/20 cursor-pointer">
                            <span class="text-sm text-gray-700 capitalize">{{ $action }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

        </div>
    </div>
</div>

</form>
</div>

@push('scripts')
<script>
function roleForm() {
    return {
        wildcard: {{ in_array('*', old('permissions', [])) ? 'true' : 'false' }},
        selected: @json(old('permissions', [])),

        init() {},

        toggleWildcard() {
            if (this.wildcard) {
                this.selected = [];
            }
        },

        selectAll() {
            const all = [];
            @foreach ($groups as $resource => $actions)
                @foreach ($actions as $action)
                    all.push('{{ $resource }}.{{ $action }}');
                @endforeach
            @endforeach
            this.selected = all;
        },

        clearAll() {
            this.selected = [];
        },

        toggleResource(resource, checked) {
            const actions = @json($groups);
            const resourceActions = (actions[resource] || []).map(a => `${resource}.${a}`);
            if (checked) {
                this.selected = [...new Set([...this.selected, ...resourceActions])];
            } else {
                this.selected = this.selected.filter(p => !resourceActions.includes(p));
            }
        },

        resourceAllChecked(resource) {
            const actions = @json($groups);
            const resourceActions = (actions[resource] || []).map(a => `${resource}.${a}`);
            return resourceActions.every(p => this.selected.includes(p));
        },
    };
}
</script>
@endpush
@endsection
