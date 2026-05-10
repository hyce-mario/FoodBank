<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 6.5.e — validates the per-row decisions submitted from the preview
 * page. The actual permission check on each "update" target is done in
 * the controller (so we can resolve the existing Household and call
 * $this->authorize('update', $household)).
 */
class CommitHouseholdImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Household::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'token'                            => ['required', 'string', 'size:36'],
            'decisions'                        => ['nullable', 'array'],
            'decisions.*.action'               => ['required', Rule::in(['create', 'create_anyway', 'skip', 'update'])],
            'decisions.*.update_target_id'     => ['nullable', 'integer', 'exists:households,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'       => 'Import session token is missing — please re-upload.',
            'decisions.*.action.required' => 'A decision is required for every row.',
            'decisions.*.action.in'       => 'Decision must be one of: Create, Create anyway, Skip, Update.',
        ];
    }
}
