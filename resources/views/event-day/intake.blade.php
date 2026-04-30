@extends('layouts.event-day')

@section('title', 'Intake — ' . $event->name)

@section('header')
<header class="bg-blue-600 shrink-0 px-4 py-4 flex items-center justify-between shadow-md">
    <div>
        <p class="text-xs text-blue-200 uppercase tracking-widest font-semibold">Intake Station</p>
        <h1 class="text-white text-xl font-black leading-tight mt-0.5">{{ $event->name }}</h1>
        <p class="text-blue-200 text-xs mt-0.5">
            {{ $event->date->format('l, F j') }}{{ $event->location ? ' · ' . $event->location : '' }}
        </p>
    </div>
    <span id="ed-clock" class="text-white text-sm font-bold tabular-nums">{{ now()->format('g:i A') }}</span>
</header>
@endsection

@push('styles')
<style>
@keyframes scan-line { 0%{top:10%} 50%{top:85%} 100%{top:10%} }
.animate-scan-line { animation: scan-line 2s ease-in-out infinite; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
@endpush

@section('content')
<div x-data="intakePage()" x-init="init()" class="p-4 max-w-lg mx-auto space-y-4">

    {{-- Lane selector --}}
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 flex items-center gap-3">
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wide shrink-0">Lane</span>
        <div class="flex flex-wrap gap-2">
            @for ($l = 1; $l <= $event->lanes; $l++)
            <button @click="lane = {{ $l }}" type="button"
                    :class="lane == {{ $l }} ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="text-sm font-bold px-4 py-2 rounded-xl transition-colors">
                {{ $l }}
            </button>
            @endfor
        </div>
    </div>

    {{-- ── Search row ──────────────────────────────────────────────────────── --}}
    <div x-show="!showQr">
        <div class="flex gap-2">
            <div class="relative flex-1" @click.outside="showDropdown = false">
                <div class="flex items-center border-2 rounded-2xl bg-white transition-all"
                     :class="showDropdown && results.length ? 'border-blue-500' : 'border-gray-200'">
                    <svg class="w-4 h-4 text-gray-400 ml-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.197 5.197a7.5 7.5 0 0 0 10.606 10.606Z"/>
                    </svg>
                    <input type="text" x-model="query"
                           @input.debounce.300ms="doSearch()"
                           @focus="showDropdown = results.length > 0"
                           @keydown.escape="showDropdown = false"
                           @keydown.enter.prevent="results.length === 1 && selectHousehold(results[0])"
                           placeholder="Search by code, name or phone"
                           class="flex-1 min-w-0 py-3.5 px-2.5 text-sm text-gray-800 placeholder-gray-400 bg-transparent focus:outline-none">
                    <span x-show="searching" style="display:none" class="mr-3">
                        <svg class="w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </span>
                    <button x-show="query && !searching" style="display:none"
                            @click="clearSelection()" type="button" class="mr-3 text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Results dropdown --}}
                <div x-show="showDropdown && (results.length > 0 || (searched && query))"
                     style="display:none"
                     class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-gray-200 rounded-2xl shadow-xl z-30 overflow-hidden">
                    <template x-for="h in results" :key="h.id">
                        <button @click="selectHousehold(h)" type="button"
                                class="w-full flex items-start gap-3 px-4 py-3.5 hover:bg-blue-50 text-left border-b border-gray-100 last:border-0 transition-colors">
                            <span class="text-xs font-mono text-gray-400 w-14 shrink-0 pt-0.5" x-text="'#' + h.household_number"></span>
                            <span class="flex-1 min-w-0">
                                <span class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-sm font-bold text-gray-900 truncate" x-text="h.full_name"></span>
                                    <span x-show="h.is_pre_registered" style="display:none"
                                          class="inline-flex items-center text-[10px] font-bold text-teal-700 bg-teal-50 border border-teal-200 rounded px-1.5 py-0.5 shrink-0">
                                        Pre-Reg
                                    </span>
                                </span>
                                <span class="text-xs font-semibold text-gray-500" x-show="vehicleLabel(h)" style="display:none" x-text="vehicleLabel(h)"></span>
                            </span>
                            {{-- Family tag — hover-only inside this button row --}}
                            <span x-data="{ showDemo: false }"
                                  @mouseenter="showDemo = true"
                                  @mouseleave="showDemo = false"
                                  class="relative text-xs text-gray-400 shrink-0 mt-0.5 cursor-help">
                                <span>1 Family</span>
                                <span x-show="showDemo" style="display:none"
                                      x-transition:enter="transition ease-out duration-150"
                                      x-transition:enter-start="opacity-0 translate-y-1"
                                      x-transition:enter-end="opacity-100 translate-y-0"
                                      class="absolute right-0 top-full mt-1 z-30 min-w-32
                                             bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                                    <span class="block text-sm font-semibold text-gray-900 mb-2"
                                          x-text="(h.household_size ?? 0) + ' ' + ((h.household_size ?? 0) === 1 ? 'Member' : 'Members')"></span>
                                    <span class="block text-xs text-gray-600">
                                        <span class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span>
                                            <span class="font-semibold text-gray-800" x-text="h.children_count ?? 0"></span>
                                            <span x-text="(h.children_count ?? 0) === 1 ? 'Child' : 'Children'"></span>
                                        </span>
                                        <span class="flex items-center gap-2 mt-1">
                                            <span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span>
                                            <span class="font-semibold text-gray-800" x-text="h.adults_count ?? 0"></span>
                                            <span x-text="(h.adults_count ?? 0) === 1 ? 'Adult' : 'Adults'"></span>
                                        </span>
                                        <span class="flex items-center gap-2 mt-1">
                                            <span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span>
                                            <span class="font-semibold text-gray-800" x-text="h.seniors_count ?? 0"></span>
                                            <span x-text="(h.seniors_count ?? 0) === 1 ? 'Senior' : 'Seniors'"></span>
                                        </span>
                                    </span>
                                </span>
                            </span>
                        </button>
                    </template>
                    <div x-show="searched && results.length === 0" style="display:none"
                         class="px-4 py-4 text-sm text-gray-500 text-center">No households found</div>
                </div>
            </div>

            {{-- QR --}}
            <button @click="startQr()" type="button"
                    class="w-12 h-12 shrink-0 flex items-center justify-center rounded-2xl border-2 border-gray-200 bg-white text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75V16.5ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 18.75h.75v.75h-.75v-.75ZM18.75 13.5h.75v.75h-.75v-.75ZM18.75 18.75h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75V16.5Z"/>
                </svg>
            </button>

            {{-- Quick-add --}}
            <button @click="toggleAddForm()" type="button"
                    :class="showAddForm ? 'bg-gray-100 text-gray-700 border-2 border-gray-200' : 'bg-blue-600 text-white border-2 border-blue-600'"
                    class="shrink-0 flex items-center gap-1 px-3.5 h-12 rounded-2xl font-bold text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="showAddForm ? 'M6 18 18 6M6 6l12 12' : 'M12 4.5v15m7.5-7.5h-15'"/>
                </svg>
                <span x-text="showAddForm ? 'Cancel' : 'Add'"></span>
            </button>
        </div>
    </div>

    {{-- ── QR Scanner ───────────────────────────────────────────────────────── --}}
    <div x-show="showQr" style="display:none" class="space-y-3">
        <button @click="stopQr()" type="button"
                class="flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>Back to search
        </button>
        <div class="relative bg-gray-800 rounded-2xl overflow-hidden aspect-square max-w-xs mx-auto shadow-xl">
            <video id="qr-video" class="w-full h-full object-cover" playsinline muted></video>
            <canvas id="qr-canvas" class="hidden absolute inset-0"></canvas>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="relative w-52 h-52">
                    <span class="absolute top-0 left-0 w-7 h-7 border-t-[3px] border-l-[3px] border-white rounded-tl-md"></span>
                    <span class="absolute top-0 right-0 w-7 h-7 border-t-[3px] border-r-[3px] border-white rounded-tr-md"></span>
                    <span class="absolute bottom-0 left-0 w-7 h-7 border-b-[3px] border-l-[3px] border-white rounded-bl-md"></span>
                    <span class="absolute bottom-0 right-0 w-7 h-7 border-b-[3px] border-r-[3px] border-white rounded-br-md"></span>
                    <div class="absolute left-2 right-2 h-0.5 bg-blue-400/80 animate-scan-line"></div>
                </div>
            </div>
            <div x-show="qrError" style="display:none"
                 class="absolute inset-0 flex items-center justify-center bg-gray-900/90 text-white p-6 text-center">
                <p class="text-sm font-semibold" x-text="qrError"></p>
            </div>
        </div>
        <p class="text-center text-xs text-gray-500">Point camera at household QR code</p>
    </div>

    {{-- ── Quick-Add form ───────────────────────────────────────────────────── --}}
    <div x-show="showAddForm && !showQr" style="display:none"
         class="bg-white rounded-2xl border-2 border-blue-200 p-4 space-y-4">
        <h3 class="text-base font-black text-gray-900">Add New Household</h3>

        {{-- Name --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1.5">First Name *</label>
                <input type="text" x-model="addForm.first_name"
                       :class="addErrors.first_name ? 'border-red-400' : 'border-gray-200'"
                       class="w-full border-2 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                <p x-show="addErrors.first_name" style="display:none"
                   class="text-xs text-red-500 mt-0.5" x-text="addErrors.first_name?.[0]"></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1.5">Last Name *</label>
                <input type="text" x-model="addForm.last_name"
                       :class="addErrors.last_name ? 'border-red-400' : 'border-gray-200'"
                       class="w-full border-2 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                <p x-show="addErrors.last_name" style="display:none"
                   class="text-xs text-red-500 mt-0.5" x-text="addErrors.last_name?.[0]"></p>
            </div>
        </div>

        {{-- Phone + Email --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1.5">Phone</label>
                <input type="tel" x-model="addForm.phone"
                       class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1.5">Email</label>
                <input type="email" x-model="addForm.email"
                       class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
            </div>
        </div>

        {{-- Household Composition --}}
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1.5">
                Household Composition *
                <span class="font-normal text-gray-400 ml-1">
                    (total: <span x-text="addForm.children_count + addForm.adults_count + addForm.seniors_count"></span> people)
                </span>
            </label>
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl px-2 py-2 text-center">
                    <p class="text-[10px] font-bold text-blue-600 mb-1">Children &lt;18</p>
                    <input type="number" min="0" max="50" x-model.number="addForm.children_count"
                           class="w-full text-center py-1.5 text-lg font-black border-2 border-blue-200 rounded-lg bg-white
                                  focus:outline-none focus:border-blue-500">
                </div>
                <div class="bg-green-50 border-2 border-green-200 rounded-xl px-2 py-2 text-center">
                    <p class="text-[10px] font-bold text-green-700 mb-1">Adults 18–64</p>
                    <input type="number" min="0" max="50" x-model.number="addForm.adults_count"
                           class="w-full text-center py-1.5 text-lg font-black border-2 border-green-200 rounded-lg bg-white
                                  focus:outline-none focus:border-green-500">
                </div>
                <div class="bg-purple-50 border-2 border-purple-200 rounded-xl px-2 py-2 text-center">
                    <p class="text-[10px] font-bold text-purple-700 mb-1">Seniors 65+</p>
                    <input type="number" min="0" max="50" x-model.number="addForm.seniors_count"
                           class="w-full text-center py-1.5 text-lg font-black border-2 border-purple-200 rounded-lg bg-white
                                  focus:outline-none focus:border-purple-500">
                </div>
            </div>
        </div>

        {{-- Vehicle --}}
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1.5">Vehicle (optional)</label>
            <div class="grid grid-cols-2 gap-3">
                <input type="text" x-model="addForm.vehicle_color" placeholder="Color"
                       class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                <input type="text" x-model="addForm.vehicle_make" placeholder="Make / Model"
                       class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
            </div>
        </div>

        <p x-show="addErrors._global" style="display:none" class="text-xs text-red-500 font-medium" x-text="addErrors._global"></p>

        <button @click="quickAddCreate()" type="button" :disabled="addLoading"
                class="w-full py-4 rounded-2xl bg-blue-600 text-white font-black text-base transition-colors disabled:opacity-40 hover:bg-blue-700 active:bg-blue-800">
            <span x-text="addLoading ? 'Creating…' : 'Create Household →'"></span>
        </button>
    </div>

    {{-- ── Selected household card ──────────────────────────────────────────── --}}
    <div x-show="selected && !showAddForm && !showQr" style="display:none"
         class="bg-white rounded-2xl border-2 border-blue-500 p-4 space-y-3">

        {{-- Header --}}
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-mono text-gray-400" x-text="'#' + selected?.household_number"></p>
                <div class="flex items-center gap-2 mt-0.5">
                    <p class="text-xl font-black text-gray-900" x-text="selected?.full_name"></p>
                    <span x-show="selected?.is_pre_registered" style="display:none"
                          class="inline-flex items-center text-[10px] font-bold text-teal-700 bg-teal-50 border border-teal-200 rounded px-1.5 py-0.5 shrink-0">
                        Pre-Reg
                    </span>
                </div>
                <div class="flex gap-4 text-sm text-gray-600 font-medium mt-1">
                    {{-- Family tag — runtime live count (selected + linked); reveals
                         the selected household's demographic breakdown on hover/click. --}}
                    <div x-data="{ showDemo: false }" class="relative">
                        <button @mouseenter="showDemo = true"
                                @mouseleave="showDemo = false"
                                @click="showDemo = !showDemo"
                                @focus="showDemo = true"
                                @blur="showDemo = false"
                                type="button"
                                class="hover:text-gray-900 transition-colors">
                            <span x-text="(1 + linkedHouseholds.length) + ' ' + ((1 + linkedHouseholds.length) === 1 ? 'Family' : 'Families')"></span>
                        </button>
                        <div x-show="showDemo" style="display:none"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute top-full left-0 mt-2 z-30 min-w-32
                                    bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                            <p class="text-sm font-semibold text-gray-900 mb-2"
                               x-text="(selected?.household_size ?? 0) + ' ' + ((selected?.household_size ?? 0) === 1 ? 'Member' : 'Members')"></p>
                            <ul class="text-xs text-gray-600 space-y-1">
                                <li class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span>
                                    <span class="font-semibold text-gray-800" x-text="selected?.children_count ?? 0"></span>
                                    <span x-text="(selected?.children_count ?? 0) === 1 ? 'Child' : 'Children'"></span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span>
                                    <span class="font-semibold text-gray-800" x-text="selected?.adults_count ?? 0"></span>
                                    <span x-text="(selected?.adults_count ?? 0) === 1 ? 'Adult' : 'Adults'"></span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span>
                                    <span class="font-semibold text-gray-800" x-text="selected?.seniors_count ?? 0"></span>
                                    <span x-text="(selected?.seniors_count ?? 0) === 1 ? 'Senior' : 'Seniors'"></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <button @click="clearSelection()" type="button" class="text-gray-400 hover:text-gray-600 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Vehicle section --}}
        <div class="pt-3 border-t border-gray-100">
            <div x-show="!vehicleEditMode" class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 text-sm">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                    </svg>
                    <template x-if="vehicleLabel(selected)">
                        <span class="font-semibold text-gray-700" x-text="vehicleLabel(selected)"></span>
                    </template>
                    <template x-if="!vehicleLabel(selected)">
                        <span class="text-gray-400 italic text-xs">No vehicle on file</span>
                    </template>
                </div>
                <button @click="openVehicleEdit()" type="button"
                        class="text-xs font-bold text-blue-600 hover:text-blue-700 shrink-0">
                    <span x-text="vehicleLabel(selected) ? 'Edit' : '+ Add vehicle'"></span>
                </button>
            </div>

            <div x-show="vehicleEditMode" style="display:none" class="space-y-2">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Color</label>
                        <input type="text" x-model="vehicleEditColor" placeholder="Silver"
                               class="w-full border-2 border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Make</label>
                        <input type="text" x-model="vehicleEditMake" placeholder="Toyota"
                               class="w-full border-2 border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button @click="saveVehicleEdit()" type="button"
                            :disabled="vehicleEditSaving"
                            class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold
                                   transition-colors disabled:opacity-60">
                        <span x-text="vehicleEditSaving ? 'Saving…' : 'Save'"></span>
                    </button>
                    <button @click="vehicleEditMode = false" type="button"
                            class="px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-bold transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        {{-- Represented Pickups --}}
        <div class="pt-3 border-t border-gray-100">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Represented Pickups</p>
                <div class="flex items-center gap-2">
                    <button @click="showAttachSearch = !showAttachSearch; attachQuery = ''; attachResults = []"
                            type="button"
                            :class="showAttachSearch ? 'text-gray-500' : 'text-blue-600 hover:text-blue-700'"
                            class="text-xs font-bold transition-colors">
                        <span x-text="showAttachSearch ? '✕ Close' : '+ Attach'"></span>
                    </button>
                    <span class="text-gray-300 text-xs select-none">|</span>
                    <button @click="showCreatePanel = true; showAttachSearch = false"
                            type="button"
                            class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors">
                        + Create new
                    </button>
                </div>
            </div>

            {{-- Attach-existing search --}}
            <div x-show="showAttachSearch" style="display:none" class="mb-2.5">
                <div class="relative">
                    <input type="text" x-model="attachQuery"
                           @input.debounce.300ms="doAttachSearch()"
                           placeholder="Search by name, code or phone…"
                           class="w-full border-2 border-gray-200 rounded-xl px-3 py-2.5 text-sm
                                  focus:outline-none focus:border-blue-500 pr-9">
                    <span x-show="attachSearching" style="display:none"
                          class="absolute right-3 top-1/2 -translate-y-1/2">
                        <svg class="w-4 h-4 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </span>
                </div>
                <template x-if="attachResults.length > 0">
                    <div class="mt-1.5 bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <template x-for="h in attachResults" :key="h.id">
                            <button @click="attachExisting(h)" type="button"
                                    class="w-full flex items-center gap-2 px-3 py-3 hover:bg-blue-50
                                           text-left border-b border-gray-100 last:border-0 transition-colors">
                                <span class="font-mono text-xs text-gray-400 w-12 shrink-0"
                                      x-text="'#' + h.household_number"></span>
                                <span class="flex-1 min-w-0 text-sm font-semibold text-gray-800 truncate"
                                      x-text="h.full_name"></span>
                                {{-- Family tag — hover-only inside this button row --}}
                                <span x-data="{ showDemo: false }"
                                      @mouseenter="showDemo = true"
                                      @mouseleave="showDemo = false"
                                      class="relative text-xs text-gray-500 shrink-0 cursor-help">
                                    <span>1 Family</span>
                                    <span x-show="showDemo" style="display:none"
                                          x-transition:enter="transition ease-out duration-150"
                                          x-transition:enter-start="opacity-0 translate-y-1"
                                          x-transition:enter-end="opacity-100 translate-y-0"
                                          class="absolute right-0 top-full mt-1 z-30 min-w-32
                                                 bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                                        <span class="block text-sm font-semibold text-gray-900 mb-2"
                                              x-text="(h.household_size ?? 0) + ' ' + ((h.household_size ?? 0) === 1 ? 'Member' : 'Members')"></span>
                                        <span class="block text-xs text-gray-600">
                                            <span class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span>
                                                <span class="font-semibold text-gray-800" x-text="h.children_count ?? 0"></span>
                                                <span x-text="(h.children_count ?? 0) === 1 ? 'Child' : 'Children'"></span>
                                            </span>
                                            <span class="flex items-center gap-2 mt-1">
                                                <span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span>
                                                <span class="font-semibold text-gray-800" x-text="h.adults_count ?? 0"></span>
                                                <span x-text="(h.adults_count ?? 0) === 1 ? 'Adult' : 'Adults'"></span>
                                            </span>
                                            <span class="flex items-center gap-2 mt-1">
                                                <span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span>
                                                <span class="font-semibold text-gray-800" x-text="h.seniors_count ?? 0"></span>
                                                <span x-text="(h.seniors_count ?? 0) === 1 ? 'Senior' : 'Seniors'"></span>
                                            </span>
                                        </span>
                                    </span>
                                </span>
                            </button>
                        </template>
                    </div>
                </template>
                <p x-show="attachQuery.trim() && !attachSearching && attachResults.length === 0"
                   style="display:none"
                   class="text-xs text-gray-400 text-center py-2">No households found</p>
            </div>

            {{-- Linked list --}}
            <template x-if="linkedHouseholds.length === 0">
                <p class="text-xs text-gray-400 italic py-1">No additional households — single-household check-in.</p>
            </template>
            <template x-for="h in linkedHouseholds" :key="h.id">
                <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-100 rounded-xl px-2.5 py-2 mb-1.5">
                    <span class="text-amber-400 text-xs shrink-0">↳</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-amber-900 truncate" x-text="h.full_name"></p>
                        <p class="text-[10px] text-amber-600 font-mono" x-text="'#' + h.household_number"></p>
                    </div>
                    {{-- Family tag — hover + click toggle (chip parent is a div) --}}
                    <span x-data="{ showDemo: false }"
                          @mouseenter="showDemo = true"
                          @mouseleave="showDemo = false"
                          @click.stop="showDemo = !showDemo"
                          class="relative text-xs text-amber-700 shrink-0 cursor-help">
                        <span>1 Family</span>
                        <span x-show="showDemo" style="display:none"
                              x-transition:enter="transition ease-out duration-150"
                              x-transition:enter-start="opacity-0 translate-y-1"
                              x-transition:enter-end="opacity-100 translate-y-0"
                              class="absolute right-0 top-full mt-1 z-30 min-w-32
                                     bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                            <span class="block text-sm font-semibold text-gray-900 mb-2"
                                  x-text="(h.household_size ?? 0) + ' ' + ((h.household_size ?? 0) === 1 ? 'Member' : 'Members')"></span>
                            <span class="block text-xs text-gray-600">
                                <span class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span>
                                    <span class="font-semibold text-gray-800" x-text="h.children_count ?? 0"></span>
                                    <span x-text="(h.children_count ?? 0) === 1 ? 'Child' : 'Children'"></span>
                                </span>
                                <span class="flex items-center gap-2 mt-1">
                                    <span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span>
                                    <span class="font-semibold text-gray-800" x-text="h.adults_count ?? 0"></span>
                                    <span x-text="(h.adults_count ?? 0) === 1 ? 'Adult' : 'Adults'"></span>
                                </span>
                                <span class="flex items-center gap-2 mt-1">
                                    <span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span>
                                    <span class="font-semibold text-gray-800" x-text="h.seniors_count ?? 0"></span>
                                    <span x-text="(h.seniors_count ?? 0) === 1 ? 'Senior' : 'Seniors'"></span>
                                </span>
                            </span>
                        </span>
                    </span>
                    <span x-show="h.bags_needed !== null && h.bags_needed !== undefined"
                          style="display:none"
                          class="text-xs font-bold text-orange-600 shrink-0"
                          x-text="'· ' + h.bags_needed + ' bags'"></span>
                    <button @click="removeLinked(h.id)" type="button"
                            class="ml-1 text-gray-400 hover:text-red-500 shrink-0 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <p x-show="checkInError" style="display:none" class="text-xs text-red-500 font-medium" x-text="checkInError"></p>

        <button @click="doCheckIn()" type="button" :disabled="checkingIn"
                class="w-full py-4 rounded-2xl bg-blue-600 text-white font-black text-lg transition-colors disabled:opacity-40 hover:bg-blue-700 active:bg-blue-800">
            <span x-show="checkingIn" style="display:none">Checking in…</span>
            <span x-show="!checkingIn" style="display:none"
                  x-text="linkedHouseholds.length > 0
                      ? 'Check In Group (' + (linkedHouseholds.length + 1) + ') → Lane ' + lane
                      : 'Check In → Lane ' + lane">
            </span>
        </button>
    </div>

    {{-- ── Grouped pickup summary ───────────────────────────────────────────── --}}
    <div x-show="selected && linkedHouseholds.length > 0 && !showAddForm && !showQr"
         style="display:none"
         class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
        <p class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-2.5">Grouped Pickup Summary</p>

        <div class="flex items-center gap-2 py-1 text-sm">
            <span class="w-4 shrink-0 text-center text-amber-300 font-bold">★</span>
            <span class="flex-1 font-bold text-gray-900" x-text="selected?.full_name"></span>
            {{-- Family tag for the representative --}}
            <span x-data="{ showDemo: false }"
                  @mouseenter="showDemo = true"
                  @mouseleave="showDemo = false"
                  @click="showDemo = !showDemo"
                  class="relative text-gray-600 text-xs shrink-0 cursor-help">
                <span>1 Family</span>
                <span x-show="showDemo" style="display:none"
                      x-transition:enter="transition ease-out duration-150"
                      x-transition:enter-start="opacity-0 translate-y-1"
                      x-transition:enter-end="opacity-100 translate-y-0"
                      class="absolute right-0 top-full mt-1 z-30 min-w-32
                             bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                    <span class="block text-sm font-semibold text-gray-900 mb-2"
                          x-text="(selected?.household_size ?? 0) + ' ' + ((selected?.household_size ?? 0) === 1 ? 'Member' : 'Members')"></span>
                    <span class="block text-xs text-gray-600">
                        <span class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span>
                            <span class="font-semibold text-gray-800" x-text="selected?.children_count ?? 0"></span>
                            <span x-text="(selected?.children_count ?? 0) === 1 ? 'Child' : 'Children'"></span>
                        </span>
                        <span class="flex items-center gap-2 mt-1">
                            <span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span>
                            <span class="font-semibold text-gray-800" x-text="selected?.adults_count ?? 0"></span>
                            <span x-text="(selected?.adults_count ?? 0) === 1 ? 'Adult' : 'Adults'"></span>
                        </span>
                        <span class="flex items-center gap-2 mt-1">
                            <span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span>
                            <span class="font-semibold text-gray-800" x-text="selected?.seniors_count ?? 0"></span>
                            <span x-text="(selected?.seniors_count ?? 0) === 1 ? 'Senior' : 'Seniors'"></span>
                        </span>
                    </span>
                </span>
            </span>
            <span x-show="selected?.bags_needed != null" style="display:none"
                  class="text-orange-600 text-xs font-bold shrink-0"
                  x-text="selected?.bags_needed + ' bags'"></span>
        </div>

        <template x-for="h in linkedHouseholds" :key="h.id">
            <div class="flex items-center gap-2 py-1 text-xs">
                <span class="w-4 shrink-0 text-center text-amber-400">↳</span>
                <span class="flex-1 font-medium text-gray-800" x-text="h.full_name"></span>
                {{-- Family tag for each linked household --}}
                <span x-data="{ showDemo: false }"
                      @mouseenter="showDemo = true"
                      @mouseleave="showDemo = false"
                      @click="showDemo = !showDemo"
                      class="relative text-gray-500 shrink-0 cursor-help">
                    <span>1 Family</span>
                    <span x-show="showDemo" style="display:none"
                          x-transition:enter="transition ease-out duration-150"
                          x-transition:enter-start="opacity-0 translate-y-1"
                          x-transition:enter-end="opacity-100 translate-y-0"
                          class="absolute right-0 top-full mt-1 z-30 min-w-32
                                 bg-white border border-gray-200 rounded-xl shadow-lg p-3 text-left">
                        <span class="block text-sm font-semibold text-gray-900 mb-2"
                              x-text="(h.household_size ?? 0) + ' ' + ((h.household_size ?? 0) === 1 ? 'Member' : 'Members')"></span>
                        <span class="block text-xs text-gray-600">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-sm bg-blue-500 shrink-0"></span>
                                <span class="font-semibold text-gray-800" x-text="h.children_count ?? 0"></span>
                                <span x-text="(h.children_count ?? 0) === 1 ? 'Child' : 'Children'"></span>
                            </span>
                            <span class="flex items-center gap-2 mt-1">
                                <span class="w-2 h-2 rounded-sm bg-green-500 shrink-0"></span>
                                <span class="font-semibold text-gray-800" x-text="h.adults_count ?? 0"></span>
                                <span x-text="(h.adults_count ?? 0) === 1 ? 'Adult' : 'Adults'"></span>
                            </span>
                            <span class="flex items-center gap-2 mt-1">
                                <span class="w-2 h-2 rounded-sm bg-amber-500 shrink-0"></span>
                                <span class="font-semibold text-gray-800" x-text="h.seniors_count ?? 0"></span>
                                <span x-text="(h.seniors_count ?? 0) === 1 ? 'Senior' : 'Seniors'"></span>
                            </span>
                        </span>
                    </span>
                </span>
                <span x-show="h.bags_needed != null" style="display:none"
                      class="text-orange-600 font-bold shrink-0"
                      x-text="h.bags_needed + ' bags'"></span>
            </div>
        </template>

        <div class="mt-2.5 pt-2.5 border-t border-amber-200 flex items-center justify-between">
            <span class="text-xs font-semibold text-amber-700">
                Total · <span x-text="linkedHouseholds.length + 1"></span> households
            </span>
            <div class="flex items-center gap-3">
                <span class="text-sm font-black text-gray-900"
                      x-text="totalLinkedPeople + ' people'"></span>
                <span x-show="totalLinkedBags > 0" style="display:none"
                      class="text-sm font-black text-orange-600"
                      x-text="totalLinkedBags + ' bags'"></span>
            </div>
        </div>
    </div>

    {{-- ── Toast ────────────────────────────────────────────────────────────── --}}
    <div x-show="toast.show" style="display:none"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         :class="toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'"
         class="fixed bottom-24 left-4 right-4 z-50 rounded-2xl px-5 py-4 text-sm font-bold shadow-xl text-center text-white"
         x-text="toast.message">
    </div>

    {{-- ── Recent check-ins log ─────────────────────────────────────────────── --}}
    <div x-show="log.length > 0" style="display:none">
        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Recent Check-ins</p>
        <div class="space-y-2">
            <template x-for="v in log" :key="v.id">
                <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <p class="text-sm font-bold text-gray-900 truncate"
                                   x-text="v.household?.full_name ?? 'Unknown'"></p>
                                <span x-show="v.is_representative_pickup" style="display:none"
                                      class="inline-flex items-center text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1 py-0.5 shrink-0">
                                    Rep. Pickup
                                </span>
                            </div>
                            <p class="text-sm font-bold text-blue-600 mt-0.5"
                               x-show="vehicleLabel(v.household)"
                               style="display:none"
                               x-text="vehicleLabel(v.household)"></p>
                            <p x-show="!vehicleLabel(v.household)" style="display:none"
                               class="text-xs text-gray-400 italic mt-0.5">No vehicle on file</p>
                            <p class="text-xs text-gray-400 mt-1"
                               x-text="'Lane ' + v.lane + ' · ' + formatTime(v.start_time)"></p>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <span class="text-xs font-mono text-gray-400"
                                  x-text="'#' + v.household?.household_number"></span>
                            <span x-show="v.active" style="display:none"
                                  class="text-xs text-blue-500 font-medium italic">Checked in</span>
                            <span x-show="!v.active" style="display:none"
                                  class="text-xs text-gray-400 italic">Served</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>


    {{-- ── Create Represented Household — bottom sheet ────────────────────── --}}
    {{-- Sits inside x-data="intakePage()" so all state is shared              --}}
    <div x-show="showCreatePanel" style="display:none"
         class="fixed inset-0 z-50 flex items-end justify-center">

        <div class="absolute inset-0 bg-black/50"
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showCreatePanel = false; resetCreateForm()"></div>

    <div class="relative z-10 bg-white w-full max-w-lg rounded-t-3xl shadow-2xl"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full">

        <div class="max-h-[90vh] overflow-y-auto p-5">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-base font-black text-gray-900">New Represented Household</h3>
                <button @click="showCreatePanel = false; resetCreateForm()" type="button"
                        class="text-gray-400 hover:text-gray-600 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <p class="text-xs text-gray-500 mb-4">
                Linked to: <span class="font-bold text-gray-700" x-text="selected?.full_name ?? '—'"></span>
            </p>

            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">First Name *</label>
                        <input type="text" x-model="createForm.first_name"
                               :class="createErrors.first_name ? 'border-red-400' : 'border-gray-200'"
                               class="w-full border-2 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                        <p x-show="createErrors.first_name" style="display:none"
                           class="text-xs text-red-500 mt-0.5" x-text="createErrors.first_name?.[0]"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Last Name *</label>
                        <input type="text" x-model="createForm.last_name"
                               :class="createErrors.last_name ? 'border-red-400' : 'border-gray-200'"
                               class="w-full border-2 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                        <p x-show="createErrors.last_name" style="display:none"
                           class="text-xs text-red-500 mt-0.5" x-text="createErrors.last_name?.[0]"></p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Phone</label>
                    <input type="tel" x-model="createForm.phone"
                           class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">
                        Household Composition *
                        <span class="font-normal text-gray-400 ml-1">
                            (total: <span x-text="createForm.children_count + createForm.adults_count + createForm.seniors_count"></span> ppl)
                        </span>
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-xl px-2 py-2 text-center">
                            <p class="text-[10px] font-bold text-blue-600 mb-1">Children</p>
                            <input type="number" min="0" max="50" x-model.number="createForm.children_count"
                                   class="w-full text-center py-1.5 text-lg font-black border-2 border-blue-200 rounded-lg bg-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="bg-green-50 border-2 border-green-200 rounded-xl px-2 py-2 text-center">
                            <p class="text-[10px] font-bold text-green-700 mb-1">Adults</p>
                            <input type="number" min="0" max="50" x-model.number="createForm.adults_count"
                                   class="w-full text-center py-1.5 text-lg font-black border-2 border-green-200 rounded-lg bg-white focus:outline-none focus:border-green-500">
                        </div>
                        <div class="bg-purple-50 border-2 border-purple-200 rounded-xl px-2 py-2 text-center">
                            <p class="text-[10px] font-bold text-purple-700 mb-1">Seniors</p>
                            <input type="number" min="0" max="50" x-model.number="createForm.seniors_count"
                                   class="w-full text-center py-1.5 text-lg font-black border-2 border-purple-200 rounded-lg bg-white focus:outline-none focus:border-purple-500">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Vehicle (optional)</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" x-model="createForm.vehicle_color" placeholder="Color"
                               class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                        <input type="text" x-model="createForm.vehicle_make" placeholder="Make / Model"
                               class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">Notes</label>
                    <textarea x-model="createForm.notes" rows="2"
                              class="w-full border-2 border-gray-200 rounded-xl px-3 py-3 text-sm
                                     focus:outline-none focus:border-blue-500 resize-none"></textarea>
                </div>

                <div class="flex gap-2 pt-1">
                    <button @click="submitCreateRepresented()" :disabled="createSaving" type="button"
                            class="flex-1 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-sm
                                   transition-colors disabled:opacity-60">
                        <span x-text="createSaving ? 'Saving…' : 'Create & Link Household'"></span>
                    </button>
                    <button @click="showCreatePanel = false; resetCreateForm()" type="button"
                            class="px-4 py-4 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>{{-- /showCreatePanel --}}

{{-- ════════════════════════════════════════════════════════════════════
     ALREADY-SERVED OVERRIDE MODAL  (mirrored from admin /checkin Phase 1.3.d)
     Triggered when /checkin returns 422 with error: 'household_already_served'.
     Two states:
       - allowOverride=true  → reason textarea + Confirm Override button.
                               On confirm, re-POST with force=1 + reason.
                               Server records an audit row in checkin_overrides.
       - allowOverride=false → close-only (deny policy mode).
═══════════════════════════════════════════════════════════════════════ --}}
<div x-show="overrideModal.show" style="display:none"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
     @keydown.escape.window="overrideModal.show && cancelOverride()">

    {{-- Backdrop. Click-outside cancels EXCEPT during submission — silent
         dismiss mid-flight would hide a validator error from the user. --}}
    <div class="absolute inset-0 bg-black/50"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="!overrideModal.submitting && cancelOverride()"></div>

    {{-- Panel — base max-w-md (sm:max-w-md responsive variant isn't in the
         prebuilt CSS; on mobile, w-full takes precedence for the bottom sheet). --}}
    <div class="relative z-10 bg-white w-full max-w-md rounded-t-3xl rounded-2xl shadow-2xl"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
         x-transition:enter-end="translate-y-0 sm:opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 sm:opacity-100"
         x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0">

        {{-- Header: icon + title together; household info on its own line below. --}}
        <div class="p-6 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900">Already served at this event</h3>
            </div>

            <template x-if="overrideModal.households.length === 1">
                <p class="text-sm text-gray-600 mt-3">
                    <span class="font-medium" x-text="overrideModal.households[0].full_name"></span>
                    <span class="text-gray-500" x-text="'(#' + overrideModal.households[0].household_number + ')'"></span>
                </p>
            </template>
            <template x-if="overrideModal.households.length > 1">
                <ul class="text-sm text-gray-600 mt-3 list-disc list-inside space-y-1">
                    <template x-for="h in overrideModal.households" :key="h.id">
                        <li>
                            <span class="font-medium" x-text="h.full_name"></span>
                            <span class="text-gray-500" x-text="'(#' + h.household_number + ')'"></span>
                        </li>
                    </template>
                </ul>
            </template>
        </div>

        {{-- Override-allowed body: reason textarea + Confirm button --}}
        <template x-if="overrideModal.allowOverride">
            <div class="px-6 pb-6 space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Reason for override <span class="text-red-500">*</span>
                    </label>
                    <textarea x-model="overrideModal.reason"
                              x-ref="reasonTextarea"
                              x-init="$watch('overrideModal.show', v => v && $nextTick(() => $refs.reasonTextarea?.focus()))"
                              :disabled="overrideModal.submitting"
                              rows="3"
                              maxlength="500"
                              placeholder="e.g., Forgotten item; supervisor confirmed."
                              class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                                     focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent
                                     resize-none"></textarea>
                    <p x-show="overrideModal.reasonError" style="display:none"
                       class="text-xs text-red-600 mt-1" x-text="overrideModal.reasonError"></p>
                </div>

                <div class="flex gap-2 pt-1">
                    <button @click="confirmOverride()"
                            :disabled="overrideModal.submitting || !overrideModal.reason.trim()"
                            type="button"
                            class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl
                                   bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm
                                   transition-colors disabled:opacity-60">
                        <template x-if="overrideModal.submitting">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                        </template>
                        <span x-text="overrideModal.submitting ? 'Saving…' : 'Confirm Override (Supervisor)'"></span>
                    </button>
                    <button @click="cancelOverride()"
                            :disabled="overrideModal.submitting"
                            type="button"
                            class="px-4 py-3 rounded-xl bg-gray-100 hover:bg-gray-200
                                   text-gray-700 font-semibold text-sm transition-colors disabled:opacity-60">
                        Cancel
                    </button>
                </div>
            </div>
        </template>

        {{-- Deny-policy body: close-only --}}
        <template x-if="!overrideModal.allowOverride">
            <div class="px-6 pb-6 space-y-3">
                <p class="text-sm text-gray-600">
                    The current re-check-in policy does not permit overrides. To allow re-check-ins,
                    an administrator can change the policy in
                    <span class="font-medium">Settings &rsaquo; Event &amp; Queue &rsaquo; Re-Check-In Policy</span>.
                </p>
                <div class="flex pt-1">
                    <button @click="cancelOverride()"
                            type="button"
                            class="flex-1 py-3 rounded-xl bg-navy-700 hover:bg-navy-800
                                   text-white font-semibold text-sm transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </template>

    </div>
</div>

</div>{{-- /intakePage --}}

@endsection

@push('scripts')
<script>
function intakePage() {
    return {
        eventId: {{ $event->id }},
        lane: 1,

        // ── Search ────────────────────────────────────────────────────────────
        query: '', results: [], searched: false, searching: false,
        showDropdown: false, selected: null,
        checkingIn: false, checkInError: '',

        // ── QR ────────────────────────────────────────────────────────────────
        showQr: false, qrError: '', qrStream: null, qrAnimFrame: null,

        // ── Quick-Add form ────────────────────────────────────────────────────
        showAddForm: false, addLoading: false, addErrors: {},
        addForm: {
            first_name: '', last_name: '', email: '', phone: '',
            children_count: 0, adults_count: 1, seniors_count: 0,
            vehicle_color: '', vehicle_make: '',
        },

        // ── Vehicle edit ──────────────────────────────────────────────────────
        vehicleEditMode: false, vehicleEditMake: '', vehicleEditColor: '', vehicleEditSaving: false,

        // ── Represented pickups ───────────────────────────────────────────────
        linkedHouseholds: [],
        showAttachSearch: false, attachQuery: '', attachResults: [], attachSearching: false,
        showCreatePanel: false,
        createForm: { first_name: '', last_name: '', phone: '', children_count: 0, adults_count: 1, seniors_count: 0, vehicle_make: '', vehicle_color: '', notes: '' },
        createErrors: {}, createSaving: false,

        // ── Log ───────────────────────────────────────────────────────────────
        log: [],

        // ── Override Modal ────────────────────────────────────────────────────
        // Triggered when /checkin returns 422 with error: 'household_already_served'.
        // pendingPayload holds the original request body so confirmOverride can
        // re-POST with force=1 + override_reason without rebuilding it.
        overrideModal: {
            show:           false,
            allowOverride:  true,
            households:     [],   // [{id, household_number, full_name}, ...]
            reason:         '',
            reasonError:    null,
            submitting:     false,
            pendingPayload: null,
        },

        // ── Toast ─────────────────────────────────────────────────────────────
        toast: { show: false, message: '', type: 'success' },

        // ── Computed ──────────────────────────────────────────────────────────
        get totalLinkedPeople() {
            const repSize = this.selected?.household_size ?? 0;
            return this.linkedHouseholds.reduce((s, h) => s + (h.household_size || 0), repSize);
        },
        get totalLinkedBags() {
            const repBags = this.selected?.bags_needed ?? 0;
            return this.linkedHouseholds.reduce((s, h) => s + (h.bags_needed || 0), repBags);
        },

        init() {
            this.fetchLog();
        },

        vehicleLabel(h) {
            if (!h) return null;
            const parts = [h.vehicle_color, h.vehicle_make].filter(Boolean);
            return parts.length ? parts.join(' ') : null;
        },

        // ── Search ────────────────────────────────────────────────────────────
        async doSearch() {
            if (!this.query.trim()) {
                this.results = []; this.showDropdown = false; this.searched = false;
                return;
            }
            this.searching = true;
            try {
                const r = await fetch(
                    `{{ url('/checkin/search') }}?q=${encodeURIComponent(this.query)}&event_id=${this.eventId}`,
                    { headers: { 'Accept': 'application/json' } }
                );
                const data = await r.json();
                this.results = data.results ?? [];
                this.searched = true;
                this.showDropdown = true;
            } catch {
                this.showToast('Search failed. Please try again.', 'error');
            } finally {
                this.searching = false;
            }
        },

        selectHousehold(h) {
            this.selected = h;
            this.query = h.full_name;
            this.showDropdown = false;
            this.checkInError = '';
            this.showAddForm = false;
            this.linkedHouseholds = (h.represented_households || []).map(r => ({ ...r }));
            this.showAttachSearch = false;
            this.attachQuery = '';
            this.attachResults = [];
            this.showCreatePanel = false;
        },

        clearSelection() {
            this.selected = null;
            this.query = '';
            this.results = [];
            this.showDropdown = false;
            this.searched = false;
            this.checkInError = '';
            this.vehicleEditMode = false;
            this.linkedHouseholds = [];
            this.showAttachSearch = false;
            this.attachQuery = '';
            this.attachResults = [];
            this.showCreatePanel = false;
        },

        // ── Add form ──────────────────────────────────────────────────────────
        toggleAddForm() {
            this.showAddForm = !this.showAddForm;
            this.addErrors = {};
            if (this.showAddForm) {
                this.clearSelection();
            } else {
                this.addForm = { first_name: '', last_name: '', email: '', phone: '', children_count: 0, adults_count: 1, seniors_count: 0, vehicle_color: '', vehicle_make: '' };
            }
        },

        // Creates household record only → drops into selected-household card so
        // staff can add represented pickups before clicking "Check In".
        async quickAddCreate() {
            this.addLoading = true;
            this.addErrors = {};
            try {
                const r = await fetch("{{ url('/checkin/quick-create') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ event_id: this.eventId, ...this.addForm }),
                });
                const data = await r.json();
                if (r.ok) {
                    this.showAddForm = false;
                    this.addForm = { first_name: '', last_name: '', email: '', phone: '', children_count: 0, adults_count: 1, seniors_count: 0, vehicle_color: '', vehicle_make: '' };
                    this.selectHousehold(data.household);
                    this.showToast('Household created — add pickups or check in.', 'success');
                } else if (r.status === 422) {
                    this.addErrors = data.errors || {};
                    if (data.message && !Object.keys(data.errors || {}).length) {
                        this.addErrors._global = data.message;
                    }
                } else {
                    this.showToast(data.message || 'Failed to create household', 'error');
                }
            } catch {
                this.showToast('Network error. Please try again.', 'error');
            } finally {
                this.addLoading = false;
            }
        },

        // ── Vehicle edit ──────────────────────────────────────────────────────
        openVehicleEdit() {
            this.vehicleEditMake  = this.selected?.vehicle_make  || '';
            this.vehicleEditColor = this.selected?.vehicle_color || '';
            this.vehicleEditMode  = true;
        },

        async saveVehicleEdit() {
            if (!this.selected) return;
            this.vehicleEditSaving = true;
            try {
                const r = await fetch(`{{ url('/checkin/households') }}/${this.selected.id}/vehicle`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        vehicle_make:  this.vehicleEditMake  || null,
                        vehicle_color: this.vehicleEditColor || null,
                    }),
                });
                const data = await r.json();
                if (r.ok) {
                    this.selected.vehicle_make  = data.household.vehicle_make;
                    this.selected.vehicle_color = data.household.vehicle_color;
                    this.vehicleEditMode = false;
                    this.showToast('Vehicle info saved!', 'success');
                } else {
                    this.showToast('Failed to save vehicle info', 'error');
                }
            } catch {
                this.showToast('Network error', 'error');
            } finally {
                this.vehicleEditSaving = false;
            }
        },

        // ── Attach existing represented household ─────────────────────────────
        async doAttachSearch() {
            if (!this.attachQuery.trim() || !this.selected) {
                this.attachResults = [];
                return;
            }
            this.attachSearching = true;
            try {
                const url = `{{ url('/checkin/represented/search') }}?q=${encodeURIComponent(this.attachQuery)}&representative_id=${this.selected.id}&event_id=${this.eventId}`;
                const r    = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                const linkedIds = new Set(this.linkedHouseholds.map(h => h.id));
                this.attachResults = (data.results || []).filter(h => !linkedIds.has(h.id));
            } catch {
                this.showToast('Search failed', 'error');
            } finally {
                this.attachSearching = false;
            }
        },

        async attachExisting(h) {
            try {
                const r = await fetch("{{ url('/checkin/represented/attach') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        representative_id: this.selected.id,
                        household_id: h.id,
                        event_id: this.eventId,
                    }),
                });
                const data = await r.json();
                if (r.ok) {
                    this.linkedHouseholds.push(data.household);
                    this.attachResults = this.attachResults.filter(x => x.id !== h.id);
                    this.attachQuery = '';
                    this.showAttachSearch = false;
                    this.showToast(data.household.full_name + ' linked!', 'success');
                } else {
                    this.showToast(data.message || 'Could not attach household', 'error');
                }
            } catch {
                this.showToast('Network error', 'error');
            }
        },

        // ── Create new represented household ──────────────────────────────────
        async submitCreateRepresented() {
            if (!this.selected) return;
            this.createSaving = true;
            this.createErrors = {};
            try {
                const r = await fetch("{{ url('/checkin/represented/create') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        representative_id: this.selected.id,
                        event_id: this.eventId,
                        ...this.createForm,
                    }),
                });
                const data = await r.json();
                if (r.ok) {
                    this.linkedHouseholds.push(data.household);
                    this.showCreatePanel = false;
                    this.resetCreateForm();
                    this.showToast('Household created & linked!', 'success');
                } else if (r.status === 422) {
                    this.createErrors = data.errors || {};
                } else {
                    this.showToast(data.message || 'Failed to create household', 'error');
                }
            } catch {
                this.showToast('Network error', 'error');
            } finally {
                this.createSaving = false;
            }
        },

        removeLinked(id) {
            this.linkedHouseholds = this.linkedHouseholds.filter(h => h.id !== id);
        },

        resetCreateForm() {
            this.createForm = { first_name: '', last_name: '', phone: '', children_count: 0, adults_count: 1, seniors_count: 0, vehicle_make: '', vehicle_color: '', notes: '' };
            this.createErrors = {};
        },

        // ── Check-in ──────────────────────────────────────────────────────────
        async doCheckIn() {
            if (!this.selected) return;
            this.checkingIn = true;
            this.checkInError = '';
            try {
                const body = {
                    event_id:     this.eventId,
                    household_id: this.selected.id,
                    lane:         this.lane,
                };
                if (this.linkedHouseholds.length > 0) {
                    body.represented_ids = this.linkedHouseholds.map(h => h.id);
                }
                const r = await fetch("{{ url('/checkin') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
                const data = await r.json();
                if (r.ok) {
                    const msg = this.linkedHouseholds.length > 0
                        ? `Group of ${this.linkedHouseholds.length + 1} checked in to Lane ${this.lane}!`
                        : `${this.selected.full_name} checked in to Lane ${this.lane}`;
                    this.showToast(msg, 'success');
                    this.clearSelection();
                    this.fetchLog();
                } else if (r.status === 422 && data.error === 'household_already_served') {
                    // Re-check-in policy fired. Open the override modal so the
                    // supervisor can confirm + give a reason. Capture body so
                    // confirmOverride() can re-POST with force=1 + reason.
                    const offending = Array.isArray(data.households) ? data.households : [];
                    if (offending.length === 0) {
                        this.checkInError = data.message ?? 'Already served at this event.';
                    } else {
                        this.overrideModal = {
                            show:           true,
                            allowOverride:  !!data.allow_override,
                            households:     offending,
                            reason:         '',
                            reasonError:    null,
                            submitting:     false,
                            pendingPayload: body,
                        };
                    }
                } else {
                    this.checkInError = data.message ?? 'Check-in failed.';
                }
            } catch {
                this.checkInError = 'Network error. Please try again.';
            } finally {
                this.checkingIn = false;
            }
        },

        // Re-POST the captured check-in body with force=1 + reason. Server
        // logs an audit row in checkin_overrides and proceeds with the visit.
        async confirmOverride() {
            if (this.overrideModal.submitting) return;
            const reason = this.overrideModal.reason.trim();
            if (!reason) {
                this.overrideModal.reasonError = 'Please enter a reason.';
                return;
            }
            if (!this.overrideModal.pendingPayload) {
                this.overrideModal.reasonError = 'Lost the original check-in details. Please cancel and try again.';
                return;
            }
            this.overrideModal.submitting  = true;
            this.overrideModal.reasonError = null;
            try {
                const body = {
                    ...this.overrideModal.pendingPayload,
                    force:           1,
                    override_reason: reason,
                };
                const r = await fetch("{{ url('/checkin') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
                const data = await r.json();
                if (r.ok) {
                    this.cancelOverride();
                    this.showToast('Override recorded. Check-in successful.', 'success');
                    this.clearSelection();
                    this.fetchLog();
                } else {
                    const validatorMsg = data.errors?.override_reason?.[0];
                    this.overrideModal.reasonError =
                        validatorMsg || data.message || 'Override failed. Please try again.';
                }
            } catch {
                this.overrideModal.reasonError = 'Network error. Please try again.';
            }
            this.overrideModal.submitting = false;
        },

        cancelOverride() {
            this.overrideModal.show           = false;
            this.overrideModal.allowOverride  = true;
            this.overrideModal.households     = [];
            this.overrideModal.reason         = '';
            this.overrideModal.reasonError    = null;
            this.overrideModal.submitting     = false;
            this.overrideModal.pendingPayload = null;
        },

        // ── Log ───────────────────────────────────────────────────────────────
        async fetchLog() {
            try {
                const r    = await fetch(`{{ url('/checkin/log') }}?event_id=${this.eventId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await r.json();
                this.log   = (data.log ?? []).slice(0, 10);
            } catch {}
        },

        // ── QR ────────────────────────────────────────────────────────────────
        async startQr() {
            this.showQr   = true;
            this.qrError  = '';
            this.clearSelection();
            await this.$nextTick();

            const video  = document.getElementById('qr-video');
            const canvas = document.getElementById('qr-canvas');
            const ctx    = canvas.getContext('2d');

            try {
                this.qrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                video.srcObject = this.qrStream;
                video.setAttribute('playsinline', true);
                await video.play();

                const tick = () => {
                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                        canvas.width  = video.videoWidth;
                        canvas.height = video.videoHeight;
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
                        if (code?.data) {
                            this.stopQr();
                            this.query = code.data;
                            this.doSearch().then(() => {
                                if (this.results.length === 1) this.selectHousehold(this.results[0]);
                            });
                            return;
                        }
                    }
                    this.qrAnimFrame = requestAnimationFrame(tick);
                };
                tick();
            } catch (e) {
                this.qrError = e.name === 'NotAllowedError'
                    ? 'Camera access denied. Please allow camera permissions.'
                    : 'Camera not available: ' + e.message;
            }
        },

        stopQr() {
            if (this.qrAnimFrame) { cancelAnimationFrame(this.qrAnimFrame); this.qrAnimFrame = null; }
            if (this.qrStream)    { this.qrStream.getTracks().forEach(t => t.stop()); this.qrStream = null; }
            this.showQr  = false;
            this.qrError = '';
        },

        // ── Toast + Time ──────────────────────────────────────────────────────
        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },

        formatTime(iso) {
            const d = new Date(iso);
            let h = d.getHours(), m = d.getMinutes(), ap = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + String(m).padStart(2, '0') + ' ' + ap;
        },
    };
}
</script>
@endpush
