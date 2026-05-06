<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllocationDistributedRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on inventory.edit. distributed_quantity drives the
        // posted-vs-allocated reconciliation; same scope as inventory writes.
        return (bool) $this->user()?->hasPermission('inventory.edit');
    }

    public function rules(): array
    {
        return [
            'distributed_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
