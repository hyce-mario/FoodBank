@extends('layouts.public')
@section('title', 'Register — ' . $event->name)

@section('content')

<div class="mb-5 flex items-center justify-between gap-3">
    <a href="{{ route('public.events') }}"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back to Events
    </a>
    <a href="{{ route('public.reviews.create') }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700
              bg-brand-50 hover:bg-brand-100 border border-brand-200 rounded-xl px-4 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
        </svg>
        Leave a Review
    </a>
</div>

<form method="POST" action="{{ route('public.submit', $event) }}"
      x-data="{
          children: {{ old('children_count', 0) }},
          adults:   {{ old('adults_count', 1) }},
          seniors:  {{ old('seniors_count', 0) }},
          get total() { return (parseInt(this.children)||0) + (parseInt(this.adults)||0) + (parseInt(this.seniors)||0); }
      }">
    @csrf
    <x-bot-defense />

    {{-- Event card header --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-1">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <div class="w-6 h-6 rounded-full bg-brand-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <span class="text-sm font-bold text-gray-800">{{ $event->name }}</span>
            </div>
        </div>

        {{-- Form fields --}}
        <div class="p-6 space-y-5">

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Name --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1.5">
                        First Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="first_name" name="first_name"
                           value="{{ old('first_name') }}"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-white
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  {{ $errors->has('first_name') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Last Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name"
                           value="{{ old('last_name') }}"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-white
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  {{ $errors->has('last_name') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                </div>
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Email <span class="text-red-500">*</span>
                </label>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       class="w-full px-3.5 py-2.5 text-sm border rounded-lg bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
            </div>

            {{-- City / State / Zip --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700 mb-1.5">City</label>
                    <input type="text" id="city" name="city"
                           value="{{ old('city') }}"
                           class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-white
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                </div>
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700 mb-1.5">State</label>
                    <select id="state" name="state"
                            class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-white
                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                        <option value="">--</option>
                        @foreach ($states as $abbr => $name)
                            <option value="{{ $abbr }}" {{ old('state') === $abbr ? 'selected' : '' }}>
                                {{ $abbr }} — {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="zipcode" class="block text-sm font-medium text-gray-700 mb-1.5">Zipcode</label>
                    <input type="text" id="zipcode" name="zipcode"
                           value="{{ old('zipcode') }}" inputmode="numeric"
                           class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-lg bg-white
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                </div>
            </div>

            {{-- Household Composition --}}
            <div class="border-t border-gray-100 pt-4">
                <p class="text-sm font-semibold text-gray-700 mb-1">Household Composition <span class="text-red-500">*</span></p>
                <p class="text-xs text-gray-400 mb-4">How many people are in your household by age group?</p>

                <div class="grid grid-cols-3 gap-3">
                    {{-- Children --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-xl px-3 py-3">
                        <p class="text-xs font-semibold text-blue-600 mb-1.5 text-center">Children <span class="font-normal opacity-70">&lt;18</span></p>
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="children = Math.max(0, children - 1)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-100 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                            </button>
                            <input type="number" name="children_count" x-model.number="children"
                                   min="0" max="50"
                                   class="flex-1 text-center py-1.5 text-base font-bold border border-blue-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 {{ $errors->has('children_count') ? 'border-red-400' : '' }}">
                            <button type="button" @click="children = Math.min(50, children + 1)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-blue-300 text-blue-600 hover:bg-blue-100 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            </button>
                        </div>
                    </div>
                    {{-- Adults --}}
                    <div class="bg-green-50 border border-green-200 rounded-xl px-3 py-3">
                        <p class="text-xs font-semibold text-green-700 mb-1.5 text-center">Adults <span class="font-normal opacity-70">18–64</span></p>
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="adults = Math.max(0, adults - 1)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-green-300 text-green-600 hover:bg-green-100 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                            </button>
                            <input type="number" name="adults_count" x-model.number="adults"
                                   min="0" max="50"
                                   class="flex-1 text-center py-1.5 text-base font-bold border border-green-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-300 {{ $errors->has('adults_count') ? 'border-red-400' : '' }}">
                            <button type="button" @click="adults = Math.min(50, adults + 1)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-green-300 text-green-600 hover:bg-green-100 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            </button>
                        </div>
                    </div>
                    {{-- Seniors --}}
                    <div class="bg-purple-50 border border-purple-200 rounded-xl px-3 py-3">
                        <p class="text-xs font-semibold text-purple-700 mb-1.5 text-center">Seniors <span class="font-normal opacity-70">65+</span></p>
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="seniors = Math.max(0, seniors - 1)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-purple-300 text-purple-600 hover:bg-purple-100 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                            </button>
                            <input type="number" name="seniors_count" x-model.number="seniors"
                                   min="0" max="50"
                                   class="flex-1 text-center py-1.5 text-base font-bold border border-purple-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-purple-300 {{ $errors->has('seniors_count') ? 'border-red-400' : '' }}">
                            <button type="button" @click="seniors = Math.min(50, seniors + 1)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-purple-300 text-purple-600 hover:bg-purple-100 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Total --}}
                <div class="mt-3 flex items-center justify-between px-4 py-2.5 bg-brand-50 rounded-xl border border-brand-100">
                    <span class="text-sm font-medium text-brand-700">Total People</span>
                    <span class="text-lg font-black text-brand-600" x-text="total"></span>
                </div>
            </div>

        </div>
    </div>

    {{-- Action buttons --}}
    <div class="flex items-center justify-end gap-3 mt-5">
        <a href="{{ route('public.events') }}"
           class="px-6 py-2.5 text-sm font-semibold bg-navy-700 hover:bg-navy-800 text-white rounded-xl transition-colors">
            Cancel
        </a>
        <button type="submit"
                class="px-6 py-2.5 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
            Register
        </button>
    </div>

</form>

@endsection
