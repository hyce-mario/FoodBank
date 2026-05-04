@extends('layouts.app')

@section('title', 'Allocation Rules')

@section('content')

@php
    $rulesetsJson = $rulesets->map(fn($r) => [
        'id'                 => $r->id,
        'name'               => $r->name,
        'allocation_type'    => $r->allocation_type ?? 'household_size',
        'description'        => $r->description ?? '',
        'is_active'          => $r->is_active,
        'max_household_size' => $r->max_household_size,
        'rules'              => $r->rules ?? [],
        'updated_at'         => $r->updated_at?->diffForHumans() ?? '',
    ]);
@endphp

<div x-data="allocationRules()">
<div class="flex gap-6">

    {{-- ─── LEFT: Rulesets List ──────────────────────────────────────────── --}}
    <div class="flex-1 min-w-0">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Allocation Rules</h1>
                <p class="text-sm text-gray-500 mt-0.5">Define how many food bags households receive based on size.</p>
            </div>
            <button @click="openCreate()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                New Ruleset
            </button>
        </div>

        {{-- Flash --}}
        @if(session('success'))
            <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
                <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- Ruleset Cards --}}
        @if($rulesets->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
                </svg>
                <p class="text-gray-500 font-medium">No rulesets yet</p>
                <p class="text-gray-400 text-sm mt-1">Create your first allocation ruleset to get started.</p>
                <button @click="openCreate()" class="mt-4 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Create Ruleset
                </button>
            </div>
        @else
            <div class="space-y-4">
                @foreach($rulesets as $ruleset)
                @php
                    $rules = $ruleset->rules ?? [];
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    {{-- Card Header --}}
                    <div class="flex items-start justify-between px-5 pt-4 pb-3 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-semibold text-gray-900">{{ $ruleset->name }}</h3>
                                    @if($ruleset->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                                    @endif
                                    @if(($ruleset->allocation_type ?? 'household_size') === 'family_count')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">By Families</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">By Household Size</span>
                                    @endif
                                </div>
                                @if($ruleset->description)
                                    <p class="text-sm text-gray-500 mt-0.5">{{ $ruleset->description }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0 ml-4">
                            <button @click="openEdit({{ $ruleset->id }})"
                                    class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                </svg>
                            </button>
                            <button type="button"
                                    @click="openDelete({{ $ruleset->id }}, '{{ addslashes($ruleset->name) }}')"
                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Rules Table --}}
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Distribution Rules</p>
                            <p class="text-xs text-gray-400">Max household: {{ $ruleset->max_household_size }}</p>
                        </div>
                        @if(count($rules) > 0)
                        @php $isByFamily = ($ruleset->allocation_type ?? 'household_size') === 'family_count'; @endphp
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-400 border-b border-gray-100">
                                    <th class="text-left pb-1.5 font-medium">{{ $isByFamily ? 'No. of Families' : 'Household Size' }}</th>
                                    <th class="text-center pb-1.5 font-medium">Bags</th>
                                    <th class="text-right pb-1.5 font-medium pr-2">Example</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($rules as $rule)
                                <tr>
                                    <td class="py-1.5 text-gray-700">
                                        @if($isByFamily)
                                            {{ \App\Models\AllocationRuleset::ruleLabel($rule, 'family') }}
                                        @else
                                            {{ \App\Models\AllocationRuleset::ruleLabel($rule) }}
                                        @endif
                                    </td>
                                    <td class="py-1.5 text-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 bg-brand-50 text-brand-700 font-bold text-sm rounded-lg">
                                            {{ $rule['bags'] }}
                                        </span>
                                    </td>
                                    <td class="py-1.5 text-right pr-2 text-gray-400 text-xs">
                                        @php $ex = $rule['min']; @endphp
                                        {{ $ex }} {{ $isByFamily ? ($ex === 1 ? 'family' : 'families') : ($ex === 1 ? 'person' : 'people') }} → {{ $rule['bags'] }} {{ $rule['bags'] === 1 ? 'bag' : 'bags' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @else
                            <p class="text-sm text-gray-400 italic">No rules defined.</p>
                        @endif
                    </div>

                    {{-- Card Footer --}}
                    <div class="px-5 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <p class="text-xs text-gray-400">Updated {{ $ruleset->updated_at?->diffForHumans() ?? '—' }}</p>
                        <button @click="selectForPreview({{ $ruleset->id }})"
                                class="text-xs text-brand-600 hover:text-brand-700 font-medium">
                            Preview →
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ─── RIGHT: Allocation Preview Sidebar ────────────────────────────── --}}
    <div class="w-72 flex-shrink-0">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm sticky top-6">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Allocation Preview</h2>
                <p class="text-xs text-gray-400 mt-0.5">Test a ruleset to see bag allocations.</p>
            </div>

            <div class="px-5 py-4 space-y-4">
                {{-- Ruleset Selector --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Ruleset</label>
                    <select x-model="previewRulesetId" @change="previewRulesetId = Number($event.target.value)"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">— Select a ruleset —</option>
                        @foreach($rulesets as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Household Size --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Household Size</label>
                    <div class="flex items-center gap-2">
                        <button @click="previewSize = Math.max(1, previewSize - 1)"
                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition text-lg font-bold">−</button>
                        <input type="number" x-model.number="previewSize" min="1" max="20"
                               class="flex-1 text-center text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500"/>
                        <button @click="previewSize = Math.min(20, previewSize + 1)"
                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition text-lg font-bold">+</button>
                    </div>
                </div>

                {{-- Result --}}
                <div x-show="previewRulesetId" style="display:none">
                    <div class="bg-brand-50 border border-brand-100 rounded-xl p-4 text-center">
                        <p class="text-xs text-brand-600 font-medium mb-1">Bags Allocated</p>
                        <p class="text-5xl font-extrabold text-brand-700" x-text="previewBags"></p>
                        <p class="text-xs text-brand-500 mt-1"
                           x-text="'for ' + previewSize + ' ' + (previewRuleset && previewRuleset.allocation_type === 'family_count' ? (previewSize === 1 ? 'family' : 'families') : (previewSize === 1 ? 'person' : 'people'))"></p>
                    </div>
                </div>

                <div x-show="!previewRulesetId" class="bg-gray-50 rounded-xl p-4 text-center text-sm text-gray-400">
                    Select a ruleset above to preview allocations.
                </div>

                {{-- Quick Test Buttons --}}
                <div x-show="previewRulesetId" style="display:none">
                    <p class="text-xs font-medium text-gray-500 mb-2">Quick Test</p>
                    <div class="grid grid-cols-4 gap-1.5">
                        <template x-for="n in [1,2,3,4,5,6,7,8]" :key="n">
                            <button @click="previewSize = n"
                                    :class="previewSize === n ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 border-gray-300 hover:border-brand-400'"
                                    class="py-1.5 text-xs font-semibold border rounded-lg transition"
                                    x-text="n"></button>
                        </template>
                    </div>
                </div>

                {{-- All-sizes breakdown --}}
                <div x-show="previewRulesetId && previewBreakdown.length > 0" style="display:none" class="border-t border-gray-100 pt-4">
                    <p class="text-xs font-medium text-gray-500 mb-2">All Sizes</p>
                    <div class="space-y-1">
                        <template x-for="row in previewBreakdown" :key="row.size">
                            <div class="flex items-center justify-between text-xs"
                                 :class="row.size === previewSize ? 'font-semibold text-brand-700' : 'text-gray-600'">
                                <span x-text="row.size + ' ' + (previewRuleset && previewRuleset.allocation_type === 'family_count' ? (row.size === 1 ? 'family' : 'families') : (row.size === 1 ? 'person' : 'people'))"></span>
                                <span class="flex items-center gap-1">
                                    <span x-text="row.bags + (row.bags === 1 ? ' bag' : ' bags')"></span>
                                    <span x-show="row.size === previewSize" class="w-1.5 h-1.5 rounded-full bg-brand-500 inline-block"></span>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{-- end .flex.gap-6 --}}

{{-- ─── Create / Edit Modal ──────────────────────────────────────────────── --}}
<div x-show="showModal" style="display:none"
     class="fixed inset-0 z-50 flex items-start justify-center pt-10 px-4 pb-10"
     @keydown.escape.window="closeModal()">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeModal()"></div>

    {{-- Modal Panel --}}
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh]" @click.stop>

        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900" x-text="editingId ? 'Edit Ruleset' : 'Create New Ruleset'"></h2>
            <button @click="closeModal()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ruleset Name <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.name" maxlength="100" placeholder="e.g. Standard Distribution"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"/>
                <template x-if="errors.name">
                    <p class="text-red-500 text-xs mt-1" x-text="errors.name"></p>
                </template>
            </div>

            {{-- Description + Active --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <textarea x-model="form.description" rows="2" maxlength="500"
                              placeholder="Optional description..."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Household Size</label>
                    <input type="number" x-model.number="form.max_household_size" min="1" max="99"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"/>
                    <p class="text-xs text-gray-400 mt-1">Max people per household</p>
                </div>
            </div>

            {{-- Allocation Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Allocate By <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="form.allocation_type = 'household_size'"
                            :class="form.allocation_type === 'household_size'
                                ? 'border-brand-500 bg-brand-50 text-brand-700 ring-2 ring-brand-200'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                            class="flex flex-col items-start gap-1 px-4 py-3 border-2 rounded-xl transition text-left">
                        <span class="font-semibold text-sm">Household Size</span>
                        <span class="text-xs opacity-70">Rules based on number of people</span>
                    </button>
                    <button type="button" @click="form.allocation_type = 'family_count'"
                            :class="form.allocation_type === 'family_count'
                                ? 'border-purple-500 bg-purple-50 text-purple-700 ring-2 ring-purple-200'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                            class="flex flex-col items-start gap-1 px-4 py-3 border-2 rounded-xl transition text-left">
                        <span class="font-semibold text-sm">No. of Families</span>
                        <span class="text-xs opacity-70">Rules based on number of families</span>
                    </button>
                </div>
            </div>

            {{-- Active Toggle --}}
            <div class="flex items-center gap-3">
                <button @click="form.is_active = !form.is_active"
                        :class="form.is_active ? 'bg-brand-600' : 'bg-gray-200'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                    <span :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"></span>
                </button>
                <span class="text-sm text-gray-700" x-text="form.is_active ? 'Active — shown in check-in' : 'Inactive — hidden from check-in'"></span>
            </div>

            {{-- Rules --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="text-sm font-medium text-gray-700">Distribution Rules <span class="text-red-500">*</span></label>
                    <button @click="addRule()" type="button"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Add Row
                    </button>
                </div>

                {{-- Header row --}}
                <div class="grid grid-cols-[1fr_1fr_80px_32px] gap-2 mb-1.5 px-1">
                    <p class="text-xs font-medium text-gray-400" x-text="form.allocation_type === 'family_count' ? 'Min Families' : 'Min People'">Min People</p>
                    <p class="text-xs font-medium text-gray-400" x-text="form.allocation_type === 'family_count' ? 'Max Families' : 'Max People'">Max People</p>
                    <p class="text-xs font-medium text-gray-400 text-center">Bags</p>
                    <div></div>
                </div>

                <div class="space-y-2">
                    <template x-for="(rule, idx) in form.rules" :key="idx">
                        <div class="grid grid-cols-[1fr_1fr_80px_32px] gap-2 items-center">
                            {{-- Min --}}
                            <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                                <button @click="rule.min = Math.max(1, rule.min - 1)" type="button"
                                        class="px-2 py-2 text-gray-500 hover:bg-gray-50 transition text-sm font-bold">−</button>
                                <input type="number" x-model.number="rule.min" min="1"
                                       class="flex-1 text-center text-sm py-2 border-0 focus:outline-none focus:ring-0 min-w-0"/>
                                <button @click="rule.min = rule.min + 1" type="button"
                                        class="px-2 py-2 text-gray-500 hover:bg-gray-50 transition text-sm font-bold">+</button>
                            </div>
                            {{-- Max --}}
                            <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                                <button @click="rule.max = rule.max === null ? 1 : Math.max(1, rule.max - 1)" type="button"
                                        class="px-2 py-2 text-gray-500 hover:bg-gray-50 transition text-sm font-bold">−</button>
                                <input type="text" :value="rule.max === null ? '∞' : rule.max"
                                       @input="rule.max = $event.target.value === '' || $event.target.value === '∞' ? null : parseInt($event.target.value)"
                                       class="flex-1 text-center text-sm py-2 border-0 focus:outline-none focus:ring-0 min-w-0"
                                       placeholder="∞"/>
                                <button @click="rule.max = rule.max === null ? null : (rule.max + 1)" type="button"
                                        class="px-2 py-2 text-gray-500 hover:bg-gray-50 transition text-sm font-bold">+</button>
                            </div>
                            {{-- Bags --}}
                            <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                                <button @click="rule.bags = Math.max(0, rule.bags - 1)" type="button"
                                        class="px-2 py-2 text-gray-500 hover:bg-gray-50 transition text-sm font-bold">−</button>
                                <input type="number" x-model.number="rule.bags" min="0"
                                       class="flex-1 text-center text-sm py-2 border-0 focus:outline-none focus:ring-0 min-w-0"/>
                                <button @click="rule.bags = rule.bags + 1" type="button"
                                        class="px-2 py-2 text-gray-500 hover:bg-gray-50 transition text-sm font-bold">+</button>
                            </div>
                            {{-- Remove --}}
                            <button @click="removeRule(idx)" type="button" :disabled="form.rules.length <= 1"
                                    :class="form.rules.length <= 1 ? 'text-gray-200 cursor-not-allowed' : 'text-gray-400 hover:text-red-500'"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                <p class="text-xs text-gray-400 mt-2">Set Max to ∞ (blank) for "and above" open-ended ranges.</p>
            </div>

            {{-- Live Preview --}}
            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Live Preview</p>
                    <p class="text-xs text-gray-400"
                       x-text="form.allocation_type === 'family_count' ? '1–10 families' : '1–10 people'"></p>
                </div>
                <div class="grid grid-cols-5 gap-2">
                    <template x-for="n in [1,2,3,4,5,6,7,8,9,10]" :key="n">
                        <div class="flex flex-col items-center bg-white border border-gray-200 rounded-lg py-2 px-1">
                            <span class="text-xs text-gray-400" x-text="n"></span>
                            <span class="text-lg font-bold text-brand-700 mt-0.5" x-text="calcBagsFor(n)"></span>
                            <span class="text-[10px] text-gray-400">bags</span>
                        </div>
                    </template>
                </div>
            </div>

        </div>

        {{-- Modal Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button @click="closeModal()" type="button"
                    class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
                Cancel
            </button>
            <button @click="submitForm()" type="button" :disabled="submitting"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="editingId ? 'Save Changes' : 'Create Ruleset'"></span>
            </button>
        </div>
    </div>
</div>

{{-- Hidden forms for submit --}}
<form id="create-form" method="POST" action="{{ route('allocation-rulesets.store') }}" style="display:none">
    @csrf
    <div id="create-form-fields"></div>
</form>
<form id="edit-form" method="POST" action="" style="display:none">
    @csrf @method('PUT')
    <div id="edit-form-fields"></div>
</form>
<form id="delete-form" method="POST" action="" style="display:none">
    @csrf @method('DELETE')
</form>

{{-- ─── Delete Confirmation Modal ───────────────────────────────────────────── --}}
<div x-show="deleteOpen"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="deleteOpen = false" @keydown.escape.window="deleteOpen = false"
     style="display:none;">

    <div x-show="deleteOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center">

        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
            </svg>
        </div>

        <h2 class="text-base font-bold text-gray-900 mb-2">Delete Ruleset</h2>
        <p class="text-sm text-gray-500 mb-1 leading-relaxed">
            You are about to delete
        </p>
        <p class="text-sm font-semibold text-gray-800 mb-4" x-text='"' + deleteName + '"'></p>
        <p class="text-xs text-gray-400 mb-6">This cannot be undone. Any events linked to this ruleset will lose their allocation rules.</p>

        <div class="flex items-center gap-3">
            <button @click="deleteOpen = false"
                    class="flex-1 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Cancel
            </button>
            <button @click="submitDelete()"
                    class="flex-1 py-2.5 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">
                Delete
            </button>
        </div>
    </div>
</div>

</div>
{{-- end x-data="allocationRules()" --}}

@endsection

@push('scripts')
<script>
window.__allocationData = {
    rulesets: @json($rulesetsJson),
    updateBaseUrl: '{{ url("allocation-rulesets") }}',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
};

function allocationRules() {
    const cfg = window.__allocationData;

    return {
        // ── Preview sidebar ──────────────────────────────────────────────────
        previewRulesetId: null,
        previewSize: 1,
        get previewRuleset() {
            return cfg.rulesets.find(r => r.id === this.previewRulesetId) ?? null;
        },
        get previewBags() {
            if (!this.previewRuleset) return '—';
            return this.calcBagsForRuleset(this.previewRuleset, this.previewSize);
        },
        get previewBreakdown() {
            if (!this.previewRuleset) return [];
            const maxSize = this.previewRuleset.max_household_size || 10;
            const out = [];
            for (let i = 1; i <= Math.min(maxSize, 12); i++) {
                out.push({ size: i, bags: this.calcBagsForRuleset(this.previewRuleset, i) });
            }
            return out;
        },
        selectForPreview(id) {
            this.previewRulesetId = id;
        },
        calcBagsForRuleset(ruleset, size) {
            for (const rule of (ruleset.rules ?? [])) {
                const min = parseInt(rule.min ?? 1);
                const max = rule.max !== null && rule.max !== undefined ? parseInt(rule.max) : Infinity;
                if (size >= min && size <= max) return parseInt(rule.bags ?? 0);
            }
            return 0;
        },

        // ── Delete modal ─────────────────────────────────────────────────────
        deleteOpen: false,
        deleteId:   null,
        deleteName: '',
        openDelete(id, name) {
            this.deleteId   = id;
            this.deleteName = name;
            this.deleteOpen = true;
        },
        submitDelete() {
            const form = document.getElementById('delete-form');
            form.action = cfg.updateBaseUrl + '/' + this.deleteId;
            form.submit();
        },

        // ── Create / Edit modal ───────────────────────────────────────────────
        showModal: false,
        editingId: null,
        submitting: false,
        errors: {},
        form: {
            name: '',
            allocation_type: 'household_size',
            description: '',
            is_active: true,
            max_household_size: 20,
            rules: [{ min: 1, max: null, bags: 1 }],
        },

        openCreate() {
            this.editingId = null;
            this.errors = {};
            this.form = {
                name: '',
                allocation_type: 'household_size',
                description: '',
                is_active: true,
                max_household_size: 20,
                rules: [
                    { min: 1, max: 1,    bags: 1 },
                    { min: 2, max: 3,    bags: 2 },
                    { min: 4, max: 6,    bags: 3 },
                    { min: 7, max: null, bags: 4 },
                ],
            };
            this.showModal = true;
        },
        openEdit(id) {
            const rs = cfg.rulesets.find(r => r.id === id);
            if (!rs) return;
            this.editingId = id;
            this.errors = {};
            this.form = {
                name:               rs.name,
                allocation_type:    rs.allocation_type ?? 'household_size',
                description:        rs.description ?? '',
                is_active:          rs.is_active,
                max_household_size: rs.max_household_size,
                rules:              JSON.parse(JSON.stringify(rs.rules ?? [])),
            };
            if (!this.form.rules.length) {
                this.form.rules = [{ min: 1, max: null, bags: 1 }];
            }
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },

        addRule() {
            const last = this.form.rules[this.form.rules.length - 1];
            const newMin = last ? (last.max !== null ? parseInt(last.max) + 1 : parseInt(last.min) + 1) : 1;
            this.form.rules.push({ min: newMin, max: null, bags: 1 });
        },
        removeRule(idx) {
            if (this.form.rules.length <= 1) return;
            this.form.rules.splice(idx, 1);
        },

        // Live preview inside modal
        calcBagsFor(size) {
            for (const rule of this.form.rules) {
                const min = parseInt(rule.min ?? 1);
                const max = rule.max !== null && rule.max !== undefined && rule.max !== '' ? parseInt(rule.max) : Infinity;
                if (size >= min && size <= max) return parseInt(rule.bags ?? 0);
            }
            return 0;
        },

        submitForm() {
            // Validate
            this.errors = {};
            if (!this.form.name.trim()) {
                this.errors.name = 'Name is required.';
                return;
            }

            this.submitting = true;

            const fields = this.buildFormFields();
            const isEdit = !!this.editingId;
            const form = document.getElementById(isEdit ? 'edit-form' : 'create-form');

            if (isEdit) {
                form.action = cfg.updateBaseUrl + '/' + this.editingId;
            }

            const container = document.getElementById(isEdit ? 'edit-form-fields' : 'create-form-fields');
            container.innerHTML = fields;
            form.submit();
        },

        buildFormFields() {
            const f = this.form;
            let html = '';
            html += `<input name="name" value="${this.esc(f.name)}">`;
            html += `<input name="allocation_type" value="${this.esc(f.allocation_type ?? 'household_size')}">`;
            html += `<input name="description" value="${this.esc(f.description ?? '')}">`;
            html += `<input name="is_active" value="${f.is_active ? '1' : '0'}">`;
            html += `<input name="max_household_size" value="${f.max_household_size}">`;
            f.rules.forEach((rule, i) => {
                html += `<input name="rules[${i}][min]" value="${parseInt(rule.min)}">`;
                if (rule.max !== null && rule.max !== undefined && rule.max !== '') {
                    html += `<input name="rules[${i}][max]" value="${parseInt(rule.max)}">`;
                }
                html += `<input name="rules[${i}][bags]" value="${parseInt(rule.bags)}">`;
            });
            return html;
        },

        esc(str) {
            return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        },
    };
}
</script>
@endpush
