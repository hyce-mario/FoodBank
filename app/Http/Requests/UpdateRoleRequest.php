<?php

namespace App\Http\Requests;

use App\Services\RolePermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Permissions must come from the official catalog (or be the
        // wildcard). Mirrors StoreRoleRequest. See that file for rationale.
        $allowed = array_merge(RolePermissionService::allPermissions(), ['*']);

        return [
            'display_name' => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'permissions'  => ['nullable', 'array'],
            'permissions.*'=> ['string', Rule::in($allowed)],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.*.in' => 'One or more selected permissions are not recognised. Refresh the page and try again.',
        ];
    }
}
