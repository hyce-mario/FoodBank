<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('volunteer_group');
        return $group
            ? ($this->user()?->can('update', $group) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'name'        => [
                'required', 'string', 'max:100',
                Rule::unique('volunteer_groups', 'name')->ignore($this->route('volunteer_group')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'name.unique'   => 'A group with this name already exists.',
        ];
    }
}
