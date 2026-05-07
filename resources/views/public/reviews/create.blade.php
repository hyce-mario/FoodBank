@extends('layouts.public')
@section('title', 'Leave a Review')

@section('content')

{{-- Thank-you modal (shown after successful submission) --}}
@if (session('reviewed'))
<div x-data="{ open: true }"
     x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center px-4"
     style="background: rgba(0,0,0,0.45);">
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-8 text-center">

        {{-- Success icon --}}
        <div class="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-5">
            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </div>

        <h2 class="text-xl font-bold text-gray-900 mb-3">Thank You!</h2>
        <p class="text-sm text-gray-500 leading-relaxed mb-6">
            Thank you for your feedback. Your review helps us improve future events and better serve the community.
        </p>

        <div class="flex flex-col sm:flex-row gap-3">
            <button type="button" @click="open = false"
                    class="flex-1 px-5 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                Close
            </button>
            <a href="{{ route('public.events') }}"
               class="flex-1 px-5 py-2.5 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors text-center">
                View Events
            </a>
        </div>
    </div>
</div>
@endif

{{-- Page heading --}}
<div class="mb-6 flex items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-black text-gray-900">Share Your Feedback</h1>
        <p class="text-sm text-gray-500 mt-1">Your review helps us improve future events and serve the community better.</p>
    </div>
    <a href="{{ route('public.events') }}"
       class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700
              bg-brand-50 hover:bg-brand-100 border border-brand-200 rounded-xl px-4 py-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
        </svg>
        Register for an Event
    </a>
</div>

<form method="POST" action="{{ route('public.reviews.store') }}"
      x-data="reviewForm()" x-cloak>
    @csrf
    <x-bot-defense />

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 space-y-6">

            {{-- Validation errors --}}
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ── Event selector ─────────────────────────────────────────── --}}
            <div>
                <label for="event_id" class="block text-sm font-semibold text-gray-700 mb-2">
                    Which event are you reviewing? <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <select id="event_id" name="event_id"
                            class="w-full px-3.5 py-3 text-sm border rounded-xl bg-white appearance-none
                                   focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                   {{ $errors->has('event_id') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                        <option value="">— Select an event —</option>

                        @if ($todayEvents->isNotEmpty())
                            <optgroup label="Today's Events">
                                @foreach ($todayEvents as $event)
                                    <option value="{{ $event->id }}"
                                            {{ old('event_id') == $event->id ? 'selected' : '' }}>
                                        {{ $event->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif

                        @if ($pastEvents->isNotEmpty())
                            <optgroup label="Past Events">
                                @foreach ($pastEvents as $event)
                                    <option value="{{ $event->id }}"
                                            {{ old('event_id') == $event->id ? 'selected' : '' }}>
                                        {{ $event->name }} — {{ $event->date->format('M j, Y') }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- ── Star rating ─────────────────────────────────────────────── --}}
            <div>
                <p class="block text-sm font-semibold text-gray-700 mb-3">
                    How would you rate your experience? <span class="text-red-500">*</span>
                </p>

                {{-- Hidden input carries the value --}}
                <input type="hidden" name="rating" :value="rating">

                {{-- Stars --}}
                <div class="flex items-center gap-2" role="group" aria-label="Star rating">
                    <template x-for="star in 5" :key="star">
                        <button type="button"
                                @click="rating = star"
                                @mouseover="hovered = star"
                                @mouseleave="hovered = 0"
                                :aria-label="'Rate ' + star + ' out of 5'"
                                :class="(hovered || rating) >= star
                                    ? 'text-yellow-400 scale-110'
                                    : 'text-gray-300 hover:text-yellow-300'"
                                class="text-5xl leading-none w-14 h-14 flex items-center justify-center
                                       transition-all duration-100 select-none touch-manipulation focus:outline-none">
                            ★
                        </button>
                    </template>
                </div>

                {{-- Label beneath stars --}}
                <p class="mt-2 text-sm font-medium text-brand-600 h-5"
                   x-text="ratingLabel"></p>

                @error('rating')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Review text ──────────────────────────────────────────────── --}}
            <div>
                <label for="review_text" class="block text-sm font-semibold text-gray-700 mb-2">
                    Tell us about your experience <span class="text-red-500">*</span>
                </label>
                <textarea id="review_text" name="review_text"
                          x-model="reviewText"
                          rows="5"
                          maxlength="2000"
                          placeholder="Share your experience with the event, staff, organization, or food distribution process."
                          class="w-full px-3.5 py-3 text-sm border rounded-xl bg-white resize-none
                                 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                 placeholder:text-gray-300
                                 {{ $errors->has('review_text') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">{{ old('review_text') }}</textarea>
                @error('review_text')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── Optional fields ─────────────────────────────────────────── --}}
            <div class="border-t border-gray-100 pt-5 space-y-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Optional Details</p>

                <div>
                    <label for="reviewer_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Your Name
                    </label>
                    <input type="text" id="reviewer_name" name="reviewer_name"
                           value="{{ old('reviewer_name') }}"
                           placeholder="e.g. Jane D."
                           class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl bg-white
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  placeholder:text-gray-300">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email') }}"
                           placeholder="your@email.com"
                           class="w-full px-3.5 py-2.5 text-sm border rounded-xl bg-white
                                  focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                  placeholder:text-gray-300
                                  {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                    @error('email')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

        </div>

        {{-- Submit --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button type="submit"
                    :disabled="!rating || !hasText"
                    :class="(!rating || !hasText) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-brand-600 active:scale-95'"
                    class="w-full py-3.5 text-sm font-bold bg-brand-500 text-white rounded-xl transition-all">
                Submit Review
            </button>
            <p class="text-xs text-gray-400 text-center mt-2">
                Reviews are public and visible to our staff.
            </p>
        </div>
    </div>

</form>

@push('scripts')
<script>
function reviewForm() {
    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    return {
        rating:     {{ old('rating', 0) }},
        hovered:    0,
        reviewText: @json(old('review_text', '')),
        get ratingLabel() {
            const val = this.hovered || this.rating;
            return val ? labels[val] : '';
        },
        get hasText() {
            return this.reviewText.trim().length > 0;
        },
    };
}
</script>
@endpush

@endsection
