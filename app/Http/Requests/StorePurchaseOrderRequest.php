<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 3c — gates on the dedicated purchase_orders.create permission.
        // ADMIN keeps access via Gate::before's '*' wildcard match. Tier 1
        // catalog cleanup added the purchase_orders.* group; the prior
        // "isAdmin OR inventory.edit" fallback is no longer needed.
        return (bool) $this->user()?->hasPermission('purchase_orders.create');
    }

    public function rules(): array
    {
        return [
            'supplier_name'             => ['required', 'string', 'max:200'],
            'order_date'                => ['required', 'date'],
            'notes'                     => ['nullable', 'string', 'max:1000'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'items.*.unit_cost'         => ['required', 'numeric', 'min:0'],
        ];
    }
}
