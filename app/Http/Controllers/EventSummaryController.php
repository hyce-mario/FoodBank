<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventSummaryService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Event Summary report — vertical-tab review for past events.
 *
 * Routes:
 *   GET events/{event}/summary           → show()  (HTML, vertical tabs)
 *   GET events/{event}/summary/pdf       → pdf()   (DomPDF download)
 *   GET events/{event}/summary/print     → print() (HTML, auto window.print)
 *   GET events/{event}/summary/export.xlsx → xlsx() (one sheet per section)
 *
 * All routes accept a `sections[]` query parameter to filter which sections
 * appear in the report. Empty / missing query = include all sections.
 *
 * Gating:
 *   - All routes require the event-show authorization (`view` policy on Event).
 *   - Reports only render for past events (`$event->isLocked()`); current /
 *     upcoming events 404.
 *   - The Finance section is additionally gated on `finance.view`; users
 *     without that permission see the section omitted from the report
 *     entirely (handled inside EventSummaryService).
 */
class EventSummaryController extends Controller
{
    public function __construct(private readonly EventSummaryService $summary) {}

    public function show(Request $request, Event $event): View
    {
        $this->authorize('view', $event);
        abort_unless($event->isLocked(), 404, 'Summaries are only available for past events.');

        $payload = $this->payloadFor($event, $request);

        return view('events.summary.show', $payload);
    }

    public function print(Request $request, Event $event): View
    {
        $this->authorize('view', $event);
        abort_unless($event->isLocked(), 404);

        $payload = $this->payloadFor($event, $request);
        $payload['autoPrint'] = true;

        return view('events.summary.print', $payload);
    }

    public function pdf(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);
        abort_unless($event->isLocked(), 404);

        $payload  = $this->payloadFor($event, $request);
        $filename = sprintf(
            'event-summary-%s-%s.pdf',
            Str::slug($event->name),
            $event->date?->format('Ymd') ?? now()->format('Ymd'),
        );

        try {
            return Pdf::loadView('events.summary.pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('event-summary-pdf: dompdf failed with logo embedded; retrying without logo.', [
                'message' => $e->getMessage(),
                'at'      => $e->getFile() . ':' . $e->getLine(),
            ]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('events.summary.pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function xlsx(Request $request, Event $event): StreamedResponse
    {
        $this->authorize('view', $event);
        abort_unless($event->isLocked(), 404);

        $payload  = $this->payloadFor($event, $request);
        $filename = sprintf(
            'event-summary-%s-%s.xlsx',
            Str::slug($event->name),
            $event->date?->format('Ymd') ?? now()->format('Ymd'),
        );

        $spreadsheet = $this->buildSpreadsheet($payload);
        $writer      = new XlsxWriter($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control'       => 'max-age=0',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
        );
    }

    // ─── Shared payload builder ─────────────────────────────────────────────

    /**
     * Build the section payload + branding, applying the `sections[]` query
     * filter. If no sections are requested, all sections are included.
     */
    private function payloadFor(Event $event, Request $request): array
    {
        $requested = (array) $request->input('sections', []);
        $sections  = array_values(array_intersect(EventSummaryService::ALL_SECTIONS, $requested));
        if (empty($sections)) {
            $sections = EventSummaryService::ALL_SECTIONS;
        }

        $payload = $this->summary->buildPayload($event, $sections, $request->user());
        // Drop the gated Finance section entirely when the user can't see it.
        if (isset($payload['data']['finance']['gated']) && $payload['data']['finance']['gated']) {
            unset($payload['data']['finance']);
            $payload['sections'] = array_values(array_diff($payload['sections'], ['finance']));
        }

        $payload['branding'] = [
            'logo_src' => SettingService::brandingLogoDataUri(),
            'app_name' => (string) SettingService::get('general.app_name', config('app.name')),
        ];

        return $payload;
    }

    // ─── XLSX builder ───────────────────────────────────────────────────────

    /**
     * One sheet per section. The sheet structure is intentionally simple
     * (label / value rows) so the export is easy to grep, pivot, or paste
     * into a board pack — not a re-creation of the in-app charts.
     */
    private function buildSpreadsheet(array $payload): Spreadsheet
    {
        $event = $payload['event'];
        $book  = new Spreadsheet();
        $book->removeSheetByIndex(0);

        // Cover sheet ------------------------------------------------------
        $cover = $book->createSheet();
        $cover->setTitle('Summary');
        $cover->setCellValue('A1', $payload['branding']['app_name'] . ' — Event Summary');
        $cover->mergeCells('A1:B1');
        $cover->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $cover->setCellValue('A3', 'Event');         $cover->setCellValue('B3', $event->name);
        $cover->setCellValue('A4', 'Date');          $cover->setCellValue('B4', $event->date?->format('Y-m-d'));
        $cover->setCellValue('A5', 'Location');      $cover->setCellValue('B5', $event->location ?? '');
        $cover->setCellValue('A6', 'Generated');     $cover->setCellValue('B6', now()->format('Y-m-d H:i'));
        $cover->setCellValue('A7', 'Sections');      $cover->setCellValue('B7', implode(', ', $payload['sections']));
        $cover->getStyle('A3:A7')->getFont()->setBold(true);
        $cover->getColumnDimension('A')->setAutoSize(true);
        $cover->getColumnDimension('B')->setWidth(60);

        // Per-section sheets ----------------------------------------------
        foreach ($payload['sections'] as $section) {
            if (! isset($payload['data'][$section])) {
                continue;
            }
            $sheet = $book->createSheet();
            $sheet->setTitle(Str::limit($this->sectionLabel($section), 31, ''));
            $this->writeSectionToSheet($sheet, $section, $payload['data'][$section]);
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
        }

        $book->setActiveSheetIndex(0);
        return $book;
    }

    private function writeSectionToSheet($sheet, string $section, $data): void
    {
        $row = 1;
        $sheet->setCellValue("A{$row}", $this->sectionLabel($section));
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B2B4B');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $row += 2;

        // Inventory and Reviews have row-shaped data; everything else is
        // a flat key/value list.
        if ($section === 'inventory' && isset($data['rows'])) {
            $sheet->fromArray(['Item', 'Allocated', 'Distributed', 'Returned', 'Remaining', 'Rate %'], null, "A{$row}");
            $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
            $row++;
            foreach ($data['rows'] as $r) {
                $sheet->fromArray([
                    $r['name'], $r['allocated'], $r['distributed'],
                    $r['returned'], $r['remaining'], round($r['rate'] * 100, 1),
                ], null, "A{$row}");
                $row++;
            }
            $row += 2;
            $sheet->setCellValue("A{$row}", 'Totals');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
            foreach ([
                'Total items'         => $data['total_items'],
                'Total allocated'     => $data['total_allocated'],
                'Total distributed'   => $data['total_distributed'],
                'Total returned'      => $data['total_returned'],
                'Distribution rate %' => round($data['distribution_rate'] * 100, 1),
            ] as $label => $value) {
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue("B{$row}", $value);
                $row++;
            }
            return;
        }

        if ($section === 'reviews') {
            $row = $this->writeKeyValues($sheet, $row, [
                'Total reviews' => $data['total'],
                'Average rating' => $data['avg_rating'],
            ]);
            $row++;
            $sheet->setCellValue("A{$row}", 'Distribution');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
            foreach ($data['distribution'] as $stars => $n) {
                $sheet->setCellValue("A{$row}", "{$stars}★");
                $sheet->setCellValue("B{$row}", $n);
                $row++;
            }
            $row++;
            $sheet->setCellValue("A{$row}", 'Good Reviews (4–5★)');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
            foreach ($data['good_reviews'] as $r) {
                $sheet->setCellValue("A{$row}", $r->reviewer_name ?: 'Anonymous');
                $sheet->setCellValue("B{$row}", "{$r->rating}★");
                $sheet->setCellValue("C{$row}", $r->review_text);
                $row++;
            }
            $row++;
            $sheet->setCellValue("A{$row}", 'Bad Reviews (1–2★)');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
            foreach ($data['bad_reviews'] as $r) {
                $sheet->setCellValue("A{$row}", $r->reviewer_name ?: 'Anonymous');
                $sheet->setCellValue("B{$row}", "{$r->rating}★");
                $sheet->setCellValue("C{$row}", $r->review_text);
                $row++;
            }
            return;
        }

        if ($section === 'finance' && empty($data['gated'])) {
            $row = $this->writeKeyValues($sheet, $row, [
                'Total income'  => '$' . number_format($data['income']['total'], 2),
                'Total expense' => '$' . number_format($data['expense']['total'], 2),
                'Net'           => '$' . number_format($data['net'], 2),
            ]);
            foreach (['income', 'expense'] as $kind) {
                $row++;
                $sheet->setCellValue("A{$row}", ucfirst($kind) . ' — Top Sources');
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $row++;
                foreach ($data[$kind]['top_sources'] as $src) {
                    $sheet->setCellValue("A{$row}", $src['name']);
                    $sheet->setCellValue("B{$row}", '$' . number_format($src['amount'], 2));
                    $sheet->setCellValue("C{$row}", round($src['pct'] * 100, 1) . '%');
                    $row++;
                }
            }
            return;
        }

        if ($section === 'evaluation' && is_array($data)) {
            $sheet->fromArray(['Kind', 'Category', 'Message'], null, "A{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
            $row++;
            foreach ($data as $i) {
                $sheet->fromArray([$i['kind'], $i['category'], $i['message']], null, "A{$row}");
                $row++;
            }
            return;
        }

        // Fallback: flatten any associative array into label/value rows.
        $this->writeKeyValues($sheet, $row, is_array($data) ? $this->flattenForXlsx($data) : ['value' => $data]);
    }

    private function writeKeyValues($sheet, int $startRow, array $kv): int
    {
        $row = $startRow;
        foreach ($kv as $label => $value) {
            $sheet->setCellValue("A{$row}", $this->humanize((string) $label));
            $sheet->setCellValue("B{$row}", is_scalar($value) || $value === null ? $value : json_encode($value));
            $row++;
        }
        return $row;
    }

    private function flattenForXlsx(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v) && ! array_is_list($v)) {
                $out += $this->flattenForXlsx($v, $key);
            } elseif (is_scalar($v) || $v === null) {
                $out[$key] = $v;
            } else {
                $out[$key] = json_encode($v);
            }
        }
        return $out;
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            'event_details' => 'Event Details',
            'attendees'     => 'Attendees',
            'volunteers'    => 'Volunteers',
            'reviews'       => 'Reviews',
            'inventory'     => 'Inventory',
            'finance'       => 'Finance',
            'queue'         => 'Queue Summary',
            'evaluation'    => 'Evaluation',
            default         => ucwords(str_replace('_', ' ', $section)),
        };
    }

    private function humanize(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
