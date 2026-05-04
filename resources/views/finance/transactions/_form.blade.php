{{--
    Shared form partial for create & edit.
    Required vars: $categories, $events
    Optional vars: $transaction (edit mode), $preType, $preEventId
--}}
@php
    $tx        = $transaction ?? null;
    $typeVal   = old('transaction_type', $tx?->transaction_type ?? ($preType ?? 'expense'));
    $eventIdV  = old('event_id', $tx?->event_id ?? ($preEventId ?? ''));
@endphp

<div x-data="{ txType: @js($typeVal) }">

    {{-- ── Type Toggle ─────────────────────────────────────────────────── --}}
    <div class="mb-6 flex gap-2">
        <button type="button"
                @click="txType = 'expense'"
                :class="txType === 'expense' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                class="flex-1 px-4 py-2.5 text-sm font-semibold border rounded-xl transition-colors">
            Expense
        </button>
        <button type="button"
                @click="txType = 'income'"
                :class="txType === 'income' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                class="flex-1 px-4 py-2.5 text-sm font-semibold border rounded-xl transition-colors">
            Income
        </button>
        <input type="hidden" name="transaction_type" :value="txType">
    </div>

    {{-- Validation errors --}}
    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

        {{-- Title --}}
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $tx?->title) }}" required maxlength="200"
                   placeholder="e.g. Venue rent, Donation from XYZ..."
                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
        </div>

        {{-- Amount --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">$</span>
                <input type="number" name="amount" value="{{ old('amount', $tx?->amount) }}"
                       step="0.01" min="0.01" required
                       placeholder="0.00"
                       class="w-full border border-gray-300 rounded-xl pl-7 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
            </div>
        </div>

        {{-- Date --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
            <input type="date" name="transaction_date"
                   value="{{ old('transaction_date', $tx?->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                   required
                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
        </div>

        {{-- Category (filtered by type) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
            <select name="category_id" required
                    class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
                <option value="">Select category...</option>
                @foreach($categories->groupBy('type') as $type => $group)
                <optgroup label="{{ ucfirst($type) }}">
                    @foreach($group as $cat)
                    <option value="{{ $cat->id }}"
                            data-type="{{ $cat->type }}"
                            {{ old('category_id', $tx?->category_id) == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
        </div>

        {{-- Source / Payee --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                <span x-text="txType === 'income' ? 'Source / Donor' : 'Payee / Vendor'"></span>
                <span class="text-red-500">*</span>
            </label>
            <input type="text" name="source_or_payee"
                   value="{{ old('source_or_payee', $tx?->source_or_payee) }}"
                   required maxlength="200"
                   :placeholder="txType === 'income' ? 'e.g. Community Grant, John Doe' : 'e.g. City Hall, ABC Supplies'"
                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
        </div>

        {{-- Payment Method --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
            <select name="payment_method"
                    class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
                <option value="">Not specified</option>
                @foreach(\App\Models\FinanceTransaction::PAYMENT_METHODS as $method)
                <option value="{{ $method }}" {{ old('payment_method', $tx?->payment_method) === $method ? 'selected' : '' }}>{{ $method }}</option>
                @endforeach
            </select>
        </div>

        {{-- Reference Number --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reference / Invoice #</label>
            <input type="text" name="reference_number"
                   value="{{ old('reference_number', $tx?->reference_number) }}"
                   maxlength="100"
                   placeholder="Optional"
                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
        </div>

        {{-- Linked Event --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Link to Event</label>
            <select name="event_id"
                    class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
                <option value="">No event</option>
                @foreach($events as $event)
                <option value="{{ $event->id }}"
                        {{ old('event_id', $eventIdV) == $event->id ? 'selected' : '' }}>
                    {{ $event->name }} — {{ $event->date->format('M j, Y') }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status"
                    class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
                @foreach(\App\Models\FinanceTransaction::STATUSES as $status)
                <option value="{{ $status }}" {{ old('status', $tx?->status ?? 'completed') === $status ? 'selected' : '' }}>
                    {{ ucfirst($status) }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Notes --}}
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="3"
                      placeholder="Optional details..."
                      class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 resize-none">{{ old('notes', $tx?->notes) }}</textarea>
        </div>

        {{-- Attachment --}}
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Attachment
                <span class="text-gray-400 font-normal">(PDF, JPG, PNG — max 5 MB)</span>
            </label>

            {{-- Existing attachment on edit --}}
            @if($tx?->attachment_path)
            <div class="mb-3 flex items-center gap-3 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl">
                @php
                    $ext      = strtolower(pathinfo($tx->attachment_path, PATHINFO_EXTENSION));
                    $isPdf    = $ext === 'pdf';
                    $isImage  = in_array($ext, ['jpg', 'jpeg', 'png']);
                    $filename = basename($tx->attachment_path);
                @endphp
                {{-- Icon --}}
                @if($isPdf)
                <svg class="w-8 h-8 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                @else
                <svg class="w-8 h-8 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ $filename }}</p>
                    <p class="text-xs text-gray-400 uppercase">{{ strtoupper($ext) }}</p>
                </div>

                <a href="{{ route('finance.transactions.attachment.download', $tx) }}"
                   class="text-xs font-medium text-brand-600 hover:text-brand-700 whitespace-nowrap">
                    Download
                </a>
            </div>
            <p class="text-xs text-gray-400 mb-2">Upload a new file below to replace the current attachment.</p>
            @endif

            {{-- File input with drag-drop styling --}}
            <label class="flex flex-col items-center justify-center w-full px-4 py-6 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 hover:border-brand-400 transition-colors"
                   x-data="{ fileName: '' }"
                   @dragover.prevent
                   @drop.prevent="fileName = $event.dataTransfer.files[0]?.name ?? ''">
                <svg class="w-7 h-7 text-gray-400 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                </svg>
                <p class="text-sm text-gray-500" x-text="fileName || 'Click to upload or drag & drop'"></p>
                <p class="text-xs text-gray-400 mt-0.5">PDF, JPG, PNG — max 5 MB</p>
                <input type="file" name="attachment" class="hidden"
                       accept=".pdf,.jpg,.jpeg,.png"
                       @change="fileName = $event.target.files[0]?.name ?? ''">
            </label>
        </div>

    </div>
</div>
