<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on roles.create. CRITICAL: prior to this fix any
        // authenticated user could POST /roles with permissions=['*'], creating
        // an admin-equivalent role and self-promoting via UserController::update
        // (the role-assignment defense-in-depth check there is the second line
        // of defense — this is the first).
        return (bool) $this->user()?->hasPermission('roles.create');
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
