<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mx-1 sm:mx-4">

    <div class="flex items-center gap-2.5 px-6 py-4 border-b border-gray-100">
        <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-3.5 h-3.5 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
        </div>
        <h2 class="text-sm font-semibold text-gray-800">Volunteer Information</h2>
    </div>

    <div class="px-8 py-7 space-y-6">

        {{-- First + Last Name --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    First Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="first_name"
                       value="{{ old('first_name', $volunteer->first_name ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('first_name') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="First name">
                @error('first_name')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Last Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="last_name"
                       value="{{ old('last_name', $volunteer->last_name ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('last_name') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="Last name">
                @error('last_name')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Phone + Email --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                <input type="text" name="phone"
                       value="{{ old('phone', $volunteer->phone ?? '') }}"
                       class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="(215) 555-0100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" name="email"
                       value="{{ old('email', $volunteer->email ?? '') }}"
                       class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                              @error('email') border-red-400 bg-red-50 @else border-gray-300 @enderror
                              focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                              placeholder:text-gray-400"
                       placeholder="volunteer@example.com">
                @error('email')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Operational Role --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Operational Role</label>
            <p class="text-xs text-gray-400 mb-3">The primary function this volunteer performs at events.</p>
            <select name="role"
                    class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white
                           focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                           text-gray-700 cursor-pointer">
                <option value="">— No role assigned —</option>
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}"
                            @selected(old('role', $volunteer->role ?? '') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('role')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

    </div>
</div>
