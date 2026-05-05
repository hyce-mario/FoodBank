<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on finance.edit (same as StoreFinanceCategoryRequest).
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
