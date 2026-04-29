<?php

namespace App\Http\Requests;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $userId        = $this->route('user')?->id;
        $minLength     = max(8, (int) SettingService::get('security.password_min_length',    8));
        $requireStrong = (bool)       SettingService::get('security.require_strong_password', false);

        $passwordRule = Password::min($minLength);
        if ($requireStrong) {
            $passwordRule = $passwordRule->mixedCase()->numbers()->symbols();
        }

        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role_id'               => ['required', 'exists:roles,id'],
            'password'              => ['nullable', 'string', 'min:' . $minLength, 'confirmed', $passwordRule],
            'password_confirmation' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => trim($this->name ?? ''),
            'email' => strtolower(trim($this->email ?? '')),
        ]);
    }
}
