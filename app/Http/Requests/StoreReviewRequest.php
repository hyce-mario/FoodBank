<?php

namespace App\Http\Requests;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minLen       = (int) SettingService::get('reviews.min_review_length', 10);
        $maxLen       = (int) SettingService::get('reviews.max_review_length', 2000);
        $emailOptional = SettingService::get('reviews.email_optional', true);

        $textRules = ['required', 'string'];
        if ($minLen > 0) {
            $textRules[] = "min:{$minLen}";
        }
        $textRules[] = "max:{$maxLen}";

        return [
            'event_id'      => ['required', 'integer', 'exists:events,id'],
            'rating'        => ['required', 'integer', 'between:1,5'],
            'review_text'   => $textRules,
            'reviewer_name' => ['nullable', 'string', 'max:100'],
            'email'         => [$emailOptional ? 'nullable' : 'required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'Please select an event to review.',
            'event_id.exists'   => 'The selected event does not exist.',
            'rating.required'   => 'Please select a star rating.',
            'rating.between'    => 'Rating must be between 1 and 5 stars.',
            'review_text.required' => 'Please share your experience in the review field.',
        ];
    }
}
