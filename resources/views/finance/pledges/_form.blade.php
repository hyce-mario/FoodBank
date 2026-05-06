{{-- Phase 7.4.c — shared form partial for create + edit. --}}
@csrf

@if ($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <ul class="list-disc list-inside">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Donor / source name <span class="text-red-500">*</span></label>
        <input type="text" name="source_or_payee" required maxlength="200"
               value="{{ old('source_or_payee', $pledge->source_or_payee ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Linked household <span class="text-gray-400 text-xs">(optional)</span></label>
        <select name="household_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">— New / unlinked donor —</option>
            @foreach ($households as $hh)
                <option value="{{ $hh->id }}" {{ old('household_id', $pledge->household_id ?? '') == $hh->id ? 'selected' : '' }}>
                    {{ trim($hh->first_name . ' ' . $hh->last_name) }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
        <input type="number" name="amount" required step="0.01" min="0.01"
               value="{{ old('amount', $pledge->amount ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
        <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            @foreach (\App\Models\Pledge::STATUS_LABELS as $val => $lbl)
                <option value="{{ $val }}" {{ old('status', $pledge->status ?? 'open') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Pledged on <span class="text-red-500">*</span></label>
        <input type="date" name="pledged_at" required
               value="{{ old('pledged_at', isset($pledge) ? $pledge->pledged_at?->format('Y-m-d') : now()->format('Y-m-d')) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Expected by <span class="text-red-500">*</span></label>
        <input type="date" name="expected_at" required
               value="{{ old('expected_at', isset($pledge) ? $pledge->expected_at?->format('Y-m-d') : now()->addDays(30)->format('Y-m-d')) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Received on <span class="text-gray-400 text-xs">(optional)</span></label>
        <input type="date" name="received_at"
               value="{{ old('received_at', isset($pledge) ? $pledge->received_at?->format('Y-m-d') : '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Income category <span class="text-gray-400 text-xs">(optional)</span></label>
        <select name="category_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">— None —</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" {{ old('category_id', $pledge->category_id ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Linked event <span class="text-gray-400 text-xs">(optional)</span></label>
        <select name="event_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">— None —</option>
            @foreach ($events as $ev)
                <option value="{{ $ev->id }}" {{ old('event_id', $pledge->event_id ?? '') == $ev->id ? 'selected' : '' }}>
                    {{ $ev->name }} ({{ $ev->date->format('M j, Y') }})
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="mt-4">
    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
    <textarea name="notes" rows="2" maxlength="1000"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $pledge->notes ?? '') }}</textarea>
</div>
<div class="flex justify-end gap-2 mt-5">
    <a href="{{ route('finance.pledges.index') }}"
       class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</a>
    <button type="submit"
            class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-lg">{{ $submitLabel ?? 'Save' }}</button>
</div>
