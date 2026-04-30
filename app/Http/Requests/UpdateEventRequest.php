<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        return $event
            ? ($this->user()?->can('update', $event) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:150'],
            'date'               => ['required', 'date'],
            'location'           => ['required', 'string', 'max:255'],
            'lanes'              => ['required', 'integer', 'min:1', 'max:20'],
            'ruleset_id'         => ['nullable', 'integer', 'exists:allocation_rulesets,id'],
            'volunteer_group_id' => ['nullable', 'integer', 'exists:volunteer_groups,id'],
            'notes'              => ['nullable', 'string', 'max:10000'],
            'volunteer_ids'      => ['nullable', 'array'],
            'volunteer_ids.*'    => ['integer', 'exists:volunteers,id'],
        ];
    }
}
