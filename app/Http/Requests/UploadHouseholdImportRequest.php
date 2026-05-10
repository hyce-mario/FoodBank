<?php

namespace App\Http\Requests;

use App\Services\HouseholdImportService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 6.5.e — file-upload validation for the household import flow.
 *
 * The mime-type rule is intentionally lenient because real-world CSV files
 * round-trip through Outlook / Slack / OneDrive with mime types like
 * `application/octet-stream`, `text/plain`, or
 * `application/vnd.ms-excel` depending on which app touched it last. We
 * fall back to extension-only validation via a custom rule when the mime
 * lookup is inconclusive.
 */
class UploadHouseholdImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Household::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:' . (HouseholdImportService::HARD_BYTE_CAP / 1024), // KB unit
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['csv', 'xlsx', 'txt'], true)) {
                        $fail('The file must be a .csv or .xlsx document.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a file to upload.',
            'file.file'     => 'The upload was not received as a valid file.',
            'file.max'      => sprintf(
                'File exceeds the %d MB cap for imports.',
                HouseholdImportService::HARD_BYTE_CAP / 1024 / 1024,
            ),
        ];
    }
}
