@extends('layouts.app')
@section('title', 'Finance — ' . $transaction->title)

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.transactions.index') }}" class="hover:text-brand-500">Transactions</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">{{ Str::limit($transaction->title, 40) }}</span>
        </nav>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('finance.transactions.edit', $transaction) }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg px-4 py-2 bg-white hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
            Edit
        </a>
    </div>
</div>

@include('finance._nav')

@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

<div class="max-w-2xl">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        {{-- Amount Header --}}
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="text-2xl font-bold tabular-nums {{ $transaction->isIncome() ? 'text-green-600' : 'text-red-600' }}">
                    {{ $transaction->isIncome() ? '+' : '-' }}{{ $transaction->formattedAmount() }}
                </p>
                <p class="text-base font-semibold text-gray-900 mt-0.5">{{ $transaction->title }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $transaction->typeBadgeClasses() }}">
                    {{ ucfirst($transaction->transaction_type) }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $transaction->statusBadgeClasses() }}">
                    {{ ucfirst($transaction->status ?? 'completed') }}
                </span>
            </div>
        </div>

        {{-- Details Grid --}}
        <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Date</p>
                <p class="text-gray-800 font-medium">{{ $transaction->transaction_date->format('F j, Y') }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Category</p>
                <p class="text-gray-800">{{ $transaction->category?->name ?? '—' }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">
                    {{ $transaction->isIncome() ? 'Source / Donor' : 'Payee / Vendor' }}
                </p>
                <p class="text-gray-800">{{ $transaction->source_or_payee }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Payment Method</p>
                <p class="text-gray-800">{{ $transaction->payment_method ?? '—' }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Reference #</p>
                <p class="text-gray-800">{{ $transaction->reference_number ?? '—' }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Linked Event</p>
                @if($transaction->event)
                <a href="{{ route('events.show', $transaction->event) }}"
                   class="text-brand-600 hover:underline font-medium">
                    {{ $transaction->event->name }}
                </a>
                @else
                <p class="text-gray-400">—</p>
                @endif
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Recorded By</p>
                <p class="text-gray-800">{{ $transaction->creator?->name ?? '—' }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Recorded On</p>
                <p class="text-gray-800">{{ $transaction->created_at->format('M j, Y g:i A') }}</p>
            </div>

            @if($transaction->notes)
            <div class="sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Notes</p>
                <p class="text-gray-700 whitespace-pre-wrap">{{ $transaction->notes }}</p>
            </div>
            @endif

        </div>

        {{-- ── Attachment Section ─────────────────────────────────────────── --}}
        @if($transaction->attachment_path)
        @php
            $ext     = strtolower(pathinfo($transaction->attachment_path, PATHINFO_EXTENSION));
            $isPdf   = $ext === 'pdf';
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png']);
            $fname   = basename($transaction->attachment_path);
        @endphp
        <div class="px-6 pb-5 border-t border-gray-100 pt-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">Attachment</p>
            <div class="flex items-center gap-4 px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl">
                {{-- Icon --}}
                @if($isPdf)
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                    </svg>
                </div>
                @else
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                </div>
                @endif

                {{-- File info --}}
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $fname }}</p>
                    <p class="text-xs text-gray-400 uppercase mt-0.5">{{ strtoupper($ext) }} document</p>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('finance.transactions.attachment.download', $transaction) }}"
                       class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 border border-brand-200 rounded-lg px-3 py-1.5 hover:bg-brand-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Download
                    </a>
                    <form method="POST"
                          action="{{ route('finance.transactions.attachment.remove', $transaction) }}"
                          onsubmit="return confirm('Remove this attachment? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 hover:text-red-700 border border-red-200 rounded-lg px-3 py-1.5 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                            Remove
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @else
        <div class="px-6 pb-5 border-t border-gray-100 pt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Attachment</p>
            <a href="{{ route('finance.transactions.edit', $transaction) }}"
               class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
                </svg>
                No attachment — click to add one
            </a>
        </div>
        @endif

    </div>
</div>

@endsection
