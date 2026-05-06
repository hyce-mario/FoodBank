<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase D — Validates the bulk-allocate payload submitted from the wide
 * drawer on the event details > inventory tab.
 *
 * Bulk allocation is add-only by intent: the operator picks quantities for
 * one or more inventory items and the totals get pulled from the shelf and
 * attached to the event. Returning surplus to the shelf after the event is
 * a separate flow (the per-row Return action on the existing allocations
 * table) — bulk submit never reduces.
 *
 * Locked decisions:
 *  - inventory_item_id values must be unique within a single submission;
 *    a duplicate id is treated as user error and rejected with 422.
 *  - Zero-quantity rows are accepted by validation and silently skipped by
 *    the controller (operator left a row blank, not an error).
 */
class BulkAllocateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tier 2 — gates on inventory.edit. Bulk allocation decrements stock,
        // matching the inventory.edit gate on /inventory/items writes.
        return (bool) $this->user()?->hasPermission('inventory.edit');
    }

    public function rules(): array
    {
        return [
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id'  => ['required', 'integer', 'exists:inventory_items,id'],
            'items.*.allocated_quantity' => ['required', 'integer', 'min:0'],
            'notes'                      => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Reject duplicate inventory_item_id values inside one submission. The
     * after-hook runs once basic per-row validation passes, so we know each
     * row has an integer id by the time we get here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $items = (array) $this->input('items', []);
            $ids   = array_filter(array_map(
                fn ($r) => is_array($r) ? ($r['inventory_item_id'] ?? null) : null,
                $items,
            ));

            if (count($ids) !== count(array_unique($ids))) {
                $v->errors()->add(
                    'items',
                    'Each inventory item may only appear once in a single bulk allocation.'
                );
            }
        });
    }
}
