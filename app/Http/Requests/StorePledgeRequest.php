<?php

namespace App\Http\Requests;

use App\Models\Pledge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same gate as finance transactions — pledges are part of the
        // finance module.
        return (bool) $this->user()?->hasPermission('finance.edit');
    }

    public function rules(): array
    {
        return [
            'household_id'    => ['nullable', 'integer', 'exists:households,id'],
            'source_or_payee' => ['required', 'string', 'max:200'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'pledged_at'      => ['required', 'date'],
            'expected_at'     => ['required', 'date', 'after_or_equal:pledged_at'],
            'received_at'     => ['nullable', 'date'],
            'status'          => ['required', Rule::in(Pledge::STATUSES)],
            'category_id'     => ['nullable', 'integer', 'exists:finance_categories,id'],
            'event_id'        => ['nullable', 'integer', 'exists:events,id'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
