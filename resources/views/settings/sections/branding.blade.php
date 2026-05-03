{{-- Branding & Theme Section --}}

{{--
    The Logo & Favicon card contains its own POST/DELETE forms.
    We push it to @stack('settings_standalone_forms') in show.blade.php
    so it renders OUTSIDE the outer PUT <form> — HTML forbids nested forms.
--}}
@push('settings_standalone_forms')
@php
    $hasLogo    = ! empty($settings['logo_path']);
    $hasFavicon = ! empty($settings['favicon_path']);
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-800">Logo &amp; Favicon</h2>
            <p class="text-xs text-gray-500 mt-0.5">Upload your application logo and browser tab icon.</p>
        </div>
    </div>

    <div class="px-6 py-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">

            {{-- ── Application Logo ── --}}
            <div class="space-y-4" x-data="brandingZone()">
                <div>
                    <p class="text-sm font-medium text-gray-700">Application Logo</p>
                    <p class="text-xs text-gray-400 mt-0.5">Shown in the sidebar. PNG, JPG, or WebP &middot; max 2 MB &middot; max 1500&times;1500 px.</p>
                </div>

                {{-- Preview / drop zone. Live data-URL preview when a file is
                     selected; otherwise the persisted asset (or empty state). --}}
                <div class="w-full h-24 rounded-xl border-2 border-dashed bg-gray-50 flex items-center justify-center overflow-hidden transition-colors"
                     :class="dragOver ? 'border-brand-400 bg-brand-50' : 'border-gray-200'"
                     @dragover.prevent="dragOver = true"
                     @dragleave.prevent="dragOver = false"
                     @drop.prevent="onDrop($event)">
                    <template x-if="previewUrl">
                        <img :src="previewUrl" alt="Logo preview" class="max-h-16 max-w-full object-contain px-4">
                    </template>
                    <template x-if="!previewUrl">
                        <div>
                            @if ($hasLogo)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($settings['logo_path']) }}"
                                     alt="Logo"
                                     class="max-h-16 max-w-full object-contain px-4">
                            @else
                                <div class="flex flex-col items-center gap-1 text-gray-300 pointer-events-none">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                                    </svg>
                                    <span class="text-xs">Drop a file or click below</span>
                                </div>
                            @endif
                        </div>
                    </template>
                </div>

                {{-- Upload form --}}
                <form action="{{ route('settings.branding.upload', 'logo') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      class="space-y-3"
                      @submit="submitting = true">
                    @csrf
                    <label class="block w-full cursor-pointer">
                        <div class="flex items-center gap-2 w-full px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                            <span x-text="fileName || 'Choose file or drag here...'" class="truncate"></span>
                        </div>
                        <input type="file"
                               name="file"
                               x-ref="input"
                               accept=".png,.jpg,.jpeg,.webp"
                               class="sr-only"
                               @change="onPick($event)">
                    </label>
                    @error('file')
                        @if (request()->is('settings/branding/logo*') || str_contains($message, 'logo'))
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @endif
                    @enderror
                    <button type="submit"
                            :disabled="submitting || !fileName"
                            class="w-full px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-medium transition flex items-center justify-center gap-2">
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        <span x-text="submitting ? 'Uploading...' : '{{ $hasLogo ? 'Replace Logo' : 'Upload Logo' }}'"></span>
                    </button>
                </form>

                @if ($hasLogo)
                    <button type="button"
                            @click="$dispatch('open-branding-confirm', { asset: 'logo', label: 'logo' })"
                            class="w-full px-3 py-2 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium transition">
                        Remove Logo
                    </button>
                @endif
            </div>

            {{-- ── Favicon ── --}}
            <div class="space-y-4" x-data="brandingZone()">
                <div>
                    <p class="text-sm font-medium text-gray-700">Favicon</p>
                    <p class="text-xs text-gray-400 mt-0.5">Browser tab icon. ICO or PNG &middot; ideally 32&times;32 px, max 256&times;256 &middot; max 512 KB.</p>
                </div>

                {{-- Preview --}}
                <div class="w-full h-24 rounded-xl border-2 border-dashed bg-gray-50 flex items-center justify-center overflow-hidden transition-colors"
                     :class="dragOver ? 'border-brand-400 bg-brand-50' : 'border-gray-200'"
                     @dragover.prevent="dragOver = true"
                     @dragleave.prevent="dragOver = false"
                     @drop.prevent="onDrop($event)">
                    <template x-if="previewUrl">
                        <div class="flex flex-col items-center gap-2">
                            <img :src="previewUrl" alt="Favicon preview" class="w-8 h-8 object-contain">
                            <span class="text-xs text-gray-400">preview</span>
                        </div>
                    </template>
                    <template x-if="!previewUrl">
                        <div>
                            @if ($hasFavicon)
                                <div class="flex flex-col items-center gap-2">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($settings['favicon_path']) }}"
                                         alt="Favicon"
                                         class="w-8 h-8 object-contain">
                                    <span class="text-xs text-gray-400">32&times;32 preview</span>
                                </div>
                            @else
                                <div class="flex flex-col items-center gap-1 text-gray-300 pointer-events-none">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/>
                                    </svg>
                                    <span class="text-xs">Drop a file or click below</span>
                                </div>
                            @endif
                        </div>
                    </template>
                </div>

                {{-- Upload form --}}
                <form action="{{ route('settings.branding.upload', 'favicon') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      class="space-y-3"
                      @submit="submitting = true">
                    @csrf
                    <label class="block w-full cursor-pointer">
                        <div class="flex items-center gap-2 w-full px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition text-sm text-gray-600">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                            <span x-text="fileName || 'Choose file or drag here...'" class="truncate"></span>
                        </div>
                        <input type="file"
                               name="file"
                               x-ref="input"
                               accept=".ico,.png"
                               class="sr-only"
                               @change="onPick($event)">
                    </label>
                    @error('file')
                        @if (request()->is('settings/branding/favicon*'))
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @endif
                    @enderror
                    <button type="submit"
                            :disabled="submitting || !fileName"
                            class="w-full px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-medium transition flex items-center justify-center gap-2">
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        <span x-text="submitting ? 'Uploading...' : '{{ $hasFavicon ? 'Replace Favicon' : 'Upload Favicon' }}'"></span>
                    </button>
                </form>

                @if ($hasFavicon)
                    <button type="button"
                            @click="$dispatch('open-branding-confirm', { asset: 'favicon', label: 'favicon' })"
                            class="w-full px-3 py-2 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium transition">
                        Remove Favicon
                    </button>
                @endif
            </div>

        </div>

        <p class="mt-5 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5">
            <strong>Storage note:</strong> Files are saved to <code>storage/app/public/branding/</code>.
            Run <code>php artisan storage:link</code> once if images do not appear after uploading.
        </p>
    </div>
</div>

{{-- ── Confirm-removal modal ─────────────────────────────────────────
     Replaces the native confirm() prompt with the project's standard
     Alpine modal pattern. Submits a hidden DELETE form on confirm. --}}
<div x-data="{
        open: false,
        asset: '',
        label: '',
        submitting: false,
    }"
     @open-branding-confirm.window="open = true; asset = $event.detail.asset; label = $event.detail.label;"
     @keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open"
         x-transition.opacity
         class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"
         @click="open = false"></div>
    <div x-show="open"
         x-transition
         class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden"
         role="dialog" aria-modal="true">
        <div class="px-6 py-5">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12.56 1.001c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-gray-900">Remove <span x-text="label"></span>?</h3>
                    <p class="text-sm text-gray-500 mt-1">The file will be deleted from storage. You can re-upload at any time.</p>
                </div>
            </div>
        </div>
        <form method="POST"
              :action="`{{ url('settings/branding') }}/${asset}`"
              @submit="submitting = true"
              class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50/40">
            @csrf
            @method('DELETE')
            <button type="button" @click="open = false"
                    :disabled="submitting"
                    class="px-4 py-2 text-sm text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors font-medium">
                Cancel
            </button>
            <button type="submit"
                    :disabled="submitting"
                    class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:bg-red-300 text-white font-semibold rounded-lg transition-colors">
                <span x-text="submitting ? 'Removing...' : 'Remove'"></span>
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Per-zone Alpine state for the drag/drop + live-preview lifecycle.
    // One instance is created for each branding zone (logo, favicon).
    function brandingZone() {
        return {
            fileName:    '',
            previewUrl:  '',
            dragOver:    false,
            submitting:  false,
            onPick(ev) {
                this.handleFile(ev.target.files?.[0]);
            },
            onDrop(ev) {
                this.dragOver = false;
                const file = ev.dataTransfer?.files?.[0];
                if (!file) return;
                // Inject the dropped file into the hidden <input type=file>
                // using a DataTransfer so the form posts it normally.
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.input.files = dt.files;
                this.handleFile(file);
            },
            handleFile(file) {
                if (!file) return;
                this.fileName = file.name;
                // ICO doesn't preview reliably in <img>; skip to a
                // filename-only state so the user still sees confirmation.
                if (/\.ico$/i.test(file.name)) {
                    this.previewUrl = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => { this.previewUrl = e.target.result; };
                reader.readAsDataURL(file);
            },
        };
    }
</script>
@endpush
@endpush

{{-- Brand Colors — inside the outer PUT form --}}
<div class="space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Brand Colors</h2>
                <p class="text-xs text-gray-500 mt-0.5">Controls the visual identity across the admin interface.</p>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @include('settings._field', ['key' => 'primary_color',   'def' => $definitions['primary_color'],   'settings' => $settings])
                @include('settings._field', ['key' => 'secondary_color', 'def' => $definitions['secondary_color'], 'settings' => $settings])
                @include('settings._field', ['key' => 'accent_color',    'def' => $definitions['accent_color'],    'settings' => $settings])
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'sidebar_bg',     'def' => $definitions['sidebar_bg'],     'settings' => $settings])
                @include('settings._field', ['key' => 'nav_text_color', 'def' => $definitions['nav_text_color'], 'settings' => $settings])
            </div>

            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                <strong>Note:</strong> Color changes currently store your preferences. Full dynamic theming requires a rebuild of Tailwind CSS classes. These values are available via <code>SettingService::get('branding.primary_color')</code> for use in custom CSS or inline styles.
            </div>

        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Display Options</h3>
        </div>
        <div class="px-6 py-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @include('settings._field', ['key' => 'logo_display', 'def' => $definitions['logo_display'], 'settings' => $settings])
                @include('settings._field', ['key' => 'appearance',   'def' => $definitions['appearance'],   'settings' => $settings])
            </div>

        </div>
    </div>

</div>
