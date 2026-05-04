{{-- Finance Settings Section --}}
<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Currency & Display</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls how monetary values are shown throughout the app.</p>
            </div>
        </div>
        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'currency_symbol',   'def' => $definitions['currency_symbol'],   'settings' => $settings])
                @include('settings._field', ['key' => 'decimal_precision', 'def' => $definitions['decimal_precision'], 'settings' => $settings])
            </div>
            @include('settings._field', ['key' => 'default_date_range', 'def' => $definitions['default_date_range'], 'settings' => $settings])
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Attachments & Transactions</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            @include('settings._field', ['key' => 'allow_attachments',       'def' => $definitions['allow_attachments'],       'settings' => $settings])
            @include('settings._field', ['key' => 'allowed_attachment_types','def' => $definitions['allowed_attachment_types'],'settings' => $settings])
            @include('settings._field', ['key' => 'require_category',        'def' => $definitions['require_category'],        'settings' => $settings])
            @include('settings._field', ['key' => 'allow_draft_expenses',    'def' => $definitions['allow_draft_expenses'],    'settings' => $settings])
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Reporting</h3>
        </div>
        <div class="px-6 py-6">
            @include('settings._field', ['key' => 'enable_event_metrics', 'def' => $definitions['enable_event_metrics'], 'settings' => $settings])
        </div>
    </div>

</div>
