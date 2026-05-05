<?php

namespace App\Http\Requests;

use App\Models\InventoryMovement;
use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on inventory.edit. Inventory movements mutate stock
        // levels (the same effect as edit) — keeping them under inventory.edit
        // means a warehouse role with inventory.edit can record adjustments
        // without a separate movement-specific permission.
        return (bool) $this->user()?->hasPermission('inventory.edit');
    }

    public function rules(): array
    {
        $requireNotes = (bool) SettingService::get('inventory.require_movement_notes', false);

        return [
            'action'        => ['required', Rule::in(['add', 'remove', 'adjust'])],
            'movement_type' => ['required_if:action,remove', Rule::in(array_keys(InventoryMovement::TYPES))],
            'quantity'      => ['required', 'integer', 'min:0'],
            'notes'         => [$requireNotes ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'movement_type.required_if' => 'Please select a removal reason.',
            'quantity.min'              => 'Quantity must be 0 or greater.',
            'notes.required'            => 'A reason is required for stock adjustments.',
        ];
    }
}
