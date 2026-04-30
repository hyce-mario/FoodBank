<?php

namespace App\Http\Requests;

use App\Models\Volunteer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $volunteer = $this->route('volunteer');
        return $volunteer
            ? ($this->user()?->can('update', $volunteer) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'email'      => ['nullable', 'email', 'max:255'],
            'role'       => ['nullable', 'string', Rule::in(array_keys(Volunteer::ROLES))],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'role.in'             => 'Selected role is not valid.',
        ];
    }
}
