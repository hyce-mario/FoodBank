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
        // Phase 5.6.g: phone + email are unique when present, but the
        // CURRENT row's value is exempt so the volunteer can save without
        // changing their own phone/email. NULL values still coexist freely.
        $volunteerId = $this->route('volunteer')?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:20', Rule::unique('volunteers', 'phone')->ignore($volunteerId)],
            'email'      => ['nullable', 'email', 'max:255', Rule::unique('volunteers', 'email')->ignore($volunteerId)],
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
