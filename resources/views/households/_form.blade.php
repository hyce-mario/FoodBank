@php
$states = [
    'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas',
    'CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware',
    'FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho',
    'IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa','KS'=>'Kansas',
    'KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland',
    'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi',
    'MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada',
    'NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York',
    'NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma',
    'OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina',
    'SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah',
    'VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia',
    'WI'=>'Wisconsin','WY'=>'Wyoming','DC'=>'DC',
];

// Seed the Alpine represented-households array for edit mode
$existingRepresented = [];
if (isset($household) && $household->relationLoaded('representedHouseholds')) {
    foreach ($household->representedHouseholds as $rep) {
        $existingRepresented[] = [
            'id'             => $rep->id,
            'first_name'     => old("represented_households.{$loop->index}.first_name", $rep->first_name),
            'last_name'      => old("represented_households.{$loop->index}.last_name",  $rep->last_name),
            'email'          => old("represented_households.{$loop->index}.email",       $rep->email ?? ''),
            'phone'          => old("represented_households.{$loop->index}.phone",       $rep->phone ?? ''),
            'children_count' => old("represented_households.{$loop->index}.children_count", $rep->children_count),
            'adults_count'   => old("represented_households.{$loop->index}.adults_count",   $rep->adults_count),
            'seniors_count'  => old("represented_households.{$loop->index}.seniors_count",  $rep->seniors_count),
            'notes'          => old("represented_households.{$loop->index}.notes",          $rep->notes ?? ''),
            '_detach'        => false,
        ];
    }
}
@endphp

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{--  SECTION 1 · Representative Household                                    --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div x-data="{ open: true }" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mx-1 sm:mx-4">

    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2.5">
            <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Representative Household</h2>
                <p class="text-xs text-gray-400 mt-0.5">The person picking up food today</p>
            </div>
        </div>
        <button type="button" @click="open = !open"
                class="w-7 h-7 flex items-center justify-center rounded-full border border-gray-300 text-gray-500 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-0' : 'rotate-180'"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
            </svg>
        </button>
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="px-8 py-7 space-y-6">

        {{-- Name --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="first_name"
                       value="{{ old('first_name', $household->first_name ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('first_name') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="First name">
                @error('first_name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                <input type="text" name="last_name"
                       value="{{ old('last_name', $household->last_name ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('last_name') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="Last name">
                @error('last_name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Email + Phone --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" name="email"
                       value="{{ old('email', $household->email ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('email') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="email@example.com">
                @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone{{ $householdSettings['require_phone'] ? ' *' : '' }}</label>
                <input type="text" name="phone"
                       value="{{ old('phone', $household->phone ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="(215) 555-0100">
            </div>
        </div>

        {{-- Address --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">City{{ $householdSettings['require_address'] ? ' *' : '' }}</label>
                <input type="text" name="city" value="{{ old('city', $household->city ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="City">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">State{{ $householdSettings['require_address'] ? ' *' : '' }}</label>
                <select name="state"
                        class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 cursor-pointer">
                    <option value="">--</option>
                    @foreach ($states as $code => $label)
                        <option value="{{ $code }}" @selected(old('state', $household->state ?? '') === $code)>{{ $code }} – {{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Zipcode{{ $householdSettings['require_address'] ? ' *' : '' }}</label>
                <input type="text" name="zip" value="{{ old('zip', $household->zip ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="19103" maxlength="10">
            </div>
        </div>

        {{-- ── Demographic Breakdown ──────────────────────────────────────── --}}
        <div class="flex items-center gap-3 pt-1">
            <div class="flex-1 h-px bg-gray-200"></div>
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Household Composition</span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>
        <p class="text-xs text-gray-400 -mt-2">Number of people in this household by age group.</p>

        <div x-data="{
                children: {{ old('children_count', $household->children_count ?? 0) }},
                adults:   {{ old('adults_count',   $household->adults_count   ?? 1) }},
                seniors:  {{ old('seniors_count',  $household->seniors_count  ?? 0) }},
                get total() { return (parseInt(this.children)||0) + (parseInt(this.adults)||0) + (parseInt(this.seniors)||0); }
             }"
             class="space-y-4">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Children --}}
                <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"/>
                            </svg>
                            <span class="text-sm font-semibold text-blue-700">Children</span>
                        </div>
                        <span class="text-xs text-blue-500">Under 18</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="children = Math.max(0, children - 1)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                        </button>
                        <input type="number" name="children_count" x-model.number="children"
                               min="0" max="50"
                               class="flex-1 text-center px-2 py-2 text-lg font-bold border border-blue-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <button type="button" @click="children = Math.min(50, children + 1)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </button>
                    </div>
                    @error('children_count')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Adults --}}
                <div class="bg-green-50 border border-green-200 rounded-xl px-5 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            <span class="text-sm font-semibold text-green-700">Adults</span>
                        </div>
                        <span class="text-xs text-green-500">18 – 64</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="adults = Math.max(0, adults - 1)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-green-300 text-green-600 hover:bg-green-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                        </button>
                        <input type="number" name="adults_count" x-model.number="adults"
                               min="0" max="50"
                               class="flex-1 text-center px-2 py-2 text-lg font-bold border border-green-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-300">
                        <button type="button" @click="adults = Math.min(50, adults + 1)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-green-300 text-green-600 hover:bg-green-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </button>
                    </div>
                    @error('adults_count')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Seniors --}}
                <div class="bg-purple-50 border border-purple-200 rounded-xl px-5 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                            </svg>
                            <span class="text-sm font-semibold text-purple-700">Seniors</span>
                        </div>
                        <span class="text-xs text-purple-500">65+</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="seniors = Math.max(0, seniors - 1)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-purple-300 text-purple-600 hover:bg-purple-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                        </button>
                        <input type="number" name="seniors_count" x-model.number="seniors"
                               min="0" max="50"
                               class="flex-1 text-center px-2 py-2 text-lg font-bold border border-purple-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-purple-300">
                        <button type="button" @click="seniors = Math.min(50, seniors + 1)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-purple-300 text-purple-600 hover:bg-purple-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </button>
                    </div>
                    @error('seniors_count')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Computed total --}}
            <div class="flex items-center justify-between bg-navy-700 text-white rounded-xl px-5 py-3.5">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-white/70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                    <span class="text-sm font-semibold">Total Household Size</span>
                    <span class="text-xs text-white/60 ml-1">(auto-calculated)</span>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold" x-text="total"></span>
                    <span class="text-sm text-white/70" x-text="total === 1 ? 'person' : 'people'"></span>
                </div>
            </div>
        </div>

        {{-- ── Vehicle Information ──────────────────────────────────────── --}}
        <div class="flex items-center gap-3 pt-1">
            <div class="flex-1 h-px bg-gray-200"></div>
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Vehicle Information</span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Vehicle Make{{ $householdSettings['require_vehicle_info'] ? ' *' : '' }}
                    <span class="text-xs font-normal text-gray-400 ml-1">e.g. Toyota</span>
                </label>
                <input type="text" name="vehicle_make"
                       value="{{ old('vehicle_make', $household->vehicle_make ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="Toyota">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Vehicle Color{{ $householdSettings['require_vehicle_info'] ? ' *' : '' }}
                    <span class="text-xs font-normal text-gray-400 ml-1">e.g. Silver</span>
                </label>
                <input type="text" name="vehicle_color"
                       value="{{ old('vehicle_color', $household->vehicle_color ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                       placeholder="Silver">
            </div>
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea name="notes" rows="3"
                      class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white resize-none
                             focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 placeholder:text-gray-400"
                      placeholder="Any additional notes about this household...">{{ old('notes', $household->notes ?? '') }}</textarea>
        </div>

    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{--  SECTION 2 · Represented Households                                      --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div x-data="representedForm({{ json_encode($existingRepresented) }})"
     class="space-y-3 mx-1 sm:mx-4">

    {{-- Section header --}}
    <div class="flex items-center justify-between bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-4">
        <div class="flex items-center gap-2.5">
            <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Represented Households</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    Other households this person is picking up food for
                    <span class="font-medium text-gray-500" x-text="activeCount() > 0 ? '(' + activeCount() + ' added)' : '(optional)'"></span>
                </p>
            </div>
        </div>
        <button type="button" @click="addHousehold()"
                class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white
                       text-sm font-semibold rounded-xl px-4 py-2.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add Household
        </button>
    </div>

    {{-- Represented household cards --}}
    <template x-for="(hh, index) in households" :key="hh._uid">
        <div x-show="!hh._detach"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="bg-white rounded-2xl border border-indigo-200 shadow-sm overflow-hidden">

            {{-- Hidden fields --}}
            <template x-if="hh.id">
                <input type="hidden" :name="'represented_households[' + index + '][id]'" :value="hh.id">
            </template>
            <input type="hidden" :name="'represented_households[' + index + '][_detach]'" :value="hh._detach ? '1' : '0'">

            {{-- Card header --}}
            <div class="flex items-center justify-between px-6 py-3.5 border-b border-indigo-100 bg-indigo-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-6 h-6 rounded-full bg-indigo-200 flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-bold text-indigo-700" x-text="activeIndex(index) + 1"></span>
                    </div>
                    <span class="text-sm font-semibold text-indigo-800"
                          x-text="(hh.first_name || hh.last_name)
                              ? (hh.first_name + ' ' + hh.last_name).trim()
                              : 'Household ' + (activeIndex(index) + 1)">
                    </span>
                    <span x-show="hh.id" class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full font-medium">existing</span>
                </div>
                <button type="button" @click="removeHousehold(index)"
                        class="flex items-center gap-1 text-xs font-semibold text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    <span x-text="hh.id ? 'Unlink' : 'Remove'"></span>
                </button>
            </div>

            {{-- Card fields --}}
            <div class="px-6 py-5 space-y-4">

                {{-- Name --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">First Name <span class="text-red-500">*</span></label>
                        <input type="text"
                               :name="'represented_households[' + index + '][first_name]'"
                               x-model="hh.first_name"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 placeholder:text-gray-400"
                               placeholder="First name">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Last Name <span class="text-red-500">*</span></label>
                        <input type="text"
                               :name="'represented_households[' + index + '][last_name]'"
                               x-model="hh.last_name"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 placeholder:text-gray-400"
                               placeholder="Last name">
                    </div>
                </div>

                {{-- Email + Phone --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Email</label>
                        <input type="email"
                               :name="'represented_households[' + index + '][email]'"
                               x-model="hh.email"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 placeholder:text-gray-400"
                               placeholder="email@example.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone</label>
                        <input type="text"
                               :name="'represented_households[' + index + '][phone]'"
                               x-model="hh.phone"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 placeholder:text-gray-400"
                               placeholder="(215) 555-0100">
                    </div>
                </div>

                {{-- Demographic breakdown --}}
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">Household Composition <span class="text-red-500">*</span></p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl px-3 py-3">
                            <p class="text-xs font-semibold text-blue-600 mb-2 text-center">Children <span class="font-normal opacity-70">&lt;18</span></p>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="hh.children_count = Math.max(0, (parseInt(hh.children_count)||0) - 1)"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-100 transition-colors flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                </button>
                                <input type="number"
                                       :name="'represented_households[' + index + '][children_count]'"
                                       x-model.number="hh.children_count"
                                       min="0" max="50"
                                       class="flex-1 text-center px-1 py-1.5 text-base font-bold border border-blue-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
                                <button type="button" @click="hh.children_count = Math.min(50, (parseInt(hh.children_count)||0) + 1)"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-100 transition-colors flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-xl px-3 py-3">
                            <p class="text-xs font-semibold text-green-700 mb-2 text-center">Adults <span class="font-normal opacity-70">18–64</span></p>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="hh.adults_count = Math.max(0, (parseInt(hh.adults_count)||0) - 1)"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-green-300 text-green-600 hover:bg-green-100 transition-colors flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                </button>
                                <input type="number"
                                       :name="'represented_households[' + index + '][adults_count]'"
                                       x-model.number="hh.adults_count"
                                       min="0" max="50"
                                       class="flex-1 text-center px-1 py-1.5 text-base font-bold border border-green-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-300">
                                <button type="button" @click="hh.adults_count = Math.min(50, (parseInt(hh.adults_count)||0) + 1)"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-green-300 text-green-600 hover:bg-green-100 transition-colors flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="bg-purple-50 border border-purple-200 rounded-xl px-3 py-3">
                            <p class="text-xs font-semibold text-purple-700 mb-2 text-center">Seniors <span class="font-normal opacity-70">65+</span></p>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="hh.seniors_count = Math.max(0, (parseInt(hh.seniors_count)||0) - 1)"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-purple-300 text-purple-600 hover:bg-purple-100 transition-colors flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                </button>
                                <input type="number"
                                       :name="'represented_households[' + index + '][seniors_count]'"
                                       x-model.number="hh.seniors_count"
                                       min="0" max="50"
                                       class="flex-1 text-center px-1 py-1.5 text-base font-bold border border-purple-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-purple-300">
                                <button type="button" @click="hh.seniors_count = Math.min(50, (parseInt(hh.seniors_count)||0) + 1)"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-purple-300 text-purple-600 hover:bg-purple-100 transition-colors flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    {{-- Mini total --}}
                    <div class="mt-2 flex items-center justify-end gap-1.5 text-sm text-gray-600">
                        <span class="text-xs text-gray-400">Total:</span>
                        <span class="font-bold text-gray-900"
                              x-text="(parseInt(hh.children_count)||0) + (parseInt(hh.adults_count)||0) + (parseInt(hh.seniors_count)||0)"></span>
                        <span class="text-xs text-gray-400">people</span>
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Notes</label>
                    <textarea :name="'represented_households[' + index + '][notes]'"
                              x-model="hh.notes"
                              rows="2"
                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white resize-none
                                     focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 placeholder:text-gray-400"
                              placeholder="Any notes about this household..."></textarea>
                </div>

            </div>
        </div>
    </template>

    {{-- Empty state --}}
    <div x-show="activeCount() === 0"
         class="text-center py-8 text-sm text-gray-400 bg-white rounded-2xl border border-dashed border-gray-300">
        <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
        </svg>
        No represented households added. Click "Add Household" above if this person is picking up for others.
    </div>

</div>

@push('scripts')
<script>
function representedForm(existing) {
    return {
        households: existing.map((hh, i) => ({ ...hh, _uid: i, _detach: false })),
        _uidCounter: existing.length,

        addHousehold() {
            this.households.push({
                _uid: this._uidCounter++,
                id: null,
                first_name:     '',
                last_name:      '',
                email:          '',
                phone:          '',
                children_count: 0,
                adults_count:   1,
                seniors_count:  0,
                notes:          '',
                _detach:        false,
            });
        },

        removeHousehold(index) {
            const hh = this.households[index];
            if (hh.id) {
                // Existing record: mark for detach (keeps the household, just unlinks it)
                hh._detach = true;
            } else {
                // New record: just remove from array
                this.households.splice(index, 1);
            }
        },

        activeCount() {
            return this.households.filter(hh => !hh._detach).length;
        },

        // Visual index (1, 2, 3...) counting only active (non-detached) entries up to this one
        activeIndex(index) {
            let count = 0;
            for (let i = 0; i < index; i++) {
                if (!this.households[i]._detach) count++;
            }
            return count;
        },
    };
}
</script>
@endpush
