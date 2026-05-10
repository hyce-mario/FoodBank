@extends('layouts.app')
@section('title', 'Import Households')

@section('content')
<div x-data="{ filename: '' }">

{{-- Header --}}
<div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Import Households</h1>
        <nav class="flex items-center gap-1.5 text-sm text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 transition-colors">Dashboard</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('households.index') }}" class="hover:text-brand-500 transition-colors">Households</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-700 font-medium">Import</span>
        </nav>
    </div>
    <a href="{{ route('households.index') }}"
       class="text-sm text-gray-600 hover:text-brand-600 self-start">← Back to Households</a>
</div>

{{-- Flash error --}}
@if (session('error'))
<div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    {{ session('error') }}
</div>
@endif

{{-- Validation errors from the parser --}}
@if (session('import_errors'))
@php $importErrors = session('import_errors'); @endphp
<div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4">
    <div class="flex items-start gap-2 mb-3">
        <svg class="w-4 h-4 text-red-700 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
        <div>
            <p class="text-sm font-semibold text-red-800">
                Upload rejected — {{ count($importErrors) }} error{{ count($importErrors) === 1 ? '' : 's' }}
                @if (session('import_filename')) in <strong>{{ session('import_filename') }}</strong>@endif.
            </p>
            <p class="text-xs text-red-700 mt-1">Fix the source file and re-upload. No records were created.</p>
        </div>
    </div>
    <div class="overflow-x-auto -mx-4">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-y border-red-200 bg-red-100/50">
                    <th class="text-left px-4 py-1.5 font-semibold text-red-800">Row</th>
                    <th class="text-left px-4 py-1.5 font-semibold text-red-800">Column</th>
                    <th class="text-left px-4 py-1.5 font-semibold text-red-800">Issue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-red-100">
                @foreach ($importErrors as $err)
                <tr>
                    <td class="px-4 py-1.5 text-red-700 font-medium tabular-nums">{{ $err['row'] }}</td>
                    <td class="px-4 py-1.5 text-red-700 font-mono">{{ $err['column'] ?? '—' }}</td>
                    <td class="px-4 py-1.5 text-red-700">{{ $err['message'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Validation errors from the file-upload FormRequest --}}
@if ($errors->any())
<div class="mb-4 flex items-start gap-2 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    <ul class="space-y-1">
        @foreach ($errors->all() as $message)
        <li>{{ $message }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    {{-- Upload card --}}
    <div class="md:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">Upload File</h2>
        </div>
        <form method="POST" action="{{ route('households.import.store') }}" enctype="multipart/form-data" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                    CSV or XLSX file <span class="text-red-500">*</span>
                </label>
                <input type="file" name="file" accept=".csv,.xlsx,.txt" required
                       @change="filename = $event.target.files[0]?.name || ''"
                       class="block w-full text-sm text-gray-700
                              file:mr-3 file:py-2 file:px-4 file:rounded-lg
                              file:border-0 file:text-sm file:font-semibold
                              file:bg-brand-500 file:text-white hover:file:bg-brand-600
                              file:cursor-pointer cursor-pointer
                              border border-gray-300 rounded-xl px-3 py-2 bg-white">
                <p class="text-xs text-gray-500 mt-1.5">
                    Max {{ number_format($maxBytes / 1024 / 1024) }} MB · max {{ number_format($maxRows) }} rows · CSV or XLSX
                </p>
                <p class="text-xs text-gray-700 mt-0.5" x-show="filename" x-cloak>
                    Selected: <span class="font-semibold" x-text="filename"></span>
                </p>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-2.5 text-xs text-amber-800 leading-relaxed">
                <strong>Heads up:</strong> uploading does not commit anything. The next page lets you review every row
                and pick what happens to existing-household matches before any records are created.
            </div>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('households.index') }}"
                   class="px-4 py-2 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
                    Upload &amp; Preview
                </button>
            </div>
        </form>
    </div>

    {{-- Template download + format guidance --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-gray-800">Template</h2>
        </div>
        <div class="p-5 space-y-3">
            <p class="text-xs text-gray-600 leading-relaxed">
                Start from a known-good shape. The template includes a sample row and column reference.
            </p>
            <div class="space-y-2">
                <a href="{{ route('households.import.template', 'xlsx') }}"
                   class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold bg-green-600 hover:bg-green-700 text-white rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Download .xlsx
                </a>
                <a href="{{ route('households.import.template', 'csv') }}"
                   class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Download .csv
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Required columns reference --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-6">
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
        <h2 class="text-sm font-semibold text-gray-800">Required Columns</h2>
    </div>
    <div class="p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Always required</p>
        <div class="flex flex-wrap gap-1.5 mb-4">
            @foreach ($requiredColumns['always'] as $col)
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono bg-red-50 border border-red-200 text-red-700">{{ $col }}</span>
            @endforeach
        </div>

        @if (! empty($requiredColumns['conditional']))
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Required by your settings</p>
            <ul class="space-y-1 mb-4">
                @foreach ($requiredColumns['conditional'] as $cc)
                    <li class="text-xs text-gray-700">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md font-mono bg-amber-50 border border-amber-200 text-amber-800">{{ $cc['column'] }}</span>
                        — {{ $cc['reason'] }}
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Optional</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach (\App\Services\HouseholdImportService::COLUMNS as $col)
                @php
                    $isAlways      = in_array($col, $requiredColumns['always'], true);
                    $isConditional = collect($requiredColumns['conditional'])->contains('column', $col);
                @endphp
                @if (! $isAlways && ! $isConditional)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono bg-gray-50 border border-gray-200 text-gray-600">{{ $col }}</span>
                @endif
            @endforeach
        </div>

        <p class="text-xs text-gray-500 mt-4 leading-relaxed">
            <strong class="text-gray-700">Auto-managed:</strong> household_number, qr_token, and household_size
            (= max(1, children + adults + seniors)) are filled in automatically.
            <strong class="text-gray-700">Representative chains</strong> are not imported in v1 — attach them manually
            on the Show page after the import.
        </p>
    </div>
</div>

</div>
@endsection
