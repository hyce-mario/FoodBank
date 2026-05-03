{{-- Branding & Theme Section — Brand Colors content rendered INSIDE the
     parent settings PUT form. The Logo & Favicon upload card lives in
     branding_above.blade.php (rendered above the form by show.blade.php
     since it has its own POST/DELETE forms). --}}

<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Brand Colors</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls the visual identity across the admin interface.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @include('settings._field', ['key' => 'primary_color',   'def' => $definitions['primary_color'],   'settings' => $settings])
                @include('settings._field', ['key' => 'secondary_color', 'def' => $definitions['secondary_color'], 'settings' => $settings])
                @include('settings._field', ['key' => 'accent_color',    'def' => $definitions['accent_color'],    'settings' => $settings])
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'sidebar_bg',     'def' => $definitions['sidebar_bg'],     'settings' => $settings])
                @include('settings._field', ['key' => 'nav_text_color', 'def' => $definitions['nav_text_color'], 'settings' => $settings])
            </div>

            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                <strong>Note:</strong> Color changes currently store your preferences. Full dynamic theming requires a rebuild of Tailwind CSS classes. These values are available via <code>SettingService::get('branding.primary_color')</code> for use in custom CSS or inline styles.
            </div>

        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Display Options</h3>
        </div>
        <div class="px-6 py-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'logo_display', 'def' => $definitions['logo_display'], 'settings' => $settings])
                @include('settings._field', ['key' => 'appearance',   'def' => $definitions['appearance'],   'settings' => $settings])
            </div>

        </div>
    </div>

</div>
