<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on roles.edit. Prior to this fix any authenticated user
        // could PUT /roles/{id} to grant any permission (including '*') to any
        // role, including their own.
        return (bool) $this->user()?->hasPermission('roles.edit');
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['nullable', 'array'],
            'permissions.*'=> ['string', 'max:100'],
        ];
    }
}
