<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on inventory.edit (same as StoreInventoryItemRequest).
        return (bool) $this->user()?->hasPermission('inventory.edit');
    }

    public function rules(): array
    {
        $id = $this->route('inventory_item')?->id;

        return [
            'name'                => ['required', 'string', 'max:150'],
            'sku'                 => ['nullable', 'string', 'max:100', "unique:inventory_items,sku,{$id}"],
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
