<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnInventoryAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on inventory.edit. Returns mutate stock (back into
        // the shelf) — same gate as the allocation that pulled it.
        return (bool) $this->user()?->hasPermission('inventory.edit');
    }

    public function rules(): array
    {
        return [
            'return_quantity' => ['required', 'integer', 'min:1'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'return_quantity.min' => 'Return quantity must be at least 1.',
        ];
    }
}
