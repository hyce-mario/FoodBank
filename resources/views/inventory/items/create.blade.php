@extends('layouts.app')
@section('title', 'Add Inventory Item')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Add Inventory Item</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('inventory.items.index') }}" class="hover:text-brand-500">Inventory</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Add Item</span>
        </nav>
    </div>
</div>

<div class="max-w-2xl">
    @include('inventory.items._form', [
        'item'        => null,
        'categories'  => $categories,
        'action'      => route('inventory.items.store'),
        'method'      => 'POST',
        'submitLabel' => 'Add Item',
    ])
</div>

@endsection
