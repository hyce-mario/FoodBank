{{-- Event & Queue Settings Section --}}
<div class="space-y-6">

    {{-- Queue Behavior --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Queue Behavior</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls how the live queue operates on event days.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'allow_lane_drag',     'def' => $definitions['allow_lane_drag'],     'settings' => $settings])
            @include('settings._field', ['key' => 'allow_queue_reorder', 'def' => $definitions['allow_queue_reorder'], 'settings' => $settings])
            @include('settings._field', ['key' => 're_checkin_policy',   'def' => $definitions['re_checkin_policy'],   'settings' => $settings])
            @include('settings._field', ['key' => 'show_family_breakdown','def' => $definitions['show_family_breakdown'],'settings' => $settings])
            @include('settings._field', ['key' => 'show_vehicle_info_queue','def' => $definitions['show_vehicle_info_queue'],'settings' => $settings])
            @include('settings._field', ['key' => 'show_household_names_scanner','def' => $definitions['show_household_names_scanner'],'settings' => $settings])
        </div>
    </div>

    {{-- Event Defaults --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Event Defaults</h3>
        </div>
        <div class="px-6 py-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'default_lane_count',    'def' => $definitions['default_lane_count'],    'settings' => $settings])
                @include('settings._field', ['key' => 'queue_poll_interval',   'def' => $definitions['queue_poll_interval'],   'settings' => $settings])
            </div>

        </div>
    </div>

    {{-- Bag Calculation --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Bag Calculation</h3>
        </div>
        <div class="px-6 py-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'bag_calculation_strategy',  'def' => $definitions['bag_calculation_strategy'],  'settings' => $settings])
                @include('settings._field', ['key' => 'default_bags_per_person',   'def' => $definitions['default_bags_per_person'],   'settings' => $settings])
            </div>

        </div>
    </div>

</div>
