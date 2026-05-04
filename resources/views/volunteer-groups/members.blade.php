@extends('layouts.app')
@section('title', 'Manage Members — ' . $volunteerGroup->name)

@section('content')

<div x-data="membersForm({{ json_encode($currentIds) }})" x-init="init()">

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Manage Members</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('volunteers.index') }}" class="hover:text-brand-500 transition-colors">Volunteers</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteer-groups.index') }}" class="hover:text-brand-500 transition-colors">Groups</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('volunteer-groups.show', $volunteerGroup) }}" class="hover:text-brand-500 transition-colors">{{ $volunteerGroup->name }}</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">Members</span>
        </nav>
    </div>
    <a href="{{ route('volunteer-groups.show', $volunteerGroup) }}"
       class="flex items-center gap-2 bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors self-start">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to Group
    </a>
</div>

{{-- Summary bar --}}
<div class="flex items-center justify-between bg-white rounded-2xl border border-gray-200 shadow-sm px-5 py-3.5 mb-4">
    <div>
        <p class="text-sm font-semibold text-gray-900">{{ $volunteerGroup->name }}</p>
        <p class="text-xs text-gray-400 mt-0.5">
            <span x-text="selected.length"></span> volunteer<span x-show="selected.length !== 1">s</span> selected
        </p>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" @click="selectAll()"
                class="px-3 py-1.5 text-xs font-semibold border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
            Select All
        </button>
        <button type="button" @click="clearAll()"
                class="px-3 py-1.5 text-xs font-semibold border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
            Clear
        </button>
    </div>
</div>

<form method="POST" action="{{ route('volunteer-groups.members.update', $volunteerGroup) }}" id="membersForm">
    @csrf

    @if ($allVolunteers->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-8 py-16 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
            </svg>
            <p class="text-sm font-medium text-gray-500 mb-3">No volunteers in the system yet.</p>
            <a href="{{ route('volunteers.create') }}"
               class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
                Add First Volunteer
            </a>
        </div>
    @else

        {{-- Search filter --}}
        <div class="mb-3">
            <input type="text" x-model="search"
                   placeholder="Filter volunteers by name, role..."
                   class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-xl bg-white
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                          placeholder:text-gray-400">
        </div>

        {{-- Volunteer list --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">

            {{-- Desktop --}}
            <div class="hidden sm:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/60">
                            <th class="w-12 px-4 py-3"></th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($allVolunteers as $vol)
                            <tr x-show="matchesSearch('{{ strtolower(addslashes($vol->full_name)) }}', '{{ strtolower(addslashes($vol->role ?? '')) }}')"
                                class="hover:bg-gray-50/50 transition-colors cursor-pointer"
                                @click="toggle({{ $vol->id }})">
                                <td class="px-4 py-3.5 text-center">
                                    <input type="checkbox"
                                           name="volunteer_ids[]"
                                           value="{{ $vol->id }}"
                                           :checked="selected.includes({{ $vol->id }})"
                                           @click.stop="toggle({{ $vol->id }})"
                                           class="w-4 h-4 rounded border-gray-300 text-brand-600
                                                  focus:ring-brand-500 cursor-pointer">
                                </td>
                                <td class="px-4 py-3.5 font-semibold text-gray-900">{{ $vol->full_name }}</td>
                                <td class="px-4 py-3.5 text-gray-500">{{ $vol->phone ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-gray-500">{{ $vol->email ?: '—' }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($vol->role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                                            {{ $vol->role }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="sm:hidden divide-y divide-gray-100">
                @foreach ($allVolunteers as $vol)
                    <label x-show="matchesSearch('{{ strtolower(addslashes($vol->full_name)) }}', '{{ strtolower(addslashes($vol->role ?? '')) }}')"
                           class="flex items-start gap-3 p-4 cursor-pointer hover:bg-gray-50 transition-colors"
                           :class="selected.includes({{ $vol->id }}) ? 'bg-brand-50' : ''">
                        <input type="checkbox"
                               name="volunteer_ids[]"
                               value="{{ $vol->id }}"
                               :checked="selected.includes({{ $vol->id }})"
                               @change="toggle({{ $vol->id }})"
                               class="w-5 h-5 mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-gray-900">{{ $vol->full_name }}</span>
                                @if ($vol->role)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                                        {{ $vol->role }}
                                    </span>
                                @endif
                            </div>
                            @if ($vol->phone)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $vol->phone }}</p>
                            @endif
                            @if ($vol->email)
                                <p class="text-xs text-gray-500">{{ $vol->email }}</p>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>

            {{-- No results from search --}}
            <div x-show="visibleCount === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                No volunteers match your search.
            </div>
        </div>

        {{-- Sticky save bar --}}
        <div class="sticky bottom-4 flex items-center justify-between bg-navy-700 text-white rounded-2xl px-5 py-3.5 shadow-lg">
            <p class="text-sm font-semibold">
                <span x-text="selected.length"></span>
                volunteer<span x-show="selected.length !== 1">s</span> selected
            </p>
            <div class="flex items-center gap-2">
                <a href="{{ route('volunteer-groups.show', $volunteerGroup) }}"
                   class="px-4 py-2 text-sm font-semibold bg-white/10 hover:bg-white/20 rounded-xl transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-400 text-white rounded-xl transition-colors">
                    Save Members
                </button>
            </div>
        </div>

    @endif

</form>

</div>

@push('scripts')
<script>
function membersForm(initialSelected) {
    return {
        selected: initialSelected.map(Number),
        search: '',
        allIds: {{ $allVolunteers->pluck('id')->toJson() }},
        allNames: {!! $allVolunteers->mapWithKeys(fn($v) => [$v->id => strtolower($v->full_name . ' ' . ($v->role ?? ''))])->toJson() !!},

        get visibleCount() {
            if (! this.search.trim()) return this.allIds.length;
            const q = this.search.toLowerCase();
            return this.allIds.filter(id => (this.allNames[id] || '').includes(q)).length;
        },

        init() {},

        toggle(id) {
            const idx = this.selected.indexOf(id);
            if (idx === -1) {
                this.selected.push(id);
            } else {
                this.selected.splice(idx, 1);
            }
        },

        selectAll() {
            const q = this.search.toLowerCase().trim();
            if (q) {
                // Only select visible filtered items
                this.allIds.forEach(id => {
                    if ((this.allNames[id] || '').includes(q) && !this.selected.includes(id)) {
                        this.selected.push(id);
                    }
                });
            } else {
                this.selected = [...this.allIds];
            }
        },

        clearAll() {
            this.selected = [];
        },

        matchesSearch(name, role) {
            if (! this.search.trim()) return true;
            const q = this.search.toLowerCase();
            return name.includes(q) || role.includes(q);
        },
    };
}
</script>
@endpush

@endsection
