<?php

namespace App\Http\Controllers;

use App\Exceptions\HouseholdImportValidationException;
use App\Http\Requests\CommitHouseholdImportRequest;
use App\Http\Requests\UploadHouseholdImportRequest;
use App\Models\Household;
use App\Services\HouseholdImportService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 6.5.e — household bulk import.
 *
 *   GET  /households/import                — upload form (with template links)
 *   GET  /households/import/template/{format} — downloadable starter template
 *   POST /households/import/upload         — parse + validate + redirect to preview
 *   GET  /households/import/preview/{token} — per-row decision table
 *   POST /households/import/commit         — apply decisions atomically
 *
 * All five routes are gated on `permission:households.create`. Update-mode
 * decisions additionally require `households.edit` (controller-level
 * $this->authorize at commit time).
 */
class HouseholdImportController extends Controller
{
    public function __construct(
        private readonly HouseholdImportService $service,
    ) {}

    // ─── GET /households/import — upload form ───────────────────────────────

    public function create(): View
    {
        $this->authorize('create', Household::class);

        return view('households.import.upload', [
            'requiredColumns'        => $this->describeRequiredColumns(),
            'maxRows'                => HouseholdImportService::HARD_ROW_CAP,
            'maxBytes'               => HouseholdImportService::HARD_BYTE_CAP,
        ]);
    }

    // ─── GET /households/import/template/{format} — sample download ─────────

    public function template(string $format): StreamedResponse
    {
        $this->authorize('create', Household::class);

        $format = strtolower($format);
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Households');

        // Header row
        $headers = HouseholdImportService::COLUMNS;
        foreach ($headers as $i => $label) {
            $col = $this->columnLetter($i);
            $sheet->setCellValue($col . '1', $label);
        }
        $sheet->getStyle('A1:' . $this->columnLetter(count($headers) - 1) . '1')
              ->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $this->columnLetter(count($headers) - 1) . '1')
              ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B2B4B');
        $sheet->getStyle('A1:' . $this->columnLetter(count($headers) - 1) . '1')
              ->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->freezePane('A2');

        // Force phone (col D) and zip (col G) to be Text-formatted so Excel
        // does not auto-numerise leading-zero ZIPs or convert long phone
        // numbers to scientific notation. Has no effect on CSV; matters
        // for XLSX downloads.
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('@'); // phone
        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode('@'); // zip

        // Sample row 1 — fully filled
        $sample1 = [
            'Mary', 'Smith', 'mary.smith@example.org', '(555) 123-4567',
            'Princeton', 'NJ', '08540',
            2, 2, 0,
            'Toyota', 'Silver',
            'Picks up on Saturdays.',
        ];
        foreach ($sample1 as $i => $value) {
            $col = $this->columnLetter($i);
            // Phone (D) and ZIP (G) get explicit string typing so the
            // sample row itself does not Excel-mangle.
            if ($i === 3 || $i === 6) {
                $sheet->setCellValueExplicit($col . '2', (string) $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue($col . '2', $value);
            }
        }

        // Sample row 2 — minimum required only
        $sample2 = [
            'John', 'Doe', '', '',
            '', '', '',
            0, 1, 0,
            '', '',
            '',
        ];
        foreach ($sample2 as $i => $value) {
            $col = $this->columnLetter($i);
            $sheet->setCellValue($col . '3', $value);
        }

        // Auto-size — but cap so the doc opens cleanly even with long notes.
        foreach (range('A', $this->columnLetter(count($headers) - 1)) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // For XLSX only: add an "Instructions" sheet so the README is
        // accessible from inside Excel without polluting the data sheet.
        // CSV is single-sheet so it gets no README — the upload page
        // surfaces the required-columns reference there instead.
        if ($format === 'xlsx') {
            $instructions = $spreadsheet->createSheet();
            $instructions->setTitle('Instructions');
            $readmeLines = [
                ['Household Import Template — Instructions', true],
                ['', false],
                ['Required columns (always): first_name, last_name, children_count, adults_count, seniors_count.', false],
                ['Optional columns: email, phone, city, state, zip, vehicle_make, vehicle_color, notes.', false],
                ['', false],
                ['Update behaviour: when an existing household is matched, blank cells overwrite to NULL.', false],
                ['Auto-managed: household_number, qr_token, household_size (= max(1, children + adults + seniors)).', false],
                ['Representative chains: not imported in v1 — attach manually via the household Show page.', false],
                ['Phone format: stored as you type it; matched on digits-only so "(555) 123-4567" finds "5551234567".', false],
                ['', false],
                ['Delete the two sample rows on the Households sheet before adding your own data.', false],
            ];
            foreach ($readmeLines as $i => [$text, $bold]) {
                $instructions->setCellValue('A' . ($i + 1), $text);
                if ($bold) {
                    $instructions->getStyle('A' . ($i + 1))->getFont()->setBold(true)->setSize(14);
                }
            }
            $instructions->getColumnDimension('A')->setWidth(120);
        }

        $filename = 'households-import-template.' . $format;

        if ($format === 'csv') {
            $writer = new CsvWriter($spreadsheet);
            $writer->setUseBOM(true); // Excel-friendly UTF-8
            return response()->streamDownload(
                fn () => $writer->save('php://output'),
                $filename,
                [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ],
            );
        }

        $writer = new XlsxWriter($spreadsheet);
        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
        );
    }

    // ─── POST /households/import/upload — parse + validate ──────────────────

    public function store(UploadHouseholdImportRequest $request): RedirectResponse
    {
        $this->authorize('create', Household::class);

        try {
            $rows = $this->service->parseAndValidate($request->file('file'));
        } catch (HouseholdImportValidationException $e) {
            return back()
                ->with('import_errors', $e->errors)
                ->with('import_filename', $request->file('file')->getClientOriginalName());
        }

        $rows = $this->service->flagDuplicates($rows);

        $token   = (string) Str::uuid();
        $userId  = $request->user()->id;
        Cache::put("household_import:{$userId}:{$token}", [
            'rows'     => $rows,
            'filename' => $request->file('file')->getClientOriginalName(),
        ], now()->addMinutes(30));

        return redirect()->route('households.import.preview', ['token' => $token]);
    }

    // ─── GET /households/import/preview/{token} — per-row decisions ─────────

    public function preview(Request $request, string $token): View|RedirectResponse
    {
        $this->authorize('create', Household::class);

        $userId  = $request->user()->id;
        $payload = Cache::get("household_import:{$userId}:{$token}");

        if (! $payload) {
            return redirect()->route('households.import.create')
                ->with('error', 'Your import session expired or is invalid. Please re-upload the file.');
        }

        return view('households.import.preview', [
            'token'    => $token,
            'rows'     => $payload['rows'],
            'filename' => $payload['filename'],
        ]);
    }

    // ─── POST /households/import/commit — apply decisions atomically ────────

    public function commit(CommitHouseholdImportRequest $request): RedirectResponse
    {
        $this->authorize('create', Household::class);

        $userId  = $request->user()->id;
        $token   = $request->input('token');
        $payload = Cache::get("household_import:{$userId}:{$token}");

        if (! $payload) {
            return redirect()->route('households.import.create')
                ->with('error', 'Your import session expired. Please re-upload the file.');
        }

        $decisions = $request->input('decisions', []);

        // Authorize every "update" target up-front so a create-only role
        // hits the gate before any work begins. Also resolves the target
        // households so we fail fast if any have been deleted since the
        // preview was rendered.
        foreach ($decisions as $rowNum => $decision) {
            if (($decision['action'] ?? null) !== 'update') {
                continue;
            }
            $targetId = (int) ($decision['update_target_id'] ?? 0);
            $existing = Household::find($targetId);
            if (! $existing) {
                return back()->with(
                    'error',
                    "Row {$rowNum} targets household #{$targetId} which no longer exists. Re-upload the file."
                );
            }
            $this->authorize('update', $existing);
        }

        try {
            $result = $this->service->commit($payload['rows'], $decisions, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed and was rolled back: ' . $e->getMessage());
        }

        Cache::forget("household_import:{$userId}:{$token}");

        $created = $result['created'];
        $updated = $result['updated'];
        $skipped = $result['skipped'];

        return redirect()->route('households.index')->with(
            'success',
            sprintf(
                'Imported %d household%s — %d created, %d updated, %d skipped.',
                $created + $updated,
                ($created + $updated) === 1 ? '' : 's',
                $created,
                $updated,
                $skipped,
            ),
        );
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    /**
     * Build the human-readable required-columns description for the upload
     * page, reflecting current org settings.
     */
    private function describeRequiredColumns(): array
    {
        $always = ['first_name', 'last_name', 'children_count', 'adults_count', 'seniors_count'];

        $conditional = [];
        if (SettingService::get('households.require_phone', false)) {
            $conditional[] = ['column' => 'phone', 'reason' => 'Require phone setting is on'];
        }
        if (SettingService::get('households.require_address', false)) {
            $conditional[] = ['column' => 'city',  'reason' => 'Require address setting is on'];
            $conditional[] = ['column' => 'state', 'reason' => 'Require address setting is on'];
            $conditional[] = ['column' => 'zip',   'reason' => 'Require address setting is on'];
        }
        if (SettingService::get('households.require_vehicle_info', false)) {
            $conditional[] = ['column' => 'vehicle_make',  'reason' => 'Require vehicle info setting is on'];
            $conditional[] = ['column' => 'vehicle_color', 'reason' => 'Require vehicle info setting is on'];
        }

        return [
            'always'      => $always,
            'conditional' => $conditional,
        ];
    }

    /**
     * Convert 0-indexed column number to spreadsheet letter (A, B, …, Z, AA…).
     * 13 columns max so a single-letter conversion is sufficient for v1,
     * but the helper is general enough that adding a 14th+ column won't
     * break the template generator.
     */
    private function columnLetter(int $index): string
    {
        $letters = '';
        $n = $index;
        do {
            $letters = chr(ord('A') + ($n % 26)) . $letters;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        return $letters;
    }
}
