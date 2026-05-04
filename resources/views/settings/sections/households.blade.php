{{-- Households & Intake Settings Section --}}
<div class="space-y-6">

    {{-- Household Numbers --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Household Numbers</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls how household IDs are assigned.</p>
            </div>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'auto_generate_household_number', 'def' => $definitions['auto_generate_household_number'], 'settings' => $settings])
            @include('settings._field', ['key' => 'household_number_length',        'def' => $definitions['household_number_length'],        'settings' => $settings])
        </div>
    </div>

    {{-- Required Fields --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Required Fields</h3>
            <p class="text-xs text-gray-500 mt-0.5">Which fields staff must fill in on the household form.</p>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'require_phone',        'def' => $definitions['require_phone'],        'settings' => $settings])
            @include('settings._field', ['key' => 'require_address',      'def' => $definitions['require_address'],      'settings' => $settings])
            @include('settings._field', ['key' => 'require_vehicle_info', 'def' => $definitions['require_vehicle_info'], 'settings' => $settings])
        </div>
    </div>

    {{-- Family Workflow --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Represented Family Workflow</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            @include('settings._field', ['key' => 'enable_represented_families', 'def' => $definitions['enable_represented_families'], 'settings' => $settings])
            @include('settings._field', ['key' => 'max_represented_families',    'def' => $definitions['max_represented_families'],    'settings' => $settings])
        </div>
    </div>

    {{-- Duplicate Warnings --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Duplicate Detection</h3>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'warn_duplicate_email', 'def' => $definitions['warn_duplicate_email'], 'settings' => $settings])
            @include('settings._field', ['key' => 'warn_duplicate_phone', 'def' => $definitions['warn_duplicate_phone'], 'settings' => $settings])
        </div>
    </div>

</div>
