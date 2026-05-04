{{-- Reviews & Feedback Settings Section --}}
<div class="space-y-6">

    {{-- Review Availability --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Review Availability</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls who can submit reviews and when.</p>
            </div>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'enable_reviews',             'def' => $definitions['enable_reviews'],             'settings' => $settings])
            @include('settings._field', ['key' => 'allow_anonymous',            'def' => $definitions['allow_anonymous'],            'settings' => $settings])
            @include('settings._field', ['key' => 'email_optional',             'def' => $definitions['email_optional'],             'settings' => $settings])
            @include('settings._field', ['key' => 'restrict_to_recent_events',  'def' => $definitions['restrict_to_recent_events'],  'settings' => $settings])
        </div>
    </div>

    {{-- Moderation --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Moderation</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            @include('settings._field', ['key' => 'require_moderation',   'def' => $definitions['require_moderation'],   'settings' => $settings])
            @include('settings._field', ['key' => 'default_visibility',   'def' => $definitions['default_visibility'],   'settings' => $settings])
            @include('settings._field', ['key' => 'show_average_rating',  'def' => $definitions['show_average_rating'],  'settings' => $settings])
        </div>
    </div>

    {{-- Content Rules --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Content Rules</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'min_review_length', 'def' => $definitions['min_review_length'], 'settings' => $settings])
                @include('settings._field', ['key' => 'max_review_length', 'def' => $definitions['max_review_length'], 'settings' => $settings])
            </div>
            @include('settings._field', ['key' => 'thankyou_message', 'def' => $definitions['thankyou_message'], 'settings' => $settings])
        </div>
    </div>

</div>
