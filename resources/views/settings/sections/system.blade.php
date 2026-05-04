{{-- System Preferences Section --}}
<div class="space-y-6">

    {{-- Maintenance --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 6 0m-6 0H3m16.5 0a3 3 0 0 0 3-3m-3 3a3 3 0 1 1-6 0m6 0h1.5m-7.5-9a3 3 0 0 1 3-3m-3 3a3 3 0 1 0-6 0m6 0H3m16.5 0a3 3 0 0 0-3-3m3 3a3 3 0 1 1 6 0m-6 0h1.5"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">System Status</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls application availability and debug options.</p>
            </div>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'maintenance_mode',     'def' => $definitions['maintenance_mode'],     'settings' => $settings])
            @include('settings._field', ['key' => 'show_debug_to_admin',  'def' => $definitions['show_debug_to_admin'],  'settings' => $settings])
        </div>
    </div>

    {{-- Display & Pagination --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Display & Pagination</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'default_pagination_limit', 'def' => $definitions['default_pagination_limit'], 'settings' => $settings])
                @include('settings._field', ['key' => 'chart_default_period',     'def' => $definitions['chart_default_period'],     'settings' => $settings])
            </div>
        </div>
    </div>

    {{-- Export & Data --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Export & Data Lifecycle</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            @include('settings._field', ['key' => 'report_export_format', 'def' => $definitions['report_export_format'], 'settings' => $settings])

            <div class="space-y-5 pt-1">
                @include('settings._field', ['key' => 'soft_delete_enabled',                    'def' => $definitions['soft_delete_enabled'],                    'settings' => $settings])
                @include('settings._field', ['key' => 'archive_completed_events_after_days',    'def' => $definitions['archive_completed_events_after_days'],    'settings' => $settings])
            </div>
        </div>
    </div>

    <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs leading-relaxed">
        <strong>Warning:</strong> Maintenance Mode will prevent non-admin users from accessing the application.
        Only enable this during planned maintenance windows. Admins will still be able to log in.
    </div>

</div>
