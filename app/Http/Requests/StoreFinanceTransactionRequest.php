<?php

namespace App\Http\Requests;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on finance.create. Route middleware (finance.view on
        // the resource) lets the user reach this endpoint; the FormRequest
        // is the second tier of defense for writes.
        return (bool) $this->user()?->hasPermission('finance.create');
    }

    public function rules(): array
    {
        $requireCategory    = (bool) SettingService::get('finance.require_category',        false);
        $allowAttachments   = (bool) SettingService::get('finance.allow_attachments',        true);
        $allowedTypes       =        SettingService::get('finance.allowed_attachment_types', 'pdf,jpg,jpeg,png');
        $allowDraftExpenses = (bool) SettingService::get('finance.allow_draft_expenses',     false);

        // Draft status is only available for expenses when the setting is on
        $isExpense   = $this->input('transaction_type') === 'expense';
        $validStatuses = $allowDraftExpenses && $isExpense
            ? 'pending,completed,cancelled,draft'
            : 'pending,completed,cancelled';

        return [
            'transaction_type' => ['required', 'in:income,expense'],
            'title'            => ['required', 'string', 'max:200'],
            'category_id'      => [$requireCategory ? 'required' : 'nullable', 'exists:finance_categories,id'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'source_or_payee'  => ['required', 'string', 'max:200'],
            'payment_method'   => ['nullable', 'string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'event_id'         => ['nullable', 'exists:events,id'],
            'notes'            => ['nullable', 'string'],
            'status'           => ['nullable', 'in:' . $validStatuses],
            'attachment'       => $allowAttachments
                ? ['nullable', 'file', 'mimes:' . $allowedTypes, 'max:5120']
                : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'  => 'A category is required for every transaction.',
            'attachment.prohibited' => 'Attachment uploads are currently disabled.',
        ];
    }
}
