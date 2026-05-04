<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', Rule::unique('roles', 'name')],
            'display_name' => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['nullable', 'array'],
            'permissions.*'=> ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Role name must be uppercase letters, numbers, and underscores only (e.g. MY_ROLE).',
            'name.unique' => 'A role with this name already exists.',
        ];
    }
}
