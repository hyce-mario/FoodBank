{{--
    Reusable settings field partial.
    Props: $key (short key), $def (definition array), $settings (group values array)
--}}
@php
    $value       = $settings[$key] ?? $def['default'] ?? null;
    $type        = $def['type'] ?? 'string';
    $label       = $def['label'] ?? $key;
    $description = $def['description'] ?? '';
    $options     = $def['options'] ?? [];
    $inputClass  = 'w-full px-4 py-2.5 text-sm border rounded-xl bg-white transition-colors
                    focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                    placeholder:text-gray-400 '
                 . ($errors->has($key) ? 'border-red-400 bg-red-50' : 'border-gray-300');
@endphp

<div class="space-y-1.5">
    @if($type !== 'boolean')
        <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif

    @if($type === 'boolean')
        {{--
            Toggle switch.
            No wrapping <label> — avoids the double-fire bug where clicking the visual
            div fires @click (toggle #1) then bubbles to <label> which activates the
            sr-only checkbox (toggle #2), causing x-model to reset on back to its
            original value so nothing appears to change.
            Instead: x-ref="cb" + explicit $refs.cb.checked keeps the hidden checkbox
            in sync for form submission without any event double-fire.
        --}}
        <div class="flex items-start gap-3"
             x-data="{ on: {{ old($key, $value) ? 'true' : 'false' }},
                        toggle() { this.on = !this.on; this.$refs.cb.checked = this.on; } }">
            <input type="hidden" name="{{ $key }}" value="0">
            <input type="checkbox" name="{{ $key }}" value="1"
                   id="field_{{ $key }}"
                   x-ref="cb"
                   {{ old($key, $value) ? 'checked' : '' }}
                   class="sr-only">
            <button type="button"
                    @click="toggle()"
                    :aria-checked="on.toString()"
                    role="switch"
                    class="relative mt-0.5 flex-shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-full">
                <div class="w-10 h-6 rounded-full border-2 transition-colors duration-200"
                     :class="on ? 'bg-brand-500 border-brand-500' : 'bg-gray-200 border-gray-300'">
                    <div class="w-4 h-4 bg-white rounded-full shadow-sm m-0.5 transition-transform duration-200"
                         :class="on ? 'translate-x-4' : 'translate-x-0'"></div>
                </div>
            </button>
            <label for="field_{{ $key }}" class="cursor-pointer" @click.prevent="toggle()">
                <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                @if($description)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $description }}</p>
                @endif
            </label>
        </div>

    @elseif($type === 'select')
        {{-- Select ────────────────────────────────────────────────────── --}}
        <select name="{{ $key }}" id="field_{{ $key }}" class="{{ $inputClass }}">
            @foreach($options as $optVal => $optLabel)
                <option value="{{ $optVal }}" {{ old($key, $value) == $optVal ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
            @endforeach
        </select>

    @elseif($type === 'multi_select')
        {{-- Chip-based dropdown multi-select. Submits as `name[]=val1&name[]=…`
             via reactive hidden inputs. SettingsController.update() filters
             empty strings before validating, and SettingService::updateGroup()
             treats a missing key as an empty array, so we don't need any
             "field is present" sentinel input here. --}}
        @php
            $current = old($key, $value);
            if (! is_array($current)) {
                $current = $current === null || $current === '' ? [] : (array) $current;
            }
            // Drop empty strings (could leak in via old() after a prior
            // failed save) so the chip list and the wire payload only carry
            // real selections. Also string-coerce so JS strict equality
            // works against the option values regardless of source type.
            $current = array_values(array_filter(
                array_map('strval', $current),
                fn ($v) => $v !== ''
            ));
        @endphp
        <div x-data="{
                 selected: @js($current),
                 options: @js($options),
                 open: false,
                 toggle(val) {
                     const i = this.selected.indexOf(val);
                     if (i === -1) this.selected.push(val);
                     else this.selected.splice(i, 1);
                 },
                 remove(val) {
                     const i = this.selected.indexOf(val);
                     if (i !== -1) this.selected.splice(i, 1);
                 },
                 optionLabel(val) {
                     return this.options[val] ?? val;
                 },
                 isSelected(val) {
                     return this.selected.includes(val);
                 }
             }"
             @click.away="open = false"
             @keydown.escape.window="open = false"
             class="relative">

            {{-- Trigger / chip area --}}
            <div @click="open = !open"
                 class="border rounded-xl px-2.5 py-2 bg-white cursor-pointer min-h-[44px] flex flex-wrap items-center gap-1.5 transition-colors
                        {{ $errors->has($key) ? 'border-red-400 bg-red-50' : 'border-gray-300 hover:border-gray-400' }}"
                 :class="open ? 'ring-2 ring-brand-500/20 border-brand-400' : ''">
                <template x-if="selected.length === 0">
                    <span class="text-sm text-gray-400 px-1">Select formats…</span>
                </template>
                <template x-for="val in selected" :key="val">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-brand-50 border border-brand-200 text-brand-700 text-xs font-medium">
                        <span x-text="optionLabel(val)"></span>
                        <button type="button"
                                @click.stop="remove(val)"
                                class="hover:text-brand-900 leading-none -mr-0.5"
                                aria-label="Remove">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                </template>
                <svg class="w-4 h-4 text-gray-400 ml-auto transition-transform"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </div>

            {{-- Hidden inputs — reactive. No empty sentinel; controller-side
                 SettingsController.update() filters empty strings before
                 validation, and updateGroup treats a missing key as []. --}}
            <template x-for="val in selected" :key="'h-' + val">
                <input type="hidden" name="{{ $key }}[]" :value="val">
            </template>

            {{-- Dropdown panel --}}
            <div x-show="open" x-cloak style="display:none"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute z-30 mt-1 left-0 right-0 max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg">
                @foreach($options as $optVal => $optLabel)
                    <button type="button"
                            @click="toggle('{{ $optVal }}')"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-brand-50 flex items-center gap-2 border-b border-gray-50 last:border-b-0">
                        <span class="w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                              :class="isSelected('{{ $optVal }}') ? 'bg-brand-500 border-brand-500' : 'border-gray-300 bg-white'">
                            <svg x-show="isSelected('{{ $optVal }}')" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </span>
                        <span class="text-gray-700">{{ $optLabel }}</span>
                    </button>
                @endforeach
            </div>
        </div>

    @elseif($type === 'color')
        {{-- Color picker ──────────────────────────────────────────────── --}}
        <div class="flex items-center gap-3">
            <input type="color" name="{{ $key }}" id="field_{{ $key }}"
                   value="{{ old($key, $value) ?? '#000000' }}"
                   class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5 bg-white">
            <input type="text" name="{{ $key }}_preview" readonly
                   value="{{ old($key, $value) ?? '' }}"
                   class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-xl bg-gray-50 text-gray-600 font-mono"
                   x-data
                   x-on:input.debounce.200ms="$el.previousElementSibling.value = $el.value">
            <script>
                document.getElementById('field_{{ $key }}').addEventListener('input', function() {
                    this.nextElementSibling.value = this.value;
                });
            </script>
        </div>

    @elseif($type === 'text')
        {{-- Textarea ──────────────────────────────────────────────────── --}}
        <textarea name="{{ $key }}" id="field_{{ $key }}"
                  rows="4"
                  class="{{ $inputClass }}">{{ old($key, $value) }}</textarea>

    @else
        {{-- Standard text/number input ────────────────────────────────── --}}
        <input type="{{ in_array($type, ['integer', 'float']) ? 'number' : 'text' }}"
               name="{{ $key }}"
               id="field_{{ $key }}"
               value="{{ old($key, $value) }}"
               {{ $type === 'float' ? 'step="0.1"' : '' }}
               class="{{ $inputClass }}"
               placeholder="{{ $def['placeholder'] ?? '' }}">
    @endif

    @if($description && $type !== 'boolean')
        <p class="text-xs text-gray-500">{{ $description }}</p>
    @endif

    @error($key)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
