{{-- Phase 7.4.b — shared form partial for create + edit. --}}
@csrf

@if ($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <ul class="list-disc list-inside">
        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
        <select name="category_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}"
                        {{ old('category_id', $budget->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }} ({{ $cat->type }})
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Event scope <span class="text-gray-400 text-xs">(optional)</span></label>
        <select name="event_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">— Org-wide budget —</option>
            @foreach ($events as $ev)
                <option value="{{ $ev->id }}"
                        {{ old('event_id', $budget->event_id ?? '') == $ev->id ? 'selected' : '' }}>
                    {{ $ev->name }} ({{ $ev->date->format('M j, Y') }})
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Period start <span class="text-red-500">*</span></label>
        <input type="date" name="period_start" required
               value="{{ old('period_start', isset($budget) ? $budget->period_start?->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d')) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Period end <span class="text-red-500">*</span></label>
        <input type="date" name="period_end" required
               value="{{ old('period_end', isset($budget) ? $budget->period_end?->format('Y-m-d') : now()->endOfMonth()->format('Y-m-d')) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
        <input type="number" name="amount" required step="0.01" min="0"
               value="{{ old('amount', $budget->amount ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
</div>
<div class="mt-4">
    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
    <textarea name="notes" rows="2" maxlength="1000"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $budget->notes ?? '') }}</textarea>
</div>
<div class="flex justify-end gap-2 mt-5">
    <a href="{{ route('finance.budgets.index') }}"
       class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</a>
    <button type="submit"
            class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-lg">{{ $submitLabel ?? 'Save' }}</button>
</div>
