@extends('layouts.app')
@section('title', 'Inventory Categories')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Inventory Categories</h1>
        <nav class="flex items-center gap-1 text-xs text-gray-400 mt-0.5">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500">Dashboard</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <a href="{{ route('inventory.items.index') }}" class="hover:text-brand-500">Inventory</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-gray-600 font-medium">Categories</span>
        </nav>
    </div>
    {{-- Back to Inventory --}}
    <a href="{{ route('inventory.items.index') }}"
       class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900
              bg-white border border-gray-200 rounded-lg px-4 py-2 hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back to Inventory
    </a>
</div>

{{-- Flash --}}
@if (session('success'))
<div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

@if (session('error'))
<div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
    {{ session('error') }}
</div>
@endif

{{-- ── Main Card with Alpine state ─────────────────────────────────────── --}}
<div x-data="{
    modal: null,
    editId: null,
    editName: '',
    editDescription: '',
    deleteId: null,
    deleteName: '',

    openAdd() { this.modal = 'add'; },

    openEdit(id, name, description) {
        this.editId = id;
        this.editName = name;
        this.editDescription = description;
        this.modal = 'edit';
    },

    openDelete(id, name) {
        this.deleteId = id;
        this.deleteName = name;
        this.modal = 'delete';
    },

    close() { this.modal = null; }
}">

    {{-- Card Header --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">All Categories</h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ $categories->count() }} {{ Str::plural('category', $categories->count()) }}</p>
            </div>
            <button @click="openAdd()"
                    class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white
                           font-semibold text-sm rounded-lg px-4 py-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Add Category
            </button>
        </div>

        {{-- Category List --}}
        @if ($categories->isEmpty())
        <div class="px-5 py-16 text-center">
            <div class="flex flex-col items-center gap-2 text-gray-400">
                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                </svg>
                <p class="text-sm font-medium text-gray-500">No categories yet</p>
                <button @click="openAdd()" class="text-xs text-brand-500 hover:underline">Add your first category</button>
            </div>
        </div>
        @else
        <ul class="divide-y divide-gray-100">
            @foreach ($categories as $category)
            <li class="flex items-center justify-between px-5 py-4 hover:bg-gray-50/60 transition-colors group">
                <div class="flex items-start gap-3 min-w-0">
                    {{-- Tag icon --}}
                    <div class="w-9 h-9 rounded-xl bg-brand-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ $category->name }}</p>
                        @if ($category->description)
                            <p class="text-xs text-gray-500 mt-0.5 truncate max-w-md">{{ $category->description }}</p>
                        @else
                            <p class="text-xs text-gray-400 mt-0.5 italic">No description</p>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                    {{-- Item count badge --}}
                    <a href="{{ route('inventory.items.index', ['category' => $category->id]) }}"
                       class="text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600
                              hover:bg-brand-50 hover:text-brand-600 transition-colors">
                        {{ $category->items_count }} {{ Str::plural('item', $category->items_count) }}
                    </a>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button @click="openEdit({{ $category->id }}, '{{ addslashes($category->name) }}', '{{ addslashes($category->description ?? '') }}')"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400
                                       hover:text-brand-600 hover:bg-brand-50 transition-colors"
                                title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                            </svg>
                        </button>
                        <button @click="openDelete({{ $category->id }}, '{{ addslashes($category->name) }}')"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400
                                       hover:text-red-600 hover:bg-red-50 transition-colors"
                                title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════
         ADD MODAL
    ══════════════════════════════════════════════════════════ --}}
    <div x-show="modal === 'add'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="close()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         style="display:none;">
        <div @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-bold text-gray-900">Add Category</h3>
                <button @click="close()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('inventory.categories.store') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required maxlength="100" autofocus
                               placeholder="e.g. Dairy & Eggs"
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                      placeholder:text-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="2" maxlength="500"
                                  placeholder="Optional — describe what belongs in this category"
                                  class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                                         focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                         placeholder:text-gray-400 resize-none"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5 pt-4 border-t border-gray-100">
                    <button type="button" @click="close()"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold text-white bg-brand-500 hover:bg-brand-600 rounded-lg transition-colors">
                        Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         EDIT MODAL
    ══════════════════════════════════════════════════════════ --}}
    <div x-show="modal === 'edit'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="close()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         style="display:none;">
        <div @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-bold text-gray-900">Edit Category</h3>
                <button @click="close()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST"
                  :action="`{{ url('inventory/categories') }}/${editId}`">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required maxlength="100"
                               x-model="editName"
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="2" maxlength="500"
                                  x-model="editDescription"
                                  placeholder="Optional"
                                  class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50
                                         focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400
                                         placeholder:text-gray-400 resize-none"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5 pt-4 border-t border-gray-100">
                    <button type="button" @click="close()"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold text-white bg-brand-500 hover:bg-brand-600 rounded-lg transition-colors">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         DELETE MODAL
    ══════════════════════════════════════════════════════════ --}}
    <div x-show="modal === 'delete'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="close()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         style="display:none;">
        <div @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">

            <div class="flex items-start gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Delete Category</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Delete <span class="font-semibold" x-text="`"${deleteName}"`"></span>?
                        This cannot be undone. Categories with items cannot be deleted.
                    </p>
                </div>
            </div>

            <form method="POST" :action="`{{ url('inventory/categories') }}/${deleteId}`">
                @csrf
                @method('DELETE')
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>{{-- /x-data --}}

@endsection
