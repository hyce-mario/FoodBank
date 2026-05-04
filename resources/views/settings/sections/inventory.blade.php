{{-- Inventory Settings Section --}}
<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Stock Thresholds</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls when items are flagged as low or out of stock.</p>
            </div>
        </div>
        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'low_stock_threshold', 'def' => $definitions['low_stock_threshold'], 'settings' => $settings])
                @include('settings._field', ['key' => 'out_of_stock_behavior','def' => $definitions['out_of_stock_behavior'],'settings' => $settings])
            </div>
            @include('settings._field', ['key' => 'allow_negative_stock', 'def' => $definitions['allow_negative_stock'], 'settings' => $settings])
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Behavior & Display</h3>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'require_movement_notes',    'def' => $definitions['require_movement_notes'],    'settings' => $settings])
            @include('settings._field', ['key' => 'show_inactive_items',       'def' => $definitions['show_inactive_items'],       'settings' => $settings])
            @include('settings._field', ['key' => 'enable_event_allocations',  'def' => $definitions['enable_event_allocations'],  'settings' => $settings])
            @include('settings._field', ['key' => 'dashboard_low_stock_alert', 'def' => $definitions['dashboard_low_stock_alert'], 'settings' => $settings])
        </div>
    </div>

</div>
