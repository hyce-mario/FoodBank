<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) $user && (
            $user->isAdmin()
            || (method_exists($user, 'hasPermission') && $user->hasPermission('inventory.edit'))
        );
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
