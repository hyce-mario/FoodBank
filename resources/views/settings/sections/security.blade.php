{{-- Users & Security Settings Section --}}
<div class="space-y-6">

    {{-- Session & Passwords --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Sessions & Passwords</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls admin login behavior and password policy.</p>
            </div>
        </div>
        <div class="px-6 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'session_timeout_minutes', 'def' => $definitions['session_timeout_minutes'], 'settings' => $settings])
                @include('settings._field', ['key' => 'password_min_length',     'def' => $definitions['password_min_length'],     'settings' => $settings])
            </div>
            @include('settings._field', ['key' => 'require_strong_password', 'def' => $definitions['require_strong_password'], 'settings' => $settings])
        </div>
    </div>

    {{-- User Management --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">User Management</h3>
        </div>
        <div class="px-6 py-6 space-y-6">
            @include('settings._field', ['key' => 'default_new_user_role',    'def' => $definitions['default_new_user_role'],    'settings' => $settings])

            <div class="space-y-4 pt-1">
                @include('settings._field', ['key' => 'allow_user_deactivation', 'def' => $definitions['allow_user_deactivation'], 'settings' => $settings])
                @include('settings._field', ['key' => 'allow_self_delete',        'def' => $definitions['allow_self_delete'],        'settings' => $settings])
            </div>
        </div>
    </div>

    {{-- Role Protection --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Role Protection</h3>
        </div>
        <div class="px-6 py-6 space-y-5">
            @include('settings._field', ['key' => 'protect_system_roles',     'def' => $definitions['protect_system_roles'],     'settings' => $settings])
            @include('settings._field', ['key' => 'role_deletion_protection', 'def' => $definitions['role_deletion_protection'], 'settings' => $settings])
            @include('settings._field', ['key' => 'audit_logging_enabled',    'def' => $definitions['audit_logging_enabled'],    'settings' => $settings])
        </div>
    </div>

</div>
