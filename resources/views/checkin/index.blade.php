@extends('layouts.app')

@section('title', 'Check-In')

@push('styles')
<style>
@keyframes scan-line {
    0%   { top: 10%; }
    50%  { top: 85%; }
    100% { top: 10%; }
}
.animate-scan-line { animation: scan-line 2s ease-in-out infinite; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
@endpush

@section('content')
<div x-data="checkIn()" x-init="init()" class="max-w-lg mx-auto pb-10">

    {{-- ═══════════════════════════════════════════════════════════
         MAIN CARD
    ═══════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-visible">

        {{-- ── Card Header ─────────────────────────────────────── --}}
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Check-In</h1>
                <p class="text-xs text-gray-400 tabular-nums mt-0.5" x-text="clock"></p>
            </div>

            {{-- Event dropdown --}}
            <div class="relative">
                @if($events->isEmpty())
                    <span class="text-sm text-gray-400 italic">No upcoming events</span>
                @else
                    <select x-model="eventId"
                            @change="onEventChange()"
                            class="appearance-none border border-gray-200 rounded-xl pl-3 pr-8 py-2
                                   text-sm font-medium text-gray-700 bg-white focus:outline-none
                                   focus:ring-2 focus:ring-brand-400 focus:border-transparent cursor-pointer">
                        <option value="">— Select Event —</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}"
                                @if($selectedEvent?->id === $event->id) selected @endif>
                                {{ $event->name }}
                            </option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                    </svg>
                @endif
            </div>
        </div>

        {{-- ── Lane Selector ───────────────────────────────────── --}}
        <div x-show="selectedLanes.length > 0" style="display:none"
             class="px-5 pb-3 flex items-center gap-2">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Lane:</span>
            <div class="flex flex-wrap gap-1.5">
                <template x-for="l in selectedLanes" :key="l">
                    <button @click="lane = l" type="button"
                            :class="lane == l
                                ? 'bg-navy-700 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="text-xs font-semibold px-2.5 py-1 rounded-lg transition-colors"
                            x-text="'Lane ' + l">
                    </button>
                </template>
            </div>
        </div>

        <div class="px-5 pb-5">

            {{-- ════════════════════════════════════════════════════
                 SEARCH ROW
            ════════════════════════════════════════════════════ --}}
            <div x-show="!showQr" class="flex items-center gap-2 mb-4">

                <div class="relative flex-1" @click.outside="showDropdown = false">
                    <div class="flex items-center border rounded-xl bg-white overflow-visible
                                transition-shadow focus-within:shadow-sm"
                         :class="showDropdown && results.length ? 'border-brand-400 ring-2 ring-brand-100' : 'border-gray-300'">
                        <svg class="w-4 h-4 text-gray-400 ml-3 flex-shrink-0"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.197 5.197a7.5 7.5 0 0 0 10.606 10.606Z"/>
                        </svg>
                        <input type="text"
                               x-model="query"
                               @input.debounce.300ms="doSearch()"
                               @focus="showDropdown = results.length > 0"
                               @keydown.escape="showDropdown = false"
                               @keydown.enter.prevent="results.length === 1 && selectHousehold(results[0])"
                               placeholder="Search by code, name or phone"
                               class="flex-1 min-w-0 py-2.5 px-2 text-sm text-gray-800
                                      placeholder-gray-400 bg-transparent focus:outline-none">
                        <span x-show="searching" style="display:none" class="mr-3">
                            <svg class="w-4 h-4 text-brand-500 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                        </span>
                        <button x-show="query && !searching" style="display:none"
                                @click="clearSelection()" type="button"
                                class="mr-3 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Search Dropdown --}}
                    <div x-show="showDropdown && (results.length > 0 || (searched && query))"
                         style="display:none"
                         class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-gray-200
                                rounded-xl shadow-lg z-30 overflow-hidden">
                        <template x-for="h in results" :key="h.id">
                            <button @click="selectHousehold(h)" type="button"
                                    class="w-full flex items-start gap-3 px-4 py-3 hover:bg-gray-50
                                           text-left border-b border-gray-100 last:border-0 transition-colors">
                                <span class="text-xs font-mono text-gray-400 w-14 flex-shrink-0 pt-0.5"
                                      x-text="'#' + h.household_number"></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-semibold text-gray-800 truncate"
                                          x-text="h.full_name"></span>
                                    <span x-show="vehicleLabel(h)" style="display:none"
                                          class="flex items-center gap-1 mt-0.5">
                                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                        </svg>
                                        <span class="text-xs text-gray-500" x-text="vehicleLabel(h)"></span>
                                    </span>
                                </span>
                            </button>
                        </template>
                        <div x-show="searched && results.length === 0" style="display:none"
                             class="px-4 py-3 text-sm text-gray-500 text-center">
                            No households found
                        </div>
                    </div>
                </div>

                {{-- QR Scan Button --}}
                <button @click="startQr()" type="button"
                        title="Scan QR code"
                        class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-xl
                               border border-gray-300 bg-white text-gray-600
                               hover:bg-gray-50 hover:text-gray-900 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75V16.5ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 18.75h.75v.75h-.75v-.75ZM18.75 13.5h.75v.75h-.75v-.75ZM18.75 18.75h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75V16.5Z"/>
                    </svg>
                </button>

                {{-- Add / Cancel Button --}}
                <button @click="toggleAddForm()" type="button"
                        :class="showAddForm
                            ? 'bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200'
                            : 'bg-navy-700 text-white hover:bg-navy-800'"
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 h-10 rounded-xl
                               font-semibold text-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              :d="showAddForm ? 'M6 18 18 6M6 6l12 12' : 'M12 4.5v15m7.5-7.5h-15'"/>
                    </svg>
                    <span x-text="showAddForm ? 'Cancel' : 'Add'"></span>
                </button>
            </div>

            {{-- ════════════════════════════════════════════════════
                 QR SCANNER
            ════════════════════════════════════════════════════ --}}
            <div x-show="showQr" style="display:none" class="mb-4">
                <button @click="stopQr()" type="button"
                        class="flex items-center gap-2 text-sm font-medium text-gray-500
                               hover:text-gray-800 mb-3 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                    </svg>
                    Back to search
                </button>

                <div class="relative bg-gray-800 rounded-2xl overflow-hidden aspect-square max-w-xs mx-auto shadow-inner">
                    <video id="qr-video" class="w-full h-full object-cover" playsinline muted></video>
                    <canvas id="qr-canvas" class="hidden absolute inset-0"></canvas>

                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="relative w-52 h-52">
                            <span class="absolute top-0 left-0 w-7 h-7 border-t-[3px] border-l-[3px] border-white rounded-tl-md"></span>
                            <span class="absolute top-0 right-0 w-7 h-7 border-t-[3px] border-r-[3px] border-white rounded-tr-md"></span>
                            <span class="absolute bottom-0 left-0 w-7 h-7 border-b-[3px] border-l-[3px] border-white rounded-bl-md"></span>
                            <span class="absolute bottom-0 right-0 w-7 h-7 border-b-[3px] border-r-[3px] border-white rounded-br-md"></span>
                            <div class="absolute left-2 right-2 h-0.5 bg-brand-400/80 animate-scan-line"></div>
                        </div>
                    </div>

                    <div x-show="qrError" style="display:none"
                         class="absolute inset-0 flex flex-col items-center justify-center bg-gray-900/90 text-white p-6 text-center">
                        <svg class="w-10 h-10 mb-2 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                        </svg>
                        <p class="text-sm font-semibold" x-text="qrError"></p>
                    </div>
                </div>
                <p class="text-center text-xs text-gray-500 mt-2">Point the camera at the household QR code</p>
            </div>

            {{-- ════════════════════════════════════════════════════
                 QUICK-ADD FORM
            ════════════════════════════════════════════════════ --}}
            <div x-show="showAddForm && !showQr" style="display:none" class="mb-4 space-y-3">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" x-model="addForm.first_name"
                               :class="addErrors.first_name ? 'border-red-400' : 'border-gray-300'"
                               class="w-full border rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                        <p x-show="addErrors.first_name" style="display:none"
                           class="text-xs text-red-500 mt-0.5" x-text="addErrors.first_name?.[0]"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" x-model="addForm.last_name"
                               :class="addErrors.last_name ? 'border-red-400' : 'border-gray-300'"
                               class="w-full border rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                        <p x-show="addErrors.last_name" style="display:none"
                           class="text-xs text-red-500 mt-0.5" x-text="addErrors.last_name?.[0]"></p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Email</label>
                    <input type="email" x-model="addForm.email"
                           :class="addErrors.email ? 'border-red-400' : 'border-gray-300'"
                           class="w-full border rounded-xl px-3 py-2.5 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Phone</label>
                    <input type="tel" x-model="addForm.phone"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Vehicle Color</label>
                        <input type="text" x-model="addForm.vehicle_color" placeholder="Silver"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Vehicle Make</label>
                        <input type="text" x-model="addForm.vehicle_make" placeholder="Toyota"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">City</label>
                        <input type="text" x-model="addForm.city"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">State</label>
                        <select x-model="addForm.state"
                                class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                       bg-white focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                            <option value="">--</option>
                            @foreach(['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
                                      'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
                                      'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
                                      'VA','WA','WV','WI','WY','DC'] as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        Household Composition <span class="text-red-500">*</span>
                        <span class="ml-1.5 font-normal text-gray-400">
                            (total: <span x-text="addForm.children_count + addForm.adults_count + addForm.seniors_count"></span> people)
                        </span>
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl px-2 py-2 text-center">
                            <p class="text-[10px] font-semibold text-blue-600 mb-1">Children <span class="font-normal opacity-70">&lt;18</span></p>
                            <input type="number" min="0" max="50" x-model.number="addForm.children_count"
                                   class="w-full text-center py-1 text-sm font-bold border border-blue-300 rounded-lg bg-white
                                          focus:outline-none focus:ring-2 focus:ring-blue-300">
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-xl px-2 py-2 text-center">
                            <p class="text-[10px] font-semibold text-green-700 mb-1">Adults <span class="font-normal opacity-70">18–64</span></p>
                            <input type="number" min="0" max="50" x-model.number="addForm.adults_count"
                                   class="w-full text-center py-1 text-sm font-bold border border-green-300 rounded-lg bg-white
                                          focus:outline-none focus:ring-2 focus:ring-green-300">
                        </div>
                        <div class="bg-purple-50 border border-purple-200 rounded-xl px-2 py-2 text-center">
                            <p class="text-[10px] font-semibold text-purple-700 mb-1">Seniors <span class="font-normal opacity-70">65+</span></p>
                            <input type="number" min="0" max="50" x-model.number="addForm.seniors_count"
                                   class="w-full text-center py-1 text-sm font-bold border border-purple-300 rounded-lg bg-white
                                          focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════════════════════
                 SELECTED HOUSEHOLD PREVIEW
            ════════════════════════════════════════════════════ --}}
            <div x-show="selectedHousehold && !showAddForm && !showQr"
                 style="display:none" class="mb-4">
                <div class="bg-gray-50 rounded-2xl border border-gray-200 p-4">

                    {{-- Header --}}
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="font-mono text-xs text-gray-400"
                                  x-text="'#' + selectedHousehold?.household_number"></span>
                            <p class="text-base font-bold text-gray-900 mt-0.5"
                               x-text="selectedHousehold?.full_name"></p>
                        </div>
                        <button @click="clearSelection()" type="button"
                                class="text-gray-400 hover:text-gray-600 p-1 -mt-1 -mr-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                            </svg>
                            <span x-text="(selectedHousehold?.household_size ?? 0) + ' ' + ((selectedHousehold?.household_size ?? 0) == 1 ? 'Member' : 'Members')"></span>
                            <span class="text-xs text-gray-400 ml-0.5"
                                  x-text="'(' + (selectedHousehold?.children_count ?? 0) + 'C ' + (selectedHousehold?.adults_count ?? 0) + 'A ' + (selectedHousehold?.seniors_count ?? 0) + 'S)'">
                            </span>
                        </span>
                        <span x-show="selectedHousehold?.city || selectedHousehold?.state"
                              class="flex items-center gap-1">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                            </svg>
                            <span x-text="[selectedHousehold?.city, selectedHousehold?.state].filter(Boolean).join(', ')"></span>
                        </span>
                    </div>

                    {{-- ── Vehicle section ──────────────────────────── --}}
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <div x-show="!vehicleEditMode" class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 text-sm">
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                </svg>
                                <template x-if="vehicleLabel(selectedHousehold)">
                                    <span class="font-medium text-gray-700" x-text="vehicleLabel(selectedHousehold)"></span>
                                </template>
                                <template x-if="!vehicleLabel(selectedHousehold)">
                                    <span class="text-gray-400 italic">No vehicle on file</span>
                                </template>
                            </div>
                            <button @click="openVehicleEdit()" type="button"
                                    class="text-xs font-semibold text-brand-600 hover:text-brand-700 flex-shrink-0">
                                <span x-text="vehicleLabel(selectedHousehold) ? 'Edit' : '+ Add vehicle'"></span>
                            </button>
                        </div>

                        <div x-show="vehicleEditMode" style="display:none" class="space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Color</label>
                                    <input type="text" x-model="vehicleEditColor" placeholder="Silver"
                                           class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm
                                                  focus:outline-none focus:ring-2 focus:ring-brand-400">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Make</label>
                                    <input type="text" x-model="vehicleEditMake" placeholder="Toyota"
                                           class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm
                                                  focus:outline-none focus:ring-2 focus:ring-brand-400">
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="saveVehicleEdit()" type="button"
                                        :disabled="vehicleEditSaving"
                                        class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg
                                               bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold
                                               transition-colors disabled:opacity-60">
                                    <template x-if="vehicleEditSaving">
                                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                        </svg>
                                    </template>
                                    <span x-text="vehicleEditSaving ? 'Saving…' : 'Save'"></span>
                                </button>
                                <button @click="vehicleEditMode = false" type="button"
                                        class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200
                                               text-gray-600 text-xs font-semibold transition-colors">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ── Represented Pickups management ───────────── --}}
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Represented Pickups</p>
                            <div class="flex items-center gap-2">
                                <button @click="showAttachSearch = !showAttachSearch; attachQuery = ''; attachResults = []"
                                        type="button"
                                        :class="showAttachSearch ? 'text-gray-500 hover:text-gray-700' : 'text-brand-600 hover:text-brand-700'"
                                        class="bg-transparent border-0 p-0 text-xs font-semibold transition-colors">
                                    <span x-text="showAttachSearch ? '✕ Close' : '+ Attach existing'"></span>
                                </button>
                                <span class="text-gray-300 text-xs select-none">|</span>
                                <button @click="showCreatePanel = true; showAttachSearch = false"
                                        type="button"
                                        class="bg-transparent border-0 p-0 text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">
                                    + Create new
                                </button>
                            </div>
                        </div>

                        {{-- Attach-existing inline search --}}
                        <div x-show="showAttachSearch" style="display:none" class="mb-2.5">
                            <div class="relative">
                                <input type="text" x-model="attachQuery"
                                       @input.debounce.300ms="doAttachSearch()"
                                       placeholder="Search by name, code or phone…"
                                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm
                                              focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent pr-8">
                                <span x-show="attachSearching" style="display:none"
                                      class="absolute right-2.5 top-1/2 -translate-y-1/2">
                                    <svg class="w-4 h-4 text-brand-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                </span>
                            </div>
                            <template x-if="attachResults.length > 0">
                                <div class="mt-1.5 bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                    <template x-for="h in attachResults" :key="h.id">
                                        <button @click="attachExisting(h)" type="button"
                                                class="w-full flex items-center gap-2 px-3 py-2.5 hover:bg-gray-50
                                                       text-left border-b border-gray-100 last:border-0 transition-colors">
                                            <span class="font-mono text-xs text-gray-400 w-12 flex-shrink-0"
                                                  x-text="'#' + h.household_number"></span>
                                            <span class="flex-1 min-w-0 text-sm font-medium text-gray-800 truncate"
                                                  x-text="h.full_name"></span>
                                            <span class="text-xs text-gray-500 flex-shrink-0"
                                                  x-text="h.household_size + ' ppl'"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <p x-show="attachQuery.trim() && !attachSearching && attachResults.length === 0"
                               style="display:none"
                               class="text-xs text-gray-400 text-center py-2">No households found</p>
                        </div>

                        {{-- Linked households list --}}
                        <template x-if="linkedHouseholds.length === 0">
                            <p class="text-xs text-gray-400 italic py-1">No additional households — single-household check-in.</p>
                        </template>
                        <template x-for="h in linkedHouseholds" :key="h.id">
                            <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-100 rounded-xl px-2.5 py-2 mb-1.5">
                                <span class="text-amber-400 text-xs flex-shrink-0">↳</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-amber-900 truncate" x-text="h.full_name"></p>
                                    <p class="text-[10px] text-amber-600 font-mono" x-text="'#' + h.household_number"></p>
                                </div>
                                <span class="text-xs text-amber-700 flex-shrink-0"
                                      x-text="h.household_size + ' ppl'"></span>
                                <span x-show="h.bags_needed !== null && h.bags_needed !== undefined"
                                      style="display:none"
                                      class="text-xs font-bold text-orange-600 flex-shrink-0"
                                      x-text="'· ' + h.bags_needed + ' bags'"></span>
                                <button @click="removeLinked(h.id)" type="button"
                                        class="ml-1 text-gray-400 hover:text-red-500 flex-shrink-0 transition-colors"
                                        title="Remove from this pickup">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                </div>
            </div>

            {{-- ════════════════════════════════════════════════════
                 GROUPED PICKUP SUMMARY
            ════════════════════════════════════════════════════ --}}
            <div x-show="selectedHousehold && linkedHouseholds.length > 0 && !showAddForm && !showQr"
                 style="display:none"
                 class="mb-4 bg-amber-50 border border-amber-200 rounded-2xl p-4">
                <p class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-2.5">
                    Grouped Pickup Summary
                </p>

                {{-- Representative row --}}
                <div class="flex items-center gap-2 py-1 text-sm">
                    <span class="w-4 flex-shrink-0 text-center text-amber-300 font-bold">★</span>
                    <span class="flex-1 font-semibold text-gray-900" x-text="selectedHousehold?.full_name"></span>
                    <span class="text-gray-600 text-xs flex-shrink-0"
                          x-text="selectedHousehold ? selectedHousehold.household_size + ' ppl' : ''"></span>
                    <span x-show="selectedHousehold?.bags_needed !== null && selectedHousehold?.bags_needed !== undefined"
                          style="display:none"
                          class="text-orange-600 text-xs font-bold flex-shrink-0"
                          x-text="selectedHousehold?.bags_needed + ' bags'"></span>
                </div>

                {{-- Linked household rows --}}
                <template x-for="h in linkedHouseholds" :key="h.id">
                    <div class="flex items-center gap-2 py-1 text-xs">
                        <span class="w-4 flex-shrink-0 text-center text-amber-400">↳</span>
                        <span class="flex-1 font-medium text-gray-800" x-text="h.full_name"></span>
                        <span class="text-gray-500 flex-shrink-0" x-text="h.household_size + ' ppl'"></span>
                        <span x-show="h.bags_needed !== null && h.bags_needed !== undefined"
                              style="display:none"
                              class="text-orange-600 font-bold flex-shrink-0"
                              x-text="h.bags_needed + ' bags'"></span>
                    </div>
                </template>

                {{-- Totals --}}
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

            {{-- ── No event selected hint ───────────────────────── --}}
            <div x-show="!eventId && !showQr" style="display:none"
                 class="mb-4 flex items-center gap-2.5 bg-amber-50 border border-amber-200
                        rounded-xl px-4 py-3 text-sm text-amber-700">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                Select an event above to enable check-in.
            </div>

            {{-- ════════════════════════════════════════════════════
                 CHECK-IN BUTTON
            ════════════════════════════════════════════════════ --}}
            <button @click="handleCheckIn()"
                    :disabled="checkingIn || addingNew
                               || (!selectedHousehold && !showAddForm)
                               || !eventId || showQr"
                    type="button"
                    class="w-full min-h-[48px] flex items-center justify-center gap-2 rounded-xl
                           bg-navy-700 hover:bg-navy-800 active:bg-navy-900 text-white
                           font-semibold text-sm transition-colors duration-150
                           disabled:opacity-40 disabled:cursor-not-allowed mb-5">
                <template x-if="checkingIn || addingNew">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                </template>
                <span x-show="checkingIn || addingNew" style="display:none">Processing…</span>
                <span x-show="!(checkingIn || addingNew)" style="display:none"
                      x-text="showAddForm
                          ? 'Create Household →'
                          : linkedHouseholds.length > 0
                              ? 'Check In Group (' + (linkedHouseholds.length + 1) + ' households)'
                              : 'Check In'">
                </span>
            </button>

            {{-- ════════════════════════════════════════════════════
                 LOG
            ════════════════════════════════════════════════════ --}}
            <div x-show="eventId" style="display:none">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Check-In Log</h2>

                <div x-show="log.length === 0" style="display:none"
                     class="text-center text-xs text-gray-400 py-5">
                    No check-ins yet for this event.
                </div>

                <template x-for="entry in log" :key="entry.id">
                    <div class="flex items-center gap-2 py-2.5 border-b border-gray-100 last:border-0">
                        <span class="text-xs font-mono text-gray-400 w-14 flex-shrink-0 truncate"
                              x-text="entry.household ? '#' + entry.household.household_number : '—'"></span>

                        <span class="flex-1 min-w-0">
                            <span class="flex items-center gap-1.5">
                                <span class="text-sm font-semibold text-gray-800 truncate"
                                      x-text="entry.household ? entry.household.full_name : '—'"></span>
                                <template x-if="entry.is_representative_pickup">
                                    <span class="flex-shrink-0 inline-flex items-center gap-0.5 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1 py-0.5">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                        </svg>
                                        Rep. Pickup
                                    </span>
                                </template>
                            </span>
                            <span x-show="entry.household && vehicleLabel(entry.household)"
                                  style="display:none"
                                  class="flex items-center gap-1 mt-0.5">
                                <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                </svg>
                                <span class="text-xs text-gray-500"
                                      x-text="vehicleLabel(entry.household)"></span>
                            </span>
                        </span>

                        <span x-show="entry.active" style="display:none"
                              class="text-xs text-gray-400 whitespace-nowrap hidden sm:block"
                              x-text="'Checked in ' + formatTime(entry.start_time)"></span>

                        <template x-if="entry.active">
                            <button @click="markDone(entry.id)"
                                    :disabled="markingDone === entry.id"
                                    type="button"
                                    class="flex-shrink-0 bg-amber-500 hover:bg-amber-600 text-white
                                           text-xs font-semibold px-3 py-1.5 rounded-lg
                                           transition-colors disabled:opacity-60">
                                <span x-text="markingDone === entry.id ? '…' : 'Done'"></span>
                            </button>
                        </template>
                        <template x-if="!entry.active">
                            <span class="flex-shrink-0 text-xs text-gray-400">Served</span>
                        </template>
                    </div>
                </template>
            </div>

        </div>{{-- /px-5 --}}
    </div>{{-- /card --}}

    {{-- ═══════════════════════════════════════════════════════════
         TOASTS
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="toast.show"
         style="display:none"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-3"
         :class="toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2
                text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-xl
                max-w-xs w-full mx-4">
        <svg x-show="toast.type === 'success'" style="display:none"
             class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
        </svg>
        <svg x-show="toast.type === 'error'" style="display:none"
             class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H2.645c-1.73 0-2.813-1.874-1.948-3.374L10.053 3.378c.866-1.5 3.032-1.5 3.898 0L21.303 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <span x-text="toast.message"></span>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         CREATE REPRESENTED HOUSEHOLD — OFFCANVAS
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="showCreatePanel" style="display:none"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50"
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showCreatePanel = false; resetCreateForm()"></div>

        {{-- Panel --}}
        <div class="relative z-10 bg-white w-full sm:max-w-sm rounded-t-3xl sm:rounded-2xl shadow-2xl"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
             x-transition:enter-end="translate-y-0 sm:opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100"
             x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0">

            <div class="max-h-[90vh] overflow-y-auto p-5">
                {{-- Panel header --}}
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-base font-bold text-gray-900">New Represented Household</h3>
                    <button @click="showCreatePanel = false; resetCreateForm()" type="button"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1 -mr-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                    Linked to:
                    <span class="font-semibold text-gray-700" x-text="selectedHousehold?.full_name ?? '—'"></span>
                </p>

                <div class="space-y-3">
                    {{-- Name row --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="createForm.first_name"
                                   :class="createErrors.first_name ? 'border-red-400' : 'border-gray-300'"
                                   class="w-full border rounded-xl px-3 py-2.5 text-sm
                                          focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                            <p x-show="createErrors.first_name" style="display:none"
                               class="text-xs text-red-500 mt-0.5" x-text="createErrors.first_name?.[0]"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="createForm.last_name"
                                   :class="createErrors.last_name ? 'border-red-400' : 'border-gray-300'"
                                   class="w-full border rounded-xl px-3 py-2.5 text-sm
                                          focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                            <p x-show="createErrors.last_name" style="display:none"
                               class="text-xs text-red-500 mt-0.5" x-text="createErrors.last_name?.[0]"></p>
                        </div>
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Phone</label>
                        <input type="tel" x-model="createForm.phone"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                    </div>

                    {{-- Household Composition --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            Household Composition <span class="text-red-500">*</span>
                            <span class="ml-1.5 font-normal text-gray-400">
                                (total: <span x-text="createForm.children_count + createForm.adults_count + createForm.seniors_count"></span> ppl)
                            </span>
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl px-2 py-2 text-center">
                                <p class="text-[10px] font-semibold text-blue-600 mb-1">Children</p>
                                <input type="number" min="0" max="50" x-model.number="createForm.children_count"
                                       class="w-full text-center py-1 text-sm font-bold border border-blue-300 rounded-lg bg-white
                                              focus:outline-none focus:ring-2 focus:ring-blue-300">
                            </div>
                            <div class="bg-green-50 border border-green-200 rounded-xl px-2 py-2 text-center">
                                <p class="text-[10px] font-semibold text-green-700 mb-1">Adults</p>
                                <input type="number" min="0" max="50" x-model.number="createForm.adults_count"
                                       class="w-full text-center py-1 text-sm font-bold border border-green-300 rounded-lg bg-white
                                              focus:outline-none focus:ring-2 focus:ring-green-300">
                            </div>
                            <div class="bg-purple-50 border border-purple-200 rounded-xl px-2 py-2 text-center">
                                <p class="text-[10px] font-semibold text-purple-700 mb-1">Seniors</p>
                                <input type="number" min="0" max="50" x-model.number="createForm.seniors_count"
                                       class="w-full text-center py-1 text-sm font-bold border border-purple-300 rounded-lg bg-white
                                              focus:outline-none focus:ring-2 focus:ring-purple-300">
                            </div>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                        <textarea x-model="createForm.notes" rows="2"
                                  class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                         focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent
                                         resize-none"></textarea>
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-2 pt-1 pb-safe">
                        <button @click="submitCreateRepresented()"
                                :disabled="createSaving"
                                type="button"
                                class="flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl
                                       bg-navy-700 hover:bg-navy-800 text-white font-semibold text-sm
                                       transition-colors disabled:opacity-60">
                            <template x-if="createSaving">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                            </template>
                            <span x-text="createSaving ? 'Saving…' : 'Create & Link Household'"></span>
                        </button>
                        <button @click="showCreatePanel = false; resetCreateForm()"
                                type="button"
                                class="px-4 py-3 rounded-xl bg-gray-100 hover:bg-gray-200
                                       text-gray-700 font-semibold text-sm transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         ALREADY-SERVED OVERRIDE — MODAL  (Phase 1.3.d)
         Triggered when /checkin returns 422 with
         error: 'household_already_served'. Two states:
           - allowOverride=true  → reason textarea + Confirm button.
                                   On confirm, re-POST with force=1
                                   + override_reason. Server logs an
                                   audit row in checkin_overrides.
           - allowOverride=false → close-only (deny policy mode).
    ═══════════════════════════════════════════════════════════ --}}
    <div x-show="overrideModal.show" style="display:none"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
         @keydown.escape.window="overrideModal.show && cancelOverride()">

        {{-- Backdrop. Click-outside cancels, but NOT while a confirm
             request is in flight — the request continues regardless of
             modal state, and silently dismissing the modal mid-flight
             would hide any validator error response from the user. --}}
        <div class="absolute inset-0 bg-black/50"
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="!overrideModal.submitting && cancelOverride()"></div>

        {{-- Panel --}}
        <div class="relative z-10 bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-2xl shadow-2xl"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
             x-transition:enter-end="translate-y-0 sm:opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100"
             x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0">

            {{-- Header --}}
            <div class="flex items-start gap-3 p-6 pb-4">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-gray-900">Already served at this event</h3>
                    <template x-if="overrideModal.households.length === 1">
                        <p class="text-sm text-gray-600 mt-1">
                            <span class="font-medium" x-text="overrideModal.households[0].full_name"></span>
                            <span class="text-gray-500" x-text="'(#' + overrideModal.households[0].household_number + ')'"></span>
                            has already been checked in and exited at this event.
                        </p>
                    </template>
                    <template x-if="overrideModal.households.length > 1">
                        <div class="text-sm text-gray-600 mt-1">
                            <p>The following households have already been served at this event:</p>
                            <ul class="list-disc list-inside mt-1 space-y-0.5">
                                <template x-for="h in overrideModal.households" :key="h.id">
                                    <li>
                                        <span class="font-medium" x-text="h.full_name"></span>
                                        <span class="text-gray-500" x-text="'(#' + h.household_number + ')'"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
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
                                  class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm
                                         focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent
                                         resize-none"></textarea>
                        <p x-show="overrideModal.reasonError" style="display:none"
                           class="text-xs text-red-600 mt-1" x-text="overrideModal.reasonError"></p>
                        <p class="text-xs text-gray-500 mt-1">
                            This will be recorded in the audit log along with your username.
                        </p>
                    </div>

                    <div class="flex gap-2 pt-1 pb-safe">
                        <button @click="confirmOverride()"
                                :disabled="overrideModal.submitting || !overrideModal.reason.trim()"
                                type="button"
                                class="flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl
                                       bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm
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
                        <span class="font-medium">Settings → Event &amp; Queue → Re-Check-In Policy</span>.
                    </p>
                    <div class="flex pt-1 pb-safe">
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

</div>
@endsection

@push('scripts')
<script>
window.__checkInData = {
    eventId: @json($selectedEvent?->id),
    lanes: @json($lanes),
};
</script>
<script>
function checkIn() {
    return {
        // ── Config ───────────────────────────────────────────────────────────
        eventId:       null,
        lane:          null,
        selectedLanes: [],

        // ── UI State ──────────────────────────────────────────────────────────
        showAddForm: false,
        showQr:      false,

        // ── Search ────────────────────────────────────────────────────────────
        query:             '',
        results:           [],
        showDropdown:      false,
        searched:          false,
        searching:         false,
        selectedHousehold: null,

        // ── QR ────────────────────────────────────────────────────────────────
        qrStream:    null,
        qrAnimFrame: null,
        qrError:     null,

        // ── Quick-Add Form ────────────────────────────────────────────────────
        addForm: {
            first_name:     '',
            last_name:      '',
            email:          '',
            phone:          '',
            vehicle_color:  '',
            vehicle_make:   '',
            city:           '',
            state:          '',
            children_count: 0,
            adults_count:   1,
            seniors_count:  0,
        },
        addErrors: {},

        // ── Vehicle Inline Edit ───────────────────────────────────────────────
        vehicleEditMode:   false,
        vehicleEditMake:   '',
        vehicleEditColor:  '',
        vehicleEditSaving: false,

        // ── Represented Pickups Management ───────────────────────────────────
        linkedHouseholds: [],   // [{id, full_name, household_number, household_size, ..., bags_needed}]
        showCreatePanel:  false,
        createForm: {
            first_name:     '',
            last_name:      '',
            phone:          '',
            children_count: 0,
            adults_count:   1,
            seniors_count:  0,
            notes:          '',
        },
        createErrors:    {},
        createSaving:    false,
        showAttachSearch: false,
        attachQuery:     '',
        attachResults:   [],
        attachSearching: false,

        // ── Actions ───────────────────────────────────────────────────────────
        checkingIn:  false,
        addingNew:   false,
        markingDone: null,

        // ── Log ───────────────────────────────────────────────────────────────
        log: [],

        // ── Override Modal (Phase 1.3.d) ──────────────────────────────────────
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

        // ── Toast / Clock ─────────────────────────────────────────────────────
        toast:      { show: false, message: '', type: 'success' },
        clock:      '',
        clockTimer: null,
        pollTimer:  null,

        // ── Computed ──────────────────────────────────────────────────────────
        get totalLinkedPeople() {
            const repSize = this.selectedHousehold?.household_size ?? 0;
            return this.linkedHouseholds.reduce((s, h) => s + (h.household_size || 0), repSize);
        },
        get totalLinkedBags() {
            const repBags = this.selectedHousehold?.bags_needed ?? 0;
            return this.linkedHouseholds.reduce((s, h) => s + (h.bags_needed || 0), repBags);
        },

        // ── Init ──────────────────────────────────────────────────────────────
        init() {
            this.eventId       = __checkInData.eventId;
            this.selectedLanes = __checkInData.lanes;
            if (this.selectedLanes.length) this.lane = this.selectedLanes[0];
            this.loadLog();
            this.pollTimer  = setInterval(() => this.loadLog(), 15000);
            this.updateClock();
            this.clockTimer = setInterval(() => this.updateClock(), 1000);
        },

        updateClock() {
            this.clock = new Date().toLocaleTimeString([], {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        },

        onEventChange() {
            window.location.href = '/checkin?event_id=' + (this.eventId || '');
        },

        // ── Search ────────────────────────────────────────────────────────────
        async doSearch() {
            if (!this.query.trim()) {
                this.results      = [];
                this.showDropdown = false;
                this.searched     = false;
                return;
            }
            this.searching = true;
            try {
                const res  = await fetch(
                    '/checkin/search?q=' + encodeURIComponent(this.query)
                    + '&event_id=' + (this.eventId || ''),
                    { headers: { 'Accept': 'application/json' } }
                );
                const data = await res.json();
                this.results      = data.results || [];
                this.showDropdown = true;
                this.searched     = true;
            } catch {
                this.showToast('Search failed. Please try again.', 'error');
            }
            this.searching = false;
        },

        selectHousehold(h) {
            this.selectedHousehold = h;
            this.query             = h.full_name;
            this.showDropdown      = false;
            this.results           = [];
            this.showAddForm       = false;
            // Seed linked households from existing DB-linked represented households
            this.linkedHouseholds  = (h.represented_households || []).map(r => ({ ...r }));
            // Reset attach/create state
            this.showAttachSearch  = false;
            this.attachQuery       = '';
            this.attachResults     = [];
            this.showCreatePanel   = false;
        },

        clearSelection() {
            this.selectedHousehold = null;
            this.query             = '';
            this.results           = [];
            this.showDropdown      = false;
            this.searched          = false;
            this.vehicleEditMode   = false;
            this.linkedHouseholds  = [];
            this.showAttachSearch  = false;
            this.attachQuery       = '';
            this.attachResults     = [];
            this.showCreatePanel   = false;
        },

        // ── Add Form ──────────────────────────────────────────────────────────
        toggleAddForm() {
            this.showAddForm = !this.showAddForm;
            if (this.showAddForm) {
                this.clearSelection();
            } else {
                this.resetAddForm();
            }
        },

        resetAddForm() {
            this.addForm = {
                first_name:     '',
                last_name:      '',
                email:          '',
                phone:          '',
                vehicle_color:  '',
                vehicle_make:   '',
                city:           '',
                state:          '',
                children_count: 0,
                adults_count:   1,
                seniors_count:  0,
            };
            this.addErrors = {};
        },

        // Creates the household record, then drops into the selected-household
        // card so staff can add represented households before checking in.
        async quickAddCreate() {
            this.addingNew = true;
            this.addErrors = {};
            try {
                const res = await fetch('/checkin/quick-create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        event_id: this.eventId,
                        ...this.addForm,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    // Switch to selected-household mode — staff can now add
                    // represented households before clicking "Check In"
                    this.showAddForm      = false;
                    this.selectedHousehold = data.household;
                    this.query             = data.household.full_name;
                    this.linkedHouseholds  = [];
                    this.resetAddForm();
                    this.showToast('Household created — add represented pickups or check in.', 'success');
                } else if (res.status === 422) {
                    this.addErrors = data.errors || {};
                    if (data.message && !Object.keys(data.errors || {}).length) {
                        this.showToast(data.message, 'error');
                    }
                } else {
                    this.showToast(data.message || 'Failed to create household', 'error');
                }
            } catch {
                this.showToast('Network error. Please try again.', 'error');
            }
            this.addingNew = false;
        },

        // ── Attach existing household ─────────────────────────────────────────
        async doAttachSearch() {
            if (!this.attachQuery.trim() || !this.selectedHousehold) {
                this.attachResults = [];
                return;
            }
            this.attachSearching = true;
            try {
                const url = '/checkin/represented/search'
                    + '?q='                 + encodeURIComponent(this.attachQuery)
                    + '&representative_id=' + this.selectedHousehold.id
                    + '&event_id='          + (this.eventId || '');
                const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const linkedIds = new Set(this.linkedHouseholds.map(h => h.id));
                this.attachResults = (data.results || []).filter(h => !linkedIds.has(h.id));
            } catch {
                this.showToast('Search failed', 'error');
            }
            this.attachSearching = false;
        },

        async attachExisting(h) {
            // Persist the DB link immediately — sets representative_household_id on the household
            try {
                const res = await fetch('/checkin/represented/attach', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        representative_id: this.selectedHousehold.id,
                        household_id:      h.id,
                        event_id:          this.eventId,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.linkedHouseholds.push(data.household);
                    this.attachResults    = this.attachResults.filter(r => r.id !== h.id);
                    this.attachQuery      = '';
                    this.showAttachSearch = false;
                    this.showToast(data.household.full_name + ' linked!', 'success');
                } else {
                    this.showToast(data.message || 'Could not attach household', 'error');
                }
            } catch {
                this.showToast('Network error', 'error');
            }
        },

        // ── Create new represented household ─────────────────────────────────
        async submitCreateRepresented() {
            if (!this.selectedHousehold) return;
            this.createSaving = true;
            this.createErrors = {};
            try {
                const res = await fetch('/checkin/represented/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        representative_id: this.selectedHousehold.id,
                        event_id:          this.eventId,
                        ...this.createForm,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.linkedHouseholds.push(data.household);
                    this.showCreatePanel = false;
                    this.resetCreateForm();
                    this.showToast('Household created & linked!', 'success');
                } else if (res.status === 422) {
                    this.createErrors = data.errors || {};
                    if (data.message && !Object.keys(data.errors || {}).length) {
                        this.showToast(data.message, 'error');
                    }
                } else {
                    this.showToast(data.message || 'Failed to create household', 'error');
                }
            } catch {
                this.showToast('Network error', 'error');
            }
            this.createSaving = false;
        },

        removeLinked(id) {
            this.linkedHouseholds = this.linkedHouseholds.filter(h => h.id !== id);
        },

        resetCreateForm() {
            this.createForm = {
                first_name:     '',
                last_name:      '',
                phone:          '',
                children_count: 0,
                adults_count:   1,
                seniors_count:  0,
                notes:          '',
            };
            this.createErrors = {};
        },

        // ── QR Scanner ────────────────────────────────────────────────────────
        async startQr() {
            this.showQr   = true;
            this.qrError  = null;
            this.clearSelection();

            await this.$nextTick();

            const video  = document.getElementById('qr-video');
            const canvas = document.getElementById('qr-canvas');
            const ctx    = canvas.getContext('2d');

            try {
                this.qrStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' }
                });
                video.srcObject = this.qrStream;
                video.setAttribute('playsinline', true);
                await video.play();

                const tick = () => {
                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                        canvas.height = video.videoHeight;
                        canvas.width  = video.videoWidth;
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height, {
                            inversionAttempts: 'dontInvert',
                        });
                        if (code && code.data) {
                            this.stopQr();
                            this.query = code.data;
                            this.doSearch().then(() => {
                                if (this.results.length === 1) {
                                    this.selectHousehold(this.results[0]);
                                }
                            });
                            return;
                        }
                    }
                    this.qrAnimFrame = requestAnimationFrame(tick);
                };
                tick();
            } catch (err) {
                this.qrError = err.name === 'NotAllowedError'
                    ? 'Camera access denied. Please allow camera permissions.'
                    : 'Camera not available on this device.';
            }
        },

        stopQr() {
            if (this.qrStream) {
                this.qrStream.getTracks().forEach(t => t.stop());
                this.qrStream = null;
            }
            if (this.qrAnimFrame) {
                cancelAnimationFrame(this.qrAnimFrame);
                this.qrAnimFrame = null;
            }
            this.showQr  = false;
            this.qrError = null;
        },

        // ── Check-In ──────────────────────────────────────────────────────────
        async handleCheckIn() {
            if (!this.eventId) { this.showToast('Select an event first', 'error'); return; }
            if (!this.lane)    { this.showToast('Select a lane first', 'error');   return; }

            if (this.showAddForm) {
                // Create the household first; staff then adds represented pickups
                // before the final "Check In" click
                await this.quickAddCreate();
            } else if (this.selectedHousehold) {
                await this.checkInHousehold(this.selectedHousehold.id);
            } else {
                this.showToast('Search and select a household first', 'error');
            }
        },

        async checkInHousehold(householdId) {
            this.checkingIn = true;
            try {
                const body = {
                    event_id:     this.eventId,
                    household_id: householdId,
                    lane:         this.lane,
                };
                // Pass explicit represented_ids so the controller uses the staff-curated list
                if (this.linkedHouseholds.length > 0) {
                    body.represented_ids = this.linkedHouseholds.map(h => h.id);
                }
                const res  = await fetch('/checkin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (res.ok) {
                    const grpMsg = this.linkedHouseholds.length > 0
                        ? 'Group of ' + (this.linkedHouseholds.length + 1) + ' checked in!'
                        : 'Checked in successfully!';
                    this.showToast(grpMsg, 'success');
                    this.clearSelection();
                    await this.loadLog();
                } else if (res.status === 422 && data.error === 'household_already_served') {
                    // Phase 1.3.d: surface the conflict via the override modal
                    // instead of a flat error toast. Capture the original body
                    // so confirmOverride() can re-POST with force=1 + reason.
                    // Defensive: if households comes back empty (shouldn't —
                    // the exception only throws when there's at least one),
                    // fall back to the regular toast so the user gets *some*
                    // explanation rather than a context-less reason textarea.
                    const offending = Array.isArray(data.households) ? data.households : [];
                    if (offending.length === 0) {
                        this.showToast(data.message || 'Already served at this event.', 'error');
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
                    this.showToast(data.message || 'Check-in failed', 'error');
                }
            } catch {
                this.showToast('Network error. Please try again.', 'error');
            }
            this.checkingIn = false;
        },

        // Phase 1.3.d: re-POST the captured check-in body with force=1 and
        // the supervisor's reason. The server logs an audit row in
        // checkin_overrides and proceeds with the visit creation. On
        // validation failure (e.g. empty reason somehow), populate the
        // modal's inline error rather than closing.
        async confirmOverride() {
            // Belt-and-suspenders against double-click. Alpine reactivity
            // synchronously disables the button on the first :disabled check,
            // but a runtime guard makes the contract explicit.
            if (this.overrideModal.submitting) {
                return;
            }
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
                const res = await fetch('/checkin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (res.ok) {
                    this.cancelOverride();
                    this.showToast('Override recorded. Check-in successful.', 'success');
                    this.clearSelection();
                    await this.loadLog();
                } else {
                    // Prefer the validator's specific override_reason error
                    // (most likely cause), then any top-level message.
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

        async quickAddCheckIn() {
            this.addingNew = true;
            this.addErrors = {};
            try {
                const res  = await fetch('/checkin/quick-add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        event_id: this.eventId,
                        lane:     this.lane,
                        ...this.addForm,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.showToast('Household added & checked in!', 'success');
                    this.showAddForm = false;
                    this.resetAddForm();
                    await this.loadLog();
                } else if (res.status === 422) {
                    this.addErrors = data.errors || {};
                    if (data.message && !Object.keys(data.errors || {}).length) {
                        this.showToast(data.message, 'error');
                    }
                } else {
                    this.showToast(data.message || 'Failed to add household', 'error');
                }
            } catch {
                this.showToast('Network error. Please try again.', 'error');
            }
            this.addingNew = false;
        },

        async markDone(visitId) {
            this.markingDone = visitId;
            try {
                const res = await fetch('/checkin/' + visitId + '/done', {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (res.ok) {
                    this.showToast('Visit completed!', 'success');
                    await this.loadLog();
                } else {
                    this.showToast('Could not mark visit as done', 'error');
                }
            } catch {
                this.showToast('Network error. Please try again.', 'error');
            }
            this.markingDone = null;
        },

        // ── Log ───────────────────────────────────────────────────────────────
        async loadLog() {
            if (!this.eventId) return;
            try {
                const res  = await fetch('/checkin/log?event_id=' + this.eventId, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.log   = data.log || [];
            } catch { /* silent */ }
        },

        // ── Vehicle Edit ──────────────────────────────────────────────────────
        openVehicleEdit() {
            this.vehicleEditMake  = this.selectedHousehold?.vehicle_make  || '';
            this.vehicleEditColor = this.selectedHousehold?.vehicle_color || '';
            this.vehicleEditMode  = true;
        },

        async saveVehicleEdit() {
            if (!this.selectedHousehold) return;
            this.vehicleEditSaving = true;
            try {
                const res = await fetch(
                    '/checkin/households/' + this.selectedHousehold.id + '/vehicle',
                    {
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
                    }
                );
                const data = await res.json();
                if (res.ok) {
                    this.selectedHousehold.vehicle_make  = data.household.vehicle_make;
                    this.selectedHousehold.vehicle_color = data.household.vehicle_color;
                    this.vehicleEditMode = false;
                    this.showToast('Vehicle info saved!', 'success');
                } else {
                    this.showToast('Failed to save vehicle info', 'error');
                }
            } catch {
                this.showToast('Network error', 'error');
            }
            this.vehicleEditSaving = false;
        },

        // ── Helpers ───────────────────────────────────────────────────────────
        vehicleLabel(h) {
            if (!h) return null;
            const parts = [h.vehicle_color, h.vehicle_make].filter(Boolean);
            return parts.length ? parts.join(' ') : null;
        },

        formatTime(iso) {
            return new Date(iso).toLocaleTimeString([], {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
    };
}
</script>
@endpush
