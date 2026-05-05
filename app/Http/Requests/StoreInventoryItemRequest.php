<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on inventory.edit. inventory keys are split view/edit
        // (no separate create/delete) since the existing catalog pre-Tier-1
        // already used that scheme; Tier 1 didn't expand it.
        return (bool) $this->user()?->hasPermission('inventory.edit');
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:150'],
            'sku'                 => ['nullable', 'string', 'max:100', 'unique:inventory_items,sku'],
            'category_id'         => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'unit_type'           => ['required', 'string', 'max:50'],
            'quantity_on_hand'    => ['required', 'integer', 'min:0'],
            'reorder_level'       => ['required', 'integer', 'min:0'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'manufacturing_date'  => ['nullable', 'date'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:manufacturing_date'],
            'is_active'           => ['boolean'],
        ];
    }
}
