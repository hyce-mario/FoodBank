<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on finance.edit. Route middleware (finance.view on
        // the resource) lets the user reach this endpoint; the FormRequest
        // is the second tier of defense for writes.
        return (bool) $this->user()?->hasPermission('finance.edit');
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'type'        => ['required', 'in:income,expense'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ];
    }
}
