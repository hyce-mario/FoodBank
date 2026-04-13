<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'size:2'],
            'zip'            => ['nullable', 'string', 'max:10'],
            'household_size' => ['required', 'integer', 'min:1', 'max:20'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'     => 'First name is required.',
            'last_name.required'      => 'Last name is required.',
            'household_size.required' => 'Household size is required.',
            'household_size.min'      => 'Household size must be at least 1.',
            'household_size.max'      => 'Household size cannot exceed 20.',
        ];
    }
}
