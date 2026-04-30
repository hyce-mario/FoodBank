{{-- Public Access / Event Auth Settings Section --}}
<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Event Auth Codes</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls how public role auth codes are generated and validated.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">

            <div class="space-y-4">
                {{-- auth_code_length removed: length is hard-coded as Event::AUTH_CODE_LENGTH (4) to match the schema. --}}
                @include('settings._field', ['key' => 'session_timeout_minutes', 'def' => $definitions['session_timeout_minutes'], 'settings' => $settings])
            </div>

            <div class="space-y-4 pt-2">
                @include('settings._field', ['key' => 'allow_code_regeneration',        'def' => $definitions['allow_code_regeneration'],        'settings' => $settings])
                @include('settings._field', ['key' => 'auto_generate_codes',            'def' => $definitions['auto_generate_codes'],            'settings' => $settings])
                @include('settings._field', ['key' => 'one_code_per_role',              'def' => $definitions['one_code_per_role'],              'settings' => $settings])
                @include('settings._field', ['key' => 'require_event_date_validation',  'def' => $definitions['require_event_date_validation'],  'settings' => $settings])
                @include('settings._field', ['key' => 'invalidate_on_completion',       'def' => $definitions['invalidate_on_completion'],       'settings' => $settings])
            </div>

        </div>
    </div>

    <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs leading-relaxed">
        <strong>Public roles</strong> include: Intake, Scanner, Loader, and Exit. Each role accesses event-day pages
        via a short numeric code. These settings control how those codes behave across all events.
    </div>

</div>
