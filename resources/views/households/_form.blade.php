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
@endphp

<div x-data="{ open: true }" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mx-1 sm:mx-4">

    {{-- Section header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2.5">
            <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                </svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-800">Basic Information</h2>
        </div>
        <button type="button" @click="open = !open"
                class="w-7 h-7 flex items-center justify-center rounded-full border border-gray-300 text-gray-500
                       hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-0' : 'rotate-180'"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
            </svg>
        </button>
    </div>

    {{-- Form fields --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="px-8 py-7 space-y-6">

        {{-- First + Last Name --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    First Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="first_name"
                       value="{{ old('first_name', $household->first_name ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('first_name') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="First name">
                @error('first_name')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Last Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="last_name"
                       value="{{ old('last_name', $household->last_name ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('last_name') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="Last name">
                @error('last_name')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Email --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
            <input type="email" name="email"
                   value="{{ old('email', $household->email ?? '') }}"
                   class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                          @error('email') border-red-400 bg-red-50 @else border-gray-300 @enderror
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                          placeholder:text-gray-400"
                   placeholder="email@example.com">
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- City + State + Zip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                <input type="text" name="city"
                       value="{{ old('city', $household->city ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="City">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                <select name="state"
                        class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                               focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                               text-gray-700 cursor-pointer">
                    <option value="">--</option>
                    @foreach ($states as $code => $label)
                        <option value="{{ $code }}" @selected(old('state', $household->state ?? '') === $code)>
                            {{ $code }} – {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Zipcode</label>
                <input type="text" name="zip"
                       value="{{ old('zip', $household->zip ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="19103" maxlength="10">
            </div>
        </div>

        {{-- Household Size --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Household Size <span class="text-red-500">*</span>
            </label>
            <input type="number" name="household_size" min="1" max="20"
                   value="{{ old('household_size', $household->household_size ?? 1) }}"
                   class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                          @error('household_size') border-red-400 bg-red-50 @else border-gray-300 @enderror
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
            @error('household_size')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea name="notes" rows="4"
                      class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white resize-none
                             focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                             placeholder:text-gray-400"
                      placeholder="Any additional notes about this household...">{{ old('notes', $household->notes ?? '') }}</textarea>
        </div>

    </div>
</div>
