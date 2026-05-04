@extends('layouts.app')
@section('title', 'Finance — Edit Transaction')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Finance</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.transactions.index') }}" class="hover:text-brand-500">Transactions</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('finance.transactions.show', $transaction) }}" class="hover:text-brand-500">{{ Str::limit($transaction->title, 30) }}</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Edit</span>
        </nav>
    </div>
    <a href="{{ route('finance.transactions.show', $transaction) }}"
       class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg px-4 py-2 bg-white hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back
    </a>
</div>

@include('finance._nav')

<div class="max-w-2xl">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-5">Edit Transaction</h2>

        <form method="POST" action="{{ route('finance.transactions.update', $transaction) }}" id="updateForm" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('finance.transactions._form')
            <div class="flex justify-between items-center mt-6 pt-5 border-t border-gray-100">
                <button type="button"
                        onclick="if(confirm('Delete this transaction? This cannot be undone.')) document.getElementById('deleteForm').submit();"
                        class="px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-xl hover:bg-red-50 transition-colors">
                    Delete
                </button>
                <div class="flex gap-3">
                    <a href="{{ route('finance.transactions.show', $transaction) }}"
                       class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-5 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-xl transition-colors">
                        Update
                    </button>
                </div>
            </div>
        </form>

        <form id="deleteForm" method="POST" action="{{ route('finance.transactions.destroy', $transaction) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>

@endsection
