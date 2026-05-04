<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('volunteer_group');
        return $group
            ? ($this->user()?->can('manageMembers', $group) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            // volunteer_ids may be absent (empty group = remove all)
            'volunteer_ids'   => ['nullable', 'array'],
            'volunteer_ids.*' => ['integer', 'exists:volunteers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'volunteer_ids.*.exists' => 'One or more selected volunteers do not exist.',
        ];
    }
}
