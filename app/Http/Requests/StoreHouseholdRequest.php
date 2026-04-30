<?php

namespace App\Http\Requests;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Household::class) ?? false;
    }

    public function rules(): array
    {
        $requirePhone   = SettingService::get('households.require_phone', false);
        $requireAddress = SettingService::get('households.require_address', false);
        $requireVehicle = SettingService::get('households.require_vehicle_info', false);

        return [
            // ── Primary household ────────────────────────────────────────────
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => [$requirePhone ? 'required' : 'nullable', 'string', 'max:20'],
            'city'           => [$requireAddress ? 'required' : 'nullable', 'string', 'max:100'],
            'state'          => [$requireAddress ? 'required' : 'nullable', 'string', 'size:2'],
            'zip'            => [$requireAddress ? 'required' : 'nullable', 'string', 'max:10'],
            'vehicle_make'   => [$requireVehicle ? 'required' : 'nullable', 'string', 'max:100'],
            'vehicle_color'  => [$requireVehicle ? 'required' : 'nullable', 'string', 'max:50'],
            'children_count' => ['required', 'integer', 'min:0', 'max:50'],
            'adults_count'   => ['required', 'integer', 'min:0', 'max:50'],
            'seniors_count'  => ['required', 'integer', 'min:0', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:2000'],

            // ── Represented households (optional inline creation) ─────────────
            'represented_households'                  => ['nullable', 'array', 'max:10'],
            'represented_households.*.first_name'     => ['required', 'string', 'max:100'],
            'represented_households.*.last_name'      => ['required', 'string', 'max:100'],
            'represented_households.*.email'          => ['nullable', 'email', 'max:255'],
            'represented_households.*.phone'          => ['nullable', 'string', 'max:20'],
            'represented_households.*.children_count' => ['required', 'integer', 'min:0', 'max:50'],
            'represented_households.*.adults_count'   => ['required', 'integer', 'min:0', 'max:50'],
            'represented_households.*.seniors_count'  => ['required', 'integer', 'min:0', 'max:50'],
            'represented_households.*.notes'          => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'  => 'First name is required.',
            'last_name.required'   => 'Last name is required.',
            'children_count.min'   => 'Children count cannot be negative.',
            'adults_count.min'     => 'Adults count cannot be negative.',
            'seniors_count.min'    => 'Seniors count cannot be negative.',
            'represented_households.*.first_name.required' => 'Each represented household requires a first name.',
            'represented_households.*.last_name.required'  => 'Each represented household requires a last name.',
        ];
    }
}
