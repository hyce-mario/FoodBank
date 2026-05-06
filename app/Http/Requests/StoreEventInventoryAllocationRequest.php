<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventInventoryAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on inventory.edit. Allocations decrement stock, so
        // the stock-side permission wins over events.edit (the event-side
        // grants viewing/editing the allocation table on the event page,
        // but a user without inventory.edit can't actually pull stock).
        return (bool) $this->user()?->hasPermission('inventory.edit');
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'allocated_quantity' => ['required', 'integer', 'min:1'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.required' => 'Please select an inventory item.',
            'allocated_quantity.min'     => 'Allocation quantity must be at least 1.',
        ];
    }
}
