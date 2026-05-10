<?php

namespace App\Services;

use App\Exceptions\HouseholdImportValidationException;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Phase 6.5.e — bulk import of households from CSV / XLSX.
 *
 * Workflow (per the user-chosen "Preview → Confirm" flow):
 *
 *   1. parseAndValidate(file) — reads + normalizes + validates every row.
 *      Throws HouseholdImportValidationException on ANY error — all-or-
 *      nothing semantics, no partial commits.
 *   2. flagDuplicates(rows) — annotates each row with the existing
 *      household(s) that match (using HouseholdService::findPotentialDuplicates,
 *      same logic the create-form warning panel uses).
 *   3. commit(rows, decisions, user) — applies per-row admin decisions
 *      (create / skip / create_anyway / update) inside a single
 *      DB::transaction. Returns a summary count.
 *
 * Production-safety constraints baked in:
 *   - HARD_ROW_CAP (5000) and HARD_BYTE_CAP (10 MB) so a misplaced upload
 *     can't blow memory or time-out the worker.
 *   - All JSON / array manipulation in PHP (no MySQL JSON functions) —
 *     same code path on SQLite test runner and MySQL production.
 *   - Phone normalized to a digits-only key for MATCHING; original format
 *     stored as the admin typed it. Existing storage semantics unchanged.
 *   - Email lowercased + trimmed for both storage and matching (matches
 *     HouseholdService::findPotentialDuplicates which already does
 *     LOWER(email) = LOWER(?)).
 *   - Per-row Auditable trait emissions are SUPPRESSED via withoutEvents
 *     and replaced with a single 'household.bulk_imported' rollup row at
 *     the end. User-chosen during scoping to avoid audit-log noise.
 */
class HouseholdImportService
{
    /** Hard cap on data rows, regardless of upload-size cap. */
    public const HARD_ROW_CAP = 5000;

    /** Hard cap on file size in bytes (matches the FormRequest rule). */
    public const HARD_BYTE_CAP = 10 * 1024 * 1024;

    /**
     * Canonical column order. The import template uses this; the parser
     * locates columns by header name (case-insensitive, trimmed) so column
     * order in the uploaded file does not matter — only header presence.
     *
     * Always-required and conditionally-required behaviour is enforced in
     * validateRow().
     */
    public const COLUMNS = [
        'first_name', 'last_name', 'email', 'phone',
        'city', 'state', 'zip',
        'children_count', 'adults_count', 'seniors_count',
        'vehicle_make', 'vehicle_color',
        'notes',
    ];

    public function __construct(
        private readonly HouseholdService $householdService,
    ) {}

    // ─── Stage 1: parse + validate ──────────────────────────────────────────

    /**
     * Read the file, normalize each row, validate every row. Throws on any
     * structural problem (missing required column, > 5000 rows, etc.) or
     * any row-level error.
     *
     * @return array<int,array{row_number:int,data:array,raw_phone:string|null}>
     */
    public function parseAndValidate(UploadedFile $file): array
    {
        $errors = [];

        if ($file->getSize() > self::HARD_BYTE_CAP) {
            $errors[] = [
                'row' => 'file', 'column' => null,
                'message' => sprintf('File exceeds the %d MB import cap.', self::HARD_BYTE_CAP / 1024 / 1024),
            ];
            throw new HouseholdImportValidationException($errors);
        }

        // Read via PhpSpreadsheet — handles CSV (with BOM, quoted commas,
        // embedded newlines) and XLSX uniformly.
        try {
            $spreadsheet = $this->loadSpreadsheet($file);
        } catch (\Throwable $e) {
            throw new HouseholdImportValidationException([
                ['row' => 'file', 'column' => null,
                 'message' => 'Could not read the file. Ensure it is a valid CSV or XLSX. (' . $e->getMessage() . ')'],
            ]);
        }

        $sheetCount = $spreadsheet->getSheetCount();
        $sheet      = $spreadsheet->getSheet(0);
        $rawRows    = $sheet->toArray(null, true, true, true); // {A: …, B: …, …}

        if (empty($rawRows)) {
            throw new HouseholdImportValidationException([
                ['row' => 'file', 'column' => null, 'message' => 'File is empty.'],
            ]);
        }

        // Header row = row 1.
        $headerRow = array_shift($rawRows);
        $headerMap = $this->buildHeaderMap($headerRow);  // lowercased name → spreadsheet column letter

        // Required columns must be present in the header.
        foreach (['first_name', 'last_name', 'children_count', 'adults_count', 'seniors_count'] as $required) {
            if (! isset($headerMap[$required])) {
                $errors[] = [
                    'row' => 'header', 'column' => $required,
                    'message' => sprintf('Missing required column "%s".', $required),
                ];
            }
        }

        // Conditional-required columns (driven by org settings).
        $conditional = $this->conditionallyRequiredColumns();
        foreach ($conditional as $col => $reason) {
            if (! isset($headerMap[$col])) {
                $errors[] = [
                    'row' => 'header', 'column' => $col,
                    'message' => sprintf('Missing required column "%s" (your org has "%s" enabled).', $col, $reason),
                ];
            }
        }

        if ($errors) {
            throw new HouseholdImportValidationException($errors);
        }

        // Walk data rows. Skip entirely-blank rows. Cap at HARD_ROW_CAP.
        $parsed       = [];
        $rowNumber    = 1;
        $dataRowCount = 0;
        $emailsSeen   = [];   // email-lowercased → first row that used it (for in-file dedup)
        $phonesSeen   = [];   // digits-only phone key → first row that used it

        foreach ($rawRows as $rawRow) {
            $rowNumber++;

            if ($this->isBlankRow($rawRow)) {
                continue;
            }

            $dataRowCount++;
            if ($dataRowCount > self::HARD_ROW_CAP) {
                $errors[] = [
                    'row' => $rowNumber, 'column' => null,
                    'message' => sprintf('File exceeds the %d-row import cap. Split the file and re-upload.', self::HARD_ROW_CAP),
                ];
                break;
            }

            $rowData = $this->extractRow($rawRow, $headerMap);
            $rowData = $this->normalizeRow($rowData);

            $rowErrors = $this->validateRow($rowData, $rowNumber);
            if ($rowErrors) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            // In-file duplicate detection — same email or phone repeated in
            // the same upload is almost always a paste error. Refuse the
            // pair so the admin can fix the source.
            $emailKey = $rowData['email'];
            if ($emailKey && isset($emailsSeen[$emailKey])) {
                $errors[] = [
                    'row' => $rowNumber, 'column' => 'email',
                    'message' => sprintf('Email "%s" already used on row %d in this file.', $emailKey, $emailsSeen[$emailKey]),
                ];
            } elseif ($emailKey) {
                $emailsSeen[$emailKey] = $rowNumber;
            }

            $phoneKey = $this->phoneMatchKey($rowData['phone']);
            if ($phoneKey && isset($phonesSeen[$phoneKey])) {
                $errors[] = [
                    'row' => $rowNumber, 'column' => 'phone',
                    'message' => sprintf('Phone "%s" already used on row %d in this file.', $rowData['phone'], $phonesSeen[$phoneKey]),
                ];
            } elseif ($phoneKey) {
                $phonesSeen[$phoneKey] = $rowNumber;
            }

            $parsed[] = [
                'row_number' => $rowNumber,
                'data'       => $rowData,
            ];
        }

        if ($errors) {
            throw new HouseholdImportValidationException($errors);
        }

        if (empty($parsed)) {
            throw new HouseholdImportValidationException([
                ['row' => 'file', 'column' => null, 'message' => 'File contains no data rows.'],
            ]);
        }

        // Multi-sheet warning surfaces in the controller flash, not as a
        // hard error — admin made a clear choice by uploading.
        if ($sheetCount > 1) {
            Log::info('household_import.multi_sheet_warning', [
                'sheet_count' => $sheetCount,
                'rows_read'   => count($parsed),
            ]);
        }

        return $parsed;
    }

    // ─── Stage 2: duplicate flagging for the preview ─────────────────────────

    /**
     * For each parsed row, attach the matching-household candidates so the
     * preview UI can render the per-row decision dropdown.
     *
     * @param  array<int,array>  $rows
     * @return array<int,array>  rows + ['matches', 'status', 'match_signals']
     */
    public function flagDuplicates(array $rows): array
    {
        foreach ($rows as &$row) {
            $candidates = $this->householdService->findPotentialDuplicates($row['data']);

            // findPotentialDuplicates does an exact-match on `phone`, which
            // misses cross-format pairs (existing "5551112222" vs incoming
            // "(555) 111-2222"). Run an additional digits-only phone match
            // here and merge the results. Kept local to the import path so
            // the create-form duplicate panel keeps its existing behavior.
            $rowPhoneKey = $this->phoneMatchKey($row['data']['phone']);
            if ($rowPhoneKey !== null && strlen($rowPhoneKey) >= 7) {
                $candidates = $candidates->concat(
                    $this->candidatesByDigitsOnlyPhone($rowPhoneKey)
                )->unique('id')->values();
            }

            $matches = $candidates->map(fn ($h) => [
                'id'                => $h->id,
                'household_number'  => $h->household_number,
                'full_name'         => trim($h->first_name . ' ' . $h->last_name),
                'email'             => $h->email,
                'phone'             => $h->phone,
            ])->all();

            // Discriminate "exact" (email or phone exactly equal) vs
            // "fuzzy" (soundex name match only). Drives the default
            // decision in the preview UI.
            $rowEmail    = $row['data']['email'];
            $rowPhoneKey = $this->phoneMatchKey($row['data']['phone']);
            $exactMatch  = false;
            foreach ($candidates as $c) {
                $cEmail    = $c->email ? mb_strtolower(trim((string) $c->email)) : null;
                $cPhoneKey = $this->phoneMatchKey($c->phone);
                if (($rowEmail && $cEmail === $rowEmail) || ($rowPhoneKey && $cPhoneKey === $rowPhoneKey)) {
                    $exactMatch = true;
                    break;
                }
            }

            $row['matches'] = $matches;
            $row['status']  = empty($matches)
                ? 'new'
                : ($exactMatch ? 'exact_match' : 'fuzzy_match');
        }

        return $rows;
    }

    // ─── Stage 3: commit ────────────────────────────────────────────────────

    /**
     * Apply per-row decisions inside one DB::transaction. Returns a summary.
     *
     * Auditable trait emissions on Household are suppressed during the loop
     * (via Household::withoutEvents) so we can write a single rollup
     * 'household.bulk_imported' AuditLog row instead of N per-row rows.
     * User-chosen during scoping to avoid audit-log noise.
     *
     * @param  array<int,array>  $rows         Output of flagDuplicates()
     * @param  array<int,array>  $decisions    Keyed by row_number. Shape:
     *                                         ['action' => 'create'|'skip'|'create_anyway'|'update',
     *                                          'update_target_id' => ?int]
     *
     * @return array{created:int,updated:int,skipped:int,
     *               created_household_ids:int[],updated_household_ids:int[]}
     */
    public function commit(array $rows, array $decisions, User $user): array
    {
        $created        = 0;
        $updated        = 0;
        $skipped        = 0;
        $createdIds     = [];
        $updatedIds     = [];

        DB::transaction(function () use ($rows, $decisions, $user, &$created, &$updated, &$skipped, &$createdIds, &$updatedIds) {
            // Suppress per-row Auditable emissions; we'll write a single
            // rollup row at the end. unsetEventDispatcher / setEventDispatcher
            // is the standard Laravel hook for this.
            Household::withoutEvents(function () use ($rows, $decisions, &$created, &$updated, &$skipped, &$createdIds, &$updatedIds) {
                foreach ($rows as $row) {
                    $rowNum   = $row['row_number'];
                    $decision = $decisions[$rowNum] ?? ['action' => 'create'];
                    $action   = $decision['action'] ?? 'create';

                    switch ($action) {
                        case 'skip':
                            $skipped++;
                            break;

                        case 'update':
                            $targetId = (int) ($decision['update_target_id'] ?? 0);
                            $existing = Household::lockForUpdate()->find($targetId);
                            if (! $existing) {
                                throw new \RuntimeException(
                                    "Row {$rowNum}: target household #{$targetId} no longer exists."
                                );
                            }
                            $existing->update($this->updateFields($row['data']));
                            $updated++;
                            $updatedIds[] = $existing->id;
                            break;

                        case 'create':
                        case 'create_anyway':
                        default:
                            $household    = $this->householdService->create($row['data']);
                            $created++;
                            $createdIds[] = $household->id;
                            break;
                    }
                }
            });

            // Single rollup audit row. target_type / target_id point at
            // the User who performed the bulk action — schema requires
            // target_id NOT NULL, and "user X performed bulk import" is
            // a semantically honest framing. The actual created /
            // updated household IDs live in after_json so they are still
            // queryable. Same pattern Phase 6.10 uses for the
            // permissions_changed rollup row (target = the role being
            // changed, payload = the diff).
            //
            // Action name MUST fit in audit_logs.action varchar(20). Naming
            // mirrors 'permissions_changed' (19 chars) — <noun>_<past_verb>
            // is the existing convention. Production MySQL strict mode
            // rejects oversize values with SQLSTATE[22001]; SQLite (test
            // suite) silently truncates, so length must be confirmed by
            // hand or by a manual MySQL test pass.
            AuditLog::create([
                'user_id'     => $user->id,
                'action'      => 'households_imported',
                'target_type' => User::class,
                'target_id'   => $user->id,
                'before_json' => null,
                'after_json'  => [
                    'created'                => $created,
                    'updated'                => $updated,
                    'skipped'                => $skipped,
                    'created_household_ids'  => $createdIds,
                    'updated_household_ids'  => $updatedIds,
                ],
                'ip_address'  => request()->ip(),
                'user_agent'  => mb_substr((string) request()->userAgent(), 0, 500),
            ]);
        });

        Log::info('household.bulk_imported', [
            'user_id' => $user->id,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return [
            'created'                => $created,
            'updated'                => $updated,
            'skipped'                => $skipped,
            'created_household_ids'  => $createdIds,
            'updated_household_ids'  => $updatedIds,
        ];
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    private function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        $ext      = strtolower($file->getClientOriginalExtension());
        $readerType = match ($ext) {
            'csv', 'txt' => 'Csv',
            'xlsx'       => 'Xlsx',
            'xls'        => 'Xls',
            default      => null,
        };

        if ($readerType === null) {
            throw new \RuntimeException("Unsupported file extension '.{$ext}'. Use .csv or .xlsx.");
        }

        /** @var IReader $reader */
        $reader = IOFactory::createReader($readerType);
        $reader->setReadDataOnly(true);

        // CSV: be permissive about delimiter (PhpSpreadsheet auto-detects
        // when input encoding is set). Default is comma; tabs and
        // semicolons round-trip cleanly via auto-detect.
        if ($readerType === 'Csv') {
            // PhpSpreadsheet's CSV reader autodetects delimiter when
            // setInputEncoding('UTF-8') is in play; BOMs are stripped.
            $reader->setInputEncoding('UTF-8');
            $reader->setEnclosure('"');
        }

        return $reader->load($file->getRealPath());
    }

    /**
     * Build a lowercased-header → spreadsheet-column-letter map.
     *
     * Strips a leading UTF-8 BOM defensively — some Windows tools (Notepad,
     * older PowerShell exports) prepend the BOM to the first cell on save,
     * which would otherwise turn "first_name" into "\xEF\xBB\xBFfirst_name"
     * and silently fail the missing-required-column check.
     *
     * @return array<string,string>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $col => $value) {
            $key = mb_strtolower(trim((string) $value));
            // Strip BOM if present at the start of the first header cell.
            if (str_starts_with($key, "\xEF\xBB\xBF")) {
                $key = substr($key, 3);
            }
            if ($key !== '') {
                $map[$key] = $col;
            }
        }
        return $map;
    }

    /**
     * True for rows the parser should silently skip — fully blank rows AND
     * comment rows whose first cell starts with `#`. The latter is a common
     * CSV convention and lets a user keep documentation comments inside a
     * data file without breaking the import.
     */
    private function isBlankRow(array $rawRow): bool
    {
        $firstNonEmpty = null;
        foreach ($rawRow as $v) {
            $trimmed = trim((string) ($v ?? ''));
            if ($trimmed !== '') {
                $firstNonEmpty = $trimmed;
                break;
            }
        }

        if ($firstNonEmpty === null) {
            return true; // entirely blank
        }

        // Comment row — first non-empty cell starts with `#`. Skip silently
        // so older templates (or hand-edited files) with `# README` lines
        // mixed into the data don't trip the required-field validator.
        return str_starts_with($firstNonEmpty, '#');
    }

    /**
     * Pull each canonical column out of the raw row using the header map.
     * Missing columns return null. No normalization yet.
     */
    private function extractRow(array $rawRow, array $headerMap): array
    {
        $out = [];
        foreach (self::COLUMNS as $col) {
            $letter = $headerMap[$col] ?? null;
            $out[$col] = $letter !== null ? ($rawRow[$letter] ?? null) : null;
        }
        return $out;
    }

    /**
     * Apply the per-field normalization rules:
     *   - Trim everything.
     *   - Empty / whitespace-only → null.
     *   - email: lowercase.
     *   - state: uppercase, exactly 2 chars or null.
     *   - zip: keep as string (preserve leading zeros).
     *   - children_count / adults_count / seniors_count: int, clamp >= 0.
     *   - phone: stored as-typed; the digits-only key is computed lazily for matching.
     */
    private function normalizeRow(array $row): array
    {
        $out = [];

        foreach ($row as $key => $value) {
            $s = trim((string) ($value ?? ''));
            $out[$key] = $s === '' ? null : $s;
        }

        if ($out['email'] !== null) {
            $out['email'] = mb_strtolower($out['email']);
        }

        if ($out['state'] !== null) {
            $out['state'] = mb_strtoupper($out['state']);
        }

        // Zip stays a string (preserve leading zeros). PhpSpreadsheet may
        // hand us an int when Excel auto-numerised the cell — coerce.
        if ($out['zip'] !== null) {
            $out['zip'] = (string) $out['zip'];
        }

        // Counts: cast to int, clamp >= 0. Empty cells become 0 here so
        // the validator's required+integer rules pass for the common case
        // of "row only has names". A row that intentionally puts negative
        // values gets caught by the min:0 rule below.
        foreach (['children_count', 'adults_count', 'seniors_count'] as $countCol) {
            $v = $out[$countCol];
            if ($v === null || $v === '') {
                $out[$countCol] = 0;
            } else {
                $out[$countCol] = (int) $v;
            }
        }

        return $out;
    }

    /**
     * Per-row validation. Returns an array of error rows (may be empty).
     *
     * @return array<int,array>
     */
    private function validateRow(array $data, int $rowNumber): array
    {
        $errors = [];

        // Excel-mangled-zip detection (date coercion). Excel sometimes
        // turns "08-540" into "Aug-540" or "8-540-1900" depending on
        // locale; the surface form is a date-shaped string. Catch and
        // surface specific copy so admins recognise the failure mode.
        if ($data['zip'] !== null && preg_match('/^\d{4,}-\d{2}-\d{2}/', $data['zip'])) {
            $errors[] = [
                'row' => $rowNumber, 'column' => 'zip',
                'message' => sprintf(
                    'ZIP field corrupted by Excel auto-format ("%s"). Format the ZIP column as Text and re-upload.',
                    $data['zip']
                ),
            ];
        }

        // Build the per-row Validator using the same rules the create form
        // uses, including the conditional-required behaviour.
        $rules = [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'size:2'],
            'zip'            => ['nullable', 'string', 'max:10'],
            'children_count' => ['required', 'integer', 'min:0', 'max:50'],
            'adults_count'   => ['required', 'integer', 'min:0', 'max:50'],
            'seniors_count'  => ['required', 'integer', 'min:0', 'max:50'],
            'vehicle_make'   => ['nullable', 'string', 'max:100'],
            'vehicle_color'  => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ];

        // Layer the conditional-required rules on top.
        foreach ($this->conditionallyRequiredColumns() as $col => $_) {
            // Replace 'nullable' with 'required' for these specific keys.
            $rules[$col] = array_values(array_filter(
                $rules[$col],
                fn ($r) => $r !== 'nullable',
            ));
            array_unshift($rules[$col], 'required');
        }

        $v = Validator::make($data, $rules);
        foreach ($v->errors()->messages() as $field => $messages) {
            foreach ($messages as $msg) {
                $errors[] = [
                    'row' => $rowNumber, 'column' => $field,
                    'message' => $msg,
                ];
            }
        }

        return $errors;
    }

    /**
     * Map of column → human-readable setting name, for any column that is
     * conditionally required by the org's settings.
     *
     * @return array<string,string>
     */
    private function conditionallyRequiredColumns(): array
    {
        $out = [];
        if (SettingService::get('households.require_phone', false)) {
            $out['phone'] = 'Require phone';
        }
        if (SettingService::get('households.require_address', false)) {
            $out['city']  = 'Require address';
            $out['state'] = 'Require address';
            $out['zip']   = 'Require address';
        }
        if (SettingService::get('households.require_vehicle_info', false)) {
            $out['vehicle_make']  = 'Require vehicle info';
            $out['vehicle_color'] = 'Require vehicle info';
        }
        return $out;
    }

    /**
     * Whitelisted fields that an "Update existing" decision is allowed to
     * overwrite. Excludes household_number / qr_token / representative_household_id /
     * events_attended_count — those are managed elsewhere and overwriting
     * via import would be unsafe.
     */
    private function updateFields(array $rowData): array
    {
        $allowed = [
            'first_name', 'last_name', 'email', 'phone',
            'city', 'state', 'zip',
            'children_count', 'adults_count', 'seniors_count',
            'vehicle_make', 'vehicle_color',
            'notes',
        ];

        $payload = array_intersect_key($rowData, array_flip($allowed));

        // Recompute household_size from the three counts (matches
        // HouseholdService::applyDemographics — clamp to >= 1).
        $size = (int) $payload['children_count']
              + (int) $payload['adults_count']
              + (int) $payload['seniors_count'];
        $payload['household_size'] = max(1, $size);

        return $payload;
    }

    /**
     * Digits-only key for a phone string. Used for duplicate matching only —
     * the original format is preserved when the household row is stored.
     */
    private function phoneMatchKey(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $key = preg_replace('/[^0-9+]/', '', $phone);
        $key = ltrim($key, '+'); // collapse "+1..." vs "1..." for matching
        return $key === '' ? null : $key;
    }

    /**
     * Find existing households whose `phone` column digits-only-matches the
     * given key. Done as a PHP filter over all phone-bearing households —
     * sidesteps cross-driver SQL string-function gotchas (MySQL REPLACE,
     * SQLite REPLACE, REGEXP semantics differ). For typical foodbank-scale
     * installations (low thousands of households) this is fast enough.
     *
     * Suffix match in either direction handles "+1 555-1234" matching "5551234"
     * and "5551234" matching "+15551234" symmetrically.
     */
    private function candidatesByDigitsOnlyPhone(string $digitsKey): \Illuminate\Support\Collection
    {
        return Household::query()
            ->whereNotNull('phone')
            ->select(['id', 'household_number', 'first_name', 'last_name', 'email', 'phone'])
            ->get()
            ->filter(function (Household $h) use ($digitsKey) {
                $key = $this->phoneMatchKey($h->phone);
                if ($key === null) {
                    return false;
                }
                return str_ends_with($key, $digitsKey) || str_ends_with($digitsKey, $key);
            })
            ->values();
    }
}
