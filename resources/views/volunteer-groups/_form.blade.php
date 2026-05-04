<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mx-1 sm:mx-4">

    <div class="flex items-center gap-2.5 px-6 py-4 border-b border-gray-100">
        <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-3.5 h-3.5 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
            </svg>
        </div>
        <h2 class="text-sm font-semibold text-gray-800">Group Information</h2>
    </div>

    <div class="px-8 py-7 space-y-6">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Group Name <span class="text-red-500">*</span>
            </label>
            <input type="text" name="name"
                   value="{{ old('name', $volunteerGroup->name ?? '') }}"
                   class="w-full px-4 py-3 text-sm border rounded-xl bg-white transition-colors
                          @error('name') border-red-400 bg-red-50 @else border-gray-300 @enderror
                          focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                          placeholder:text-gray-400"
                   placeholder="e.g. Saturday Drivers, Intake Team A">
            @error('name')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea name="description" rows="4"
                      class="w-full px-4 py-3 text-sm border border-gray-300 rounded-xl bg-white resize-none
                             focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                             placeholder:text-gray-400"
                      placeholder="What does this group do? When do they serve?">{{ old('description', $volunteerGroup->description ?? '') }}</textarea>
            @error('description')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

    </div>
</div>
