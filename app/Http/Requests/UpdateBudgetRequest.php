<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('finance.edit');
    }

    public function rules(): array
    {
        return [
            'category_id'  => ['required', 'integer', 'exists:finance_categories,id'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'event_id'     => ['nullable', 'integer', 'exists:events,id'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
