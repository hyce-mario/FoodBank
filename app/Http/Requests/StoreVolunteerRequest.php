<?php

namespace App\Http\Requests;

use App\Models\Volunteer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Volunteer::class) ?? false;
    }


    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            // Phase 5.6.g: phone + email are unique when present. NULLs
            // coexist freely (matching the DB-level UNIQUE behavior).
            'phone'      => ['nullable', 'string', 'max:20', 'unique:volunteers,phone'],
            'email'      => ['nullable', 'email', 'max:255', 'unique:volunteers,email'],
            'role'       => ['nullable', 'string', Rule::in(array_keys(Volunteer::ROLES))],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'phone.unique'        => 'A volunteer with this phone number already exists.',
            'email.unique'        => 'A volunteer with this email already exists.',
            'role.in'             => 'Selected role is not valid.',
        ];
    }
}
