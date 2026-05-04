{{-- Organization Profile Section --}}
<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Organization Profile</h2>
                <p class="text-xs text-gray-500 mt-0.5">Used on public pages, reports, and print headers.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">

            {{-- Org Name --}}
            @include('settings._field', ['key' => 'name', 'def' => $definitions['name'], 'settings' => $settings])

            {{-- Email + Phone --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'email', 'def' => $definitions['email'], 'settings' => $settings])
                @include('settings._field', ['key' => 'phone', 'def' => $definitions['phone'], 'settings' => $settings])
            </div>

            {{-- Website --}}
            @include('settings._field', ['key' => 'website', 'def' => $definitions['website'], 'settings' => $settings])

        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Mailing Address</h3>
        </div>
        <div class="px-6 py-6 space-y-6">

            @include('settings._field', ['key' => 'address_line1', 'def' => $definitions['address_line1'], 'settings' => $settings])
            @include('settings._field', ['key' => 'address_line2', 'def' => $definitions['address_line2'], 'settings' => $settings])

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @include('settings._field', ['key' => 'city', 'def' => $definitions['city'], 'settings' => $settings])
                @include('settings._field', ['key' => 'state', 'def' => $definitions['state'], 'settings' => $settings])
                @include('settings._field', ['key' => 'zip', 'def' => $definitions['zip'], 'settings' => $settings])
            </div>

        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Public Description</h3>
        </div>
        <div class="px-6 py-6">
            @include('settings._field', ['key' => 'about', 'def' => $definitions['about'], 'settings' => $settings])
        </div>
    </div>

</div>
