{{-- Notifications & Contact Settings Section --}}
<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Email Sender Configuration</h2>
                <p class="text-xs text-gray-500 mt-0.5">Used as the From and Reply-To for all system emails.</p>
            </div>
        </div>
        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'sender_name',   'def' => $definitions['sender_name'],   'settings' => $settings])
                @include('settings._field', ['key' => 'sender_email',  'def' => $definitions['sender_email'],  'settings' => $settings])
            </div>
            @include('settings._field', ['key' => 'reply_to_email', 'def' => $definitions['reply_to_email'], 'settings' => $settings])
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Organization Contact Info</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'support_email',        'def' => $definitions['support_email'],        'settings' => $settings])
                @include('settings._field', ['key' => 'public_contact_email', 'def' => $definitions['public_contact_email'], 'settings' => $settings])
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Admin Notifications</h3>
            <p class="text-xs text-gray-500 mt-0.5">Email notifications sent to administrators.</p>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'notify_admin_on_review',   'def' => $definitions['notify_admin_on_review'],   'settings' => $settings])
            @include('settings._field', ['key' => 'notify_low_stock',         'def' => $definitions['notify_low_stock'],         'settings' => $settings])
            @include('settings._field', ['key' => 'enable_event_day_alerts',  'def' => $definitions['enable_event_day_alerts'],  'settings' => $settings])
        </div>
    </div>

    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs leading-relaxed">
        <strong>Email sending not yet configured?</strong> These settings store your preferences. Actual email delivery requires setting up an SMTP driver in your <code>.env</code> file (MAIL_MAILER, MAIL_HOST, etc.).
    </div>

</div>
