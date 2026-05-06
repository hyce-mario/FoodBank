@extends('layouts.app')
@section('title', 'Add Pledge')

@section('content')
<div class="mb-5">
    <h1 class="text-xl font-bold text-gray-900">Add Pledge</h1>
    <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
        <a href="{{ route('finance.dashboard') }}" class="hover:text-brand-500">Finance</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        <a href="{{ route('finance.pledges.index') }}" class="hover:text-brand-500">Pledges</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        <span class="text-gray-600 font-medium">Add</span>
    </nav>
</div>

<form method="POST" action="{{ route('finance.pledges.store') }}"
      class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 max-w-3xl">
    @include('finance.pledges._form', ['submitLabel' => 'Save Pledge'])
</form>
@endsection
