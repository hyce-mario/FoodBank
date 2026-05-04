@php
    $initGroupId      = old('volunteer_group_id', $event->volunteer_group_id ?? '');
    $initNotes        = old('notes', $event->notes ?? '');
    $initVolunteerIds = old('volunteer_ids',
        isset($event) ? $event->assignedVolunteers->pluck('id')->toArray() : []
    );
    $initVolunteerIdsInt = array_map('intval', (array) $initVolunteerIds);
    $allVolunteersMapped = $allVolunteers->map(fn($v) => [
        'id'   => $v->id,
        'name' => $v->full_name,
        'role' => $v->role ?? '',
    ]);
@endphp

{{-- All PHP data passed safely inside a script block (never in HTML attributes) --}}
@push('scripts')
<script>
window.__eventFormData = {
    groupId:      @json($initGroupId ?: null),
    groupData:    @json($groupMap),
    volunteerIds: @json($initVolunteerIdsInt),
    allVolunteers: @json($allVolunteersMapped),
};

function eventForm() {
    const cfg = window.__eventFormData;
    return {
        groupId:      cfg.groupId ? String(cfg.groupId) : '',
        groupData:    cfg.groupData,
        volunteerIds: cfg.volunteerIds,
        allVolunteers: cfg.allVolunteers,
        wordCount:    0,

        get groupVolunteers() {
            if (!this.groupId) return [];
            return this.groupData[this.groupId] || [];
        },

        init() {
            const notes = this.$refs.notesHidden.value;
            if (notes) {
                this.$refs.editor.innerHTML = notes;
                this.wordCount = this.countWords(this.$refs.editor.innerText);
            }
            const form = this.$el.closest('form');
            if (form) {
                form.addEventListener('submit', () => {
                    this.$refs.notesHidden.value = this.$refs.editor.innerHTML;
                });
            }
        },

        exec(command, value = null) {
            document.execCommand(command, false, value);
            this.$refs.editor.focus();
        },

        execLink() {
            const url = prompt('Enter URL:');
            if (url) document.execCommand('createLink', false, url);
            this.$refs.editor.focus();
        },

        countWords(text) {
            return text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
        },
    };
}

function volunteerMultiSelect() {
    const cfg = window.__eventFormData;
    return {
        open:       false,
        search:     '',
        selected:   cfg.volunteerIds.map(Number),
        volunteers: cfg.allVolunteers,

        get filtered() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.volunteers;
            return this.volunteers.filter(v =>
                v.name.toLowerCase().includes(q) ||
                (v.role && v.role.toLowerCase().includes(q))
            );
        },

        get selectedVolunteers() {
            return this.volunteers.filter(v => this.selected.includes(v.id));
        },

        toggle(id) {
            const idx = this.selected.indexOf(id);
            if (idx === -1) this.selected.push(id);
            else this.selected.splice(idx, 1);
        },

        remove(id) {
            this.selected = this.selected.filter(s => s !== id);
        },

        isSelected(id) {
            return this.selected.includes(id);
        },

        init() {
            this.$watch('open', val => {
                if (val) this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
            });
        },
    };
}
</script>
<style>
[contenteditable]:empty:before {
    content: attr(data-placeholder);
    color: #9ca3af;
    pointer-events: none;
    display: block;
}
</style>
@endpush

<div x-data="eventForm()">

{{-- Card --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Card header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div class="flex items-center gap-2.5">
            <div class="w-6 h-6 rounded-full bg-brand-500 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd"/>
                </svg>
            </div>
            <span class="text-sm font-semibold text-gray-800">Event Information</span>
        </div>
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
    </div>

    {{-- Form body --}}
    <div class="px-6 py-6 space-y-5">

        {{-- Row 1: Event Name | Date --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Event Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="name" name="name"
                       value="{{ old('name', $event->name ?? '') }}"
                       placeholder="February Foodbank"
                       class="w-full px-3.5 py-2.5 text-sm border bg-white rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-300
                              {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }}">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="date" name="date"
                       value="{{ old('date', isset($event) ? $event->date->format('Y-m-d') : '') }}"
                       class="w-full px-3.5 py-2.5 text-sm border bg-white rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              {{ $errors->has('date') ? 'border-red-400' : 'border-gray-300' }}">
                @error('date')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Row 2: Location | Ruleset + Lanes --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Location <span class="text-red-500">*</span>
                </label>
                <input type="text" id="location" name="location"
                       value="{{ old('location', $event->location ?? '') }}"
                       placeholder="Living Spring, Harrisburg"
                       class="w-full px-3.5 py-2.5 text-sm border bg-white rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-300
                              {{ $errors->has('location') ? 'border-red-400' : 'border-gray-300' }}">
                @error('location')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div class="space-y-4">
                <div>
                    <label for="ruleset_id" class="block text-sm font-medium text-gray-700 mb-1.5">Allocation Ruleset</label>
                    <div class="relative">
                        <select id="ruleset_id" name="ruleset_id"
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-300 bg-white rounded-lg appearance-none
                                       focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                       {{ $errors->has('ruleset_id') ? 'border-red-400' : '' }}">
                            <option value="">— No ruleset —</option>
                            @foreach ($rulesets as $rs)
                                <option value="{{ $rs->id }}"
                                    {{ (string) old('ruleset_id', $event->ruleset_id ?? '') === (string) $rs->id ? 'selected' : '' }}>
                                    {{ $rs->name }}
                                    @if(!$rs->is_active) (inactive) @endif
                                    — {{ $rs->allocation_type === 'family_count' ? 'by families' : 'by household size' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                    </div>
                    @error('ruleset_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="lanes" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Lanes <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="lanes" name="lanes" min="1" max="20"
                           value="{{ old('lanes', $event->lanes ?? 1) }}"
                           class="w-full px-3.5 py-2.5 text-sm border bg-white rounded-lg
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  {{ $errors->has('lanes') ? 'border-red-400' : 'border-gray-300' }}">
                    @error('lanes')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Row 3: Group(s) | Assign Volunteer(s) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">

            {{-- Group selector + volunteer preview --}}
            <div>
                <label for="volunteer_group_id" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Group(s)
                </label>
                <div class="relative">
                    <select id="volunteer_group_id" name="volunteer_group_id"
                            x-model="groupId"
                            class="w-full px-3.5 py-2.5 text-sm border border-gray-300 bg-white rounded-lg appearance-none
                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                   {{ $errors->has('volunteer_group_id') ? 'border-red-400' : '' }}">
                        <option value="">-- Select a Group --</option>
                        @foreach ($allGroups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </div>
                </div>
                @error('volunteer_group_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror

                {{-- Group volunteers panel --}}
                <div x-show="groupVolunteers.length > 0"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-3 border border-gray-200 rounded-lg p-4 bg-gray-50"
                     style="display:none;">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                        Volunteers in this group
                    </p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5">
                        <template x-for="vol in groupVolunteers" :key="vol.id">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded flex-shrink-0 border-2 bg-brand-500 border-brand-500 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                    </svg>
                                </span>
                                <span class="text-sm text-gray-700 truncate" x-text="vol.name"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Empty state --}}
                <div x-show="groupId && groupVolunteers.length === 0"
                     class="mt-3 border border-dashed border-gray-200 rounded-lg p-4 text-center"
                     style="display:none;">
                    <p class="text-xs text-gray-400">No volunteers in this group yet.</p>
                </div>
            </div>

            {{-- Assign Volunteer(s) multi-select --}}
            <div x-data="volunteerMultiSelect()" class="relative">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-gray-700">Assign Volunteer(s)</label>
                    <a href="{{ route('volunteers.create') }}" target="_blank"
                       class="inline-flex items-center gap-1 text-xs font-semibold text-brand-500 hover:text-brand-600 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Add New
                    </a>
                </div>

                {{-- Hidden inputs for submission --}}
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="volunteer_ids[]" :value="id">
                </template>

                {{-- Trigger button --}}
                <div class="relative">
                    <button type="button"
                            @click="open = !open"
                            @keydown.escape.window="open = false"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 text-sm border border-gray-300 bg-white rounded-lg text-left
                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition-colors">
                        <span :class="selected.length === 0 ? 'text-gray-400' : 'text-gray-700'"
                              x-text="selected.length === 0
                                ? 'Select volunteers...'
                                : selected.length + ' volunteer' + (selected.length === 1 ? '' : 's') + ' selected'">
                        </span>
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform duration-150"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>

                    {{-- Dropdown panel --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click.outside="open = false"
                         class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                         style="display:none;">

                        {{-- Search --}}
                        <div class="p-2 border-b border-gray-100">
                            <input type="text" x-model="search" x-ref="searchInput" @click.stop
                                   placeholder="Search volunteers..."
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                          placeholder:text-gray-400">
                        </div>

                        {{-- List --}}
                        <ul class="max-h-52 overflow-y-auto py-1">
                            <template x-for="vol in filtered" :key="vol.id">
                                <li>
                                    <button type="button" @click.stop="toggle(vol.id)"
                                            class="w-full flex items-center gap-3 px-3 py-2.5 text-sm hover:bg-gray-50 transition-colors text-left">
                                        <span class="w-4 h-4 flex-shrink-0 rounded border-2 flex items-center justify-center transition-colors"
                                              :class="isSelected(vol.id) ? 'bg-brand-500 border-brand-500' : 'border-gray-300'">
                                            <svg x-show="isSelected(vol.id)" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                            </svg>
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="font-medium text-gray-900" x-text="vol.name"></span>
                                            <span x-show="vol.role" class="ml-1.5 text-xs text-gray-400" x-text="'· ' + vol.role"></span>
                                        </span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="filtered.length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">
                                No volunteers found.
                            </li>
                        </ul>

                        {{-- Footer --}}
                        <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
                            <span class="text-xs text-gray-400" x-text="selected.length + ' selected'"></span>
                            <button type="button" @click.stop="selected = []; open = false"
                                    x-show="selected.length > 0"
                                    class="text-xs font-semibold text-red-500 hover:text-red-600 transition-colors">
                                Clear all
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Selected chips --}}
                <div x-show="selected.length > 0" class="flex flex-wrap gap-1.5 mt-2" style="display:none;">
                    <template x-for="vol in selectedVolunteers" :key="vol.id">
                        <span class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-1 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                            <span x-text="vol.name"></span>
                            <button type="button" @click.stop="remove(vol.id)"
                                    class="w-3.5 h-3.5 flex items-center justify-center rounded-full hover:bg-brand-200 transition-colors">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </span>
                    </template>
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes (Optional)</label>

            {{-- Toolbar --}}
            <div class="flex items-center gap-0.5 px-3 py-2 border border-gray-300 border-b-0 rounded-t-lg bg-gray-50">
                <button type="button" @click="exec('bold')" title="Bold"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 font-bold text-sm transition-colors">B</button>
                <button type="button" @click="exec('italic')" title="Italic"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 italic text-sm transition-colors">I</button>
                <button type="button" @click="exec('underline')" title="Underline"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 underline text-sm transition-colors">U</button>
                <button type="button" @click="exec('strikeThrough')" title="Strikethrough"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 line-through text-sm transition-colors">S</button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <button type="button" @click="execLink()" title="Link"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                    </svg>
                </button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <button type="button" @click="exec('insertOrderedList')" title="Ordered list"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                </button>
                <button type="button" @click="exec('insertUnorderedList')" title="Unordered list"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                </button>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <button type="button" @click="exec('formatBlock', 'blockquote')" title="Blockquote"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                    </svg>
                </button>
                <button type="button" @click="exec('formatBlock', 'pre')" title="Code"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 text-gray-600 font-mono text-xs transition-colors">&lt;/&gt;</button>
            </div>

            {{-- Editor --}}
            <div x-ref="editor"
                 contenteditable="true"
                 @input="wordCount = countWords($refs.editor.innerText)"
                 class="min-h-[160px] px-4 py-3 text-sm text-gray-700 border border-gray-300 rounded-b-lg bg-white
                        focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                        prose prose-sm max-w-none"
                 data-placeholder="Special instructions or notes for the event...">
            </div>
            <textarea name="notes" x-ref="notesHidden" class="sr-only">{{ $initNotes }}</textarea>

            <div class="flex items-center justify-between mt-1.5">
                <p class="text-xs text-gray-400">Maximum 400 Words</p>
                <p class="text-xs" :class="wordCount > 400 ? 'text-red-500 font-semibold' : 'text-gray-400'">
                    <span x-text="wordCount">0</span> / 400 words
                </p>
            </div>
        </div>

    </div>
</div>

</div>
