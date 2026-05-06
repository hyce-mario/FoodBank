<?php

namespace App\Http\Requests;

use App\Models\FinanceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name'                    => ['required', 'string', 'max:100'],
            'type'                    => ['required', 'in:income,expense'],
            'description'             => ['nullable', 'string', 'max:500'],
            'is_active'               => ['boolean'],
            // Phase 7.4.a — NFP functional classification.
            'function_classification' => ['nullable', Rule::in(FinanceCategory::FUNCTIONS)],
        ];
    }
}
