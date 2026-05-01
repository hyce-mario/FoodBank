{{-- General Settings Section --}}
<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">General Settings</h2>
                <p class="text-xs text-gray-500 mt-0.5">Core application-wide preferences.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">

            {{-- App Name --}}
            @include('settings._field', ['key' => 'app_name', 'def' => $definitions['app_name'], 'settings' => $settings])

            {{-- Timezone + Date Format --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'timezone', 'def' => $definitions['timezone'], 'settings' => $settings])
                @include('settings._field', ['key' => 'date_format', 'def' => $definitions['date_format'], 'settings' => $settings])
            </div>

            {{-- Time Format + Currency --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'time_format', 'def' => $definitions['time_format'], 'settings' => $settings])
                @include('settings._field', ['key' => 'currency', 'def' => $definitions['currency'], 'settings' => $settings])
            </div>

            {{-- Records Per Page + Dashboard Default Event --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'records_per_page', 'def' => $definitions['records_per_page'], 'settings' => $settings])
                @include('settings._field', ['key' => 'dashboard_default_event', 'def' => $definitions['dashboard_default_event'], 'settings' => $settings])
            </div>

        </div>
    </div>

    {{-- Media Uploads card — controls the photos & video uploader on event detail pages. --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Media Uploads</h2>
                <p class="text-xs text-gray-500 mt-0.5">Limits applied to the event photos &amp; video uploader.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'max_upload_size_mb', 'def' => $definitions['max_upload_size_mb'], 'settings' => $settings])
                <div></div>
            </div>

            @include('settings._field', ['key' => 'allowed_upload_formats', 'def' => $definitions['allowed_upload_formats'], 'settings' => $settings])
        </div>
    </div>

</div>
