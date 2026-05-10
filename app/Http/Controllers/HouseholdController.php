<?php

namespace App\Http\Controllers;

use App\Exceptions\HouseholdMergeConflictException;
use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\UpdateHouseholdRequest;
use App\Models\Household;
use App\Services\HouseholdMergeService;
use App\Services\HouseholdService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly HouseholdService $householdService,
        private readonly HouseholdMergeService $mergeService,
    ) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Household::class);
        $defaultPerPage = (int) SettingService::get('general.records_per_page', 25);
        $perPage = in_array((int) $request->get('per_page', $defaultPerPage), [10, 25, 50, 100])
            ? (int) $request->get('per_page', $defaultPerPage)
            : $defaultPerPage;

        $households = $this->filteredHouseholdQuery($request)->paginate($perPage)->withQueryString();

        return view('households.index', compact('households'));
    }

    /**
     * Build the filtered + sorted Household query used by both the paginated
     * directory view and the export endpoints (Phase C). Pulled out so a
     * future change to filter semantics ripples to all four call sites.
     *
     * Includes the `first_event_date` correlated subquery (Phase 6.7 design:
     * everything else is on the cached events_attended_count column).
     */
    private function filteredHouseholdQuery(Request $request)
    {
        $firstEventDateSub = DB::table('visit_households as vh2')
            ->join('visits as v2', 'vh2.visit_id', '=', 'v2.id')
            ->join('events as e2', 'v2.event_id', '=', 'e2.id')
            ->whereColumn('vh2.household_id', 'households.id')
            ->selectRaw('MIN(e2.date)');

        $query = Household::query()
            ->select('households.*')
            ->selectSub($firstEventDateSub, 'first_event_date');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $attendance = $request->get('attendance');
        if ($attendance === 'first_timer') {
            $query->where('events_attended_count', 1);
        } elseif ($attendance === 'returning') {
            $query->where('events_attended_count', '>', 1);
        }

        $sort      = in_array($request->get('sort'), ['household_number', 'first_name', 'household_size', 'created_at'])
            ? $request->get('sort')
            : 'created_at';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        return $query;
    }

    /**
     * Build the human-readable applied-filters summary shown in the print/PDF
     * header so the export is self-documenting.
     */
    private function exportFilterSummary(Request $request): array
    {
        $applied = [];
        if ($s = $request->get('search'))     $applied[] = "Search: \"{$s}\"";
        if (($a = $request->get('attendance')) === 'first_timer') $applied[] = "First-timers only";
        elseif ($a === 'returning')                               $applied[] = "Returning only";
        return $applied;
    }

    /**
     * Build the branding payload (logo + org name) used by every export.
     * Logo is delivered as a base64 data URI via SettingService — same
     * helper the live sidebar uses, so dev / prod render identically.
     */
    private function exportBranding(): array
    {
        return [
            'logo_src' => SettingService::brandingLogoDataUri(),
            'app_name' => (string) SettingService::get('general.app_name', config('app.name')),
        ];
    }

    // ─── Exports (Phase C) ────────────────────────────────────────────────────

    /**
     * Print-friendly HTML view of the full filtered household list.
     * Opens in a new tab; the template auto-triggers window.print().
     */
    public function exportPrint(Request $request): View
    {
        $this->authorize('viewAny', Household::class);

        $households       = $this->filteredHouseholdQuery($request)->get();
        $appliedFilters   = $this->exportFilterSummary($request);
        $branding         = $this->exportBranding();
        $autoPrint        = true;

        return view('households.exports.print', compact('households', 'appliedFilters', 'branding', 'autoPrint'));
    }

    /**
     * Render a Blade view to PDF, retrying without the embedded logo if
     * dompdf's image pipeline trips on it. Some Windows + Apache + dompdf
     * combinations throw "PHP GD extension is required" intermittently
     * even when GD is fully loaded — likely a temp-file write race in
     * dompdf's Image\Cache. The fallback keeps the export functional and
     * logs the underlying cause so we can diagnose without 500ing the user.
     */
    private function renderPdfWithLogoFallback(string $view, array $data, string $size, string $orientation, string $filename): Response
    {
        try {
            $pdf = Pdf::loadView($view, $data)->setPaper($size, $orientation);
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'dompdf failed with logo embedded; retrying without logo.',
                ['view' => $view, 'message' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()],
            );

            // Strip the logo from the branding payload and retry.
            if (isset($data['branding']) && is_array($data['branding'])) {
                $data['branding']['logo_src'] = null;
            }
            $pdf = Pdf::loadView($view, $data)->setPaper($size, $orientation);
            return $pdf->download($filename);
        }
    }

    /**
     * XLSX export of the full filtered household list, with a styled header
     * row, frozen top row, auto-sized columns, and a leading metadata row
     * carrying the applied filters so the spreadsheet is self-documenting.
     */
    public function exportXlsx(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Household::class);

        $households     = $this->filteredHouseholdQuery($request)->get();
        $appliedFilters = $this->exportFilterSummary($request);
        $branding       = $this->exportBranding();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Households');

        // Metadata band (A1:H3) ----------------------------------------------
        $sheet->setCellValue('A1', $branding['app_name'] . ' — Households Report');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Generated ' . now()->format('M j, Y g:i A') . ($appliedFilters ? ' · ' . implode(' · ', $appliedFilters) : ''));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        // Header row (row 4) -------------------------------------------------
        $headers = ['ID', 'Household', 'Email', 'Phone', 'Location', 'Zip', 'Size', 'Events Attended'];
        foreach ($headers as $i => $label) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue($col . '4', $label);
        }
        $sheet->getStyle('A4:H4')->getFont()->setBold(true);
        $sheet->getStyle('A4:H4')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1B2B4B');
        $sheet->getStyle('A4:H4')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:H4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->freezePane('A5');

        // Data rows ----------------------------------------------------------
        $row = 5;
        foreach ($households as $h) {
            $sheet->setCellValueExplicit('A' . $row, $h->household_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $h->full_name);
            $sheet->setCellValue('C' . $row, $h->email ?? '');
            $sheet->setCellValue('D' . $row, $h->phone ?? '');
            $sheet->setCellValue('E' . $row, $h->location ?: '');
            $sheet->setCellValueExplicit('F' . $row, $h->zip ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('G' . $row, $h->household_size);
            $sheet->setCellValue('H' . $row, (int) $h->events_attended_count);
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'households-' . now()->format('Y-m-d-His') . '.xlsx';
        $writer   = new XlsxWriter($spreadsheet);

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

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', Household::class);
        $householdSettings = $this->householdFormSettings();
        return view('households.create', compact('householdSettings'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        $this->authorize('create', Household::class);
        $data = $request->validated();

        // Phase 6.5.c: duplicate check unless staff has explicitly confirmed.
        // force_create=1 is set by the "Create anyway" button on the warning panel.
        if (! $request->boolean('force_create')) {
            $duplicates = $this->householdService->findPotentialDuplicates($data);
            if ($duplicates->isNotEmpty()) {
                return redirect()
                    ->route('households.create')
                    ->withInput()
                    ->with('potential_duplicates', $duplicates);
            }
        }

        $household = $this->householdService->create($data);

        return redirect()
            ->route('households.show', $household)
            ->with('success', "Household #{$household->household_number} created successfully.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Request $request, Household $household): View
    {
        $this->authorize('view', $household);
        $household->load(['representative', 'representedHouseholds']);

        // Candidate households for the attach modal (not yet linked, not self)
        $attachCandidates = Household::whereNull('representative_household_id')
            ->where('id', '!=', $household->id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'household_number']);

        // Phase 6.5.d — candidate keepers for the Merge picker. Excludes
        // the current household; ordered by name. Light payload (id +
        // names + household_number + phone) so the dropdown is fast even
        // on installations with thousands of households.
        $mergeCandidates = Household::where('id', '!=', $household->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'household_number', 'phone']);

        $perPage = in_array((int) $request->get('per_page', 10), [10, 25, 50])
            ? (int) $request->get('per_page', 10)
            : 10;

        $eventHistory = $this->eventHistoryQuery($household)->paginate($perPage)->withQueryString();
        $historyStats = $this->historyStats($household);

        return view('households.show', compact(
            'household',
            'attachCandidates',
            'mergeCandidates',
            'eventHistory',
            'historyStats',
        ));
    }

    /**
     * Shared event-history query for the show page (paginated) and per-household
     * exports (Phase D). Pulls visits this household appeared on, eager-loading
     * the event + ruleset (for bag calculation) and sibling households (for the
     * "picked up by" label on rep pickups). Newest visit first.
     */
    private function eventHistoryQuery(Household $household)
    {
        return $household->visits()
            ->with([
                'event.ruleset',
                'households' => fn ($q) => $q->select('households.id', 'first_name', 'last_name', 'household_number'),
            ])
            ->orderByDesc('visits.start_time');
    }

    /**
     * Aggregate stats for the household detail page stat cards.
     *
     * Counts only completed visits (visit_status = 'exited') because mid-flow
     * visits are part of an event still in progress; surfacing them as
     * "Total Visits" / "Total Bags Received" would double-count a household
     * that's currently on-site. Last Served uses the same filter.
     *
     * Bag totals use the event ruleset's getBagsFor() against the pivot
     * snapshot — matching how DistributionPostingService and the live monitor
     * compute per-household allocation. This keeps the stat consistent with
     * what the household actually received at the time of each visit, even if
     * their household_size has been edited since.
     */
    private function historyStats(Household $household): array
    {
        $exitedVisits = $household->visits()
            ->with('event.ruleset')
            ->where('visits.visit_status', 'exited')
            ->get();

        $totalBags = 0;
        $lastDate  = null;
        foreach ($exitedVisits as $visit) {
            $ruleset = $visit->event?->ruleset;
            if ($ruleset) {
                $snapshotSize = (int) ($visit->pivot->household_size ?? $household->household_size);
                $totalBags   += (int) $ruleset->getBagsFor($snapshotSize);
            }

            $eventDate = $visit->event?->date;
            if ($eventDate && (! $lastDate || $eventDate->greaterThan($lastDate))) {
                $lastDate = $eventDate;
            }
        }

        return [
            'total_visits'        => $exitedVisits->count(),
            'total_bags_received' => $totalBags,
            'last_served_at'      => $lastDate,
        ];
    }

    /**
     * Build presentation rows (event, date, location, bags, status, picked-up-by)
     * from the event history for the per-household exports. Mirrors what the
     * show-page table renders so the export matches what the user sees.
     */
    private function eventHistoryRows(Household $household): \Illuminate\Support\Collection
    {
        return $this->eventHistoryQuery($household)->get()->map(function ($visit) use ($household) {
            $event        = $visit->event;
            $ruleset      = $event?->ruleset;
            $snapshotSize = (int) ($visit->pivot->household_size ?? $household->household_size);
            $bags         = $ruleset ? (int) $ruleset->getBagsFor($snapshotSize) : 0;
            $primary      = $visit->households->first();
            $pickedUpBy   = ($primary && $primary->id !== $household->id) ? $primary : null;

            return (object) [
                'event_name'    => $event?->name ?? 'Event removed',
                'event_date'    => $event?->date,
                'event_location'=> $event?->location ?: '—',
                'bags'          => $bags,
                'status_label'  => $visit->visit_status === 'exited' ? 'Served' : 'In Progress',
                'picked_up_by'  => $pickedUpBy?->full_name,
            ];
        });
    }

    // ─── Per-household event report exports (Phase D) ────────────────────────

    public function eventReportPrint(Household $household): View
    {
        $this->authorize('view', $household);
        $rows      = $this->eventHistoryRows($household);
        $stats     = $this->historyStats($household);
        $branding  = $this->exportBranding();
        $autoPrint = true;

        return view('households.exports.event-report-print', compact('household', 'rows', 'stats', 'branding', 'autoPrint'));
    }

    public function eventReportPdf(Household $household): Response
    {
        $this->authorize('view', $household);
        $rows     = $this->eventHistoryRows($household);
        $stats    = $this->historyStats($household);
        $branding = $this->exportBranding();

        $filename = "event-report-{$household->household_number}-" . now()->format('Y-m-d-His') . '.pdf';
        return $this->renderPdfWithLogoFallback(
            'households.exports.event-report-pdf',
            compact('household', 'rows', 'stats', 'branding'),
            'a4',
            'portrait',
            $filename,
        );
    }

    public function eventReportXlsx(Household $household): StreamedResponse
    {
        $this->authorize('view', $household);
        $rows     = $this->eventHistoryRows($household);
        $stats    = $this->historyStats($household);
        $branding = $this->exportBranding();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Event Report');

        // Metadata band ------------------------------------------------------
        $sheet->setCellValue('A1', $branding['app_name'] . ' — Event Report');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', $household->full_name . ' · #' . $household->household_number);
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        $sheet->setCellValue('A3', 'Generated ' . now()->format('M j, Y g:i A')
            . ' · Total visits: ' . $stats['total_visits']
            . ' · Total bags received: ' . $stats['total_bags_received']
            . ' · Last served: ' . ($stats['last_served_at']?->format('M j, Y') ?? '—'));
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

        // Header row ---------------------------------------------------------
        $headers = ['Event', 'Date', 'Location', 'Bags Received', 'Status', 'Picked Up By'];
        foreach ($headers as $i => $label) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue($col . '5', $label);
        }
        $sheet->getStyle('A5:F5')->getFont()->setBold(true);
        $sheet->getStyle('A5:F5')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1B2B4B');
        $sheet->getStyle('A5:F5')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->freezePane('A6');

        // Data rows ----------------------------------------------------------
        $row = 6;
        foreach ($rows as $r) {
            $sheet->setCellValue('A' . $row, $r->event_name);
            $sheet->setCellValue('B' . $row, $r->event_date?->format('Y-m-d') ?? '');
            $sheet->setCellValue('C' . $row, $r->event_location);
            $sheet->setCellValue('D' . $row, $r->bags);
            $sheet->setCellValue('E' . $row, $r->status_label);
            $sheet->setCellValue('F' . $row, $r->picked_up_by ?? '');
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "event-report-{$household->household_number}-" . now()->format('Y-m-d-His') . '.xlsx';
        $writer   = new XlsxWriter($spreadsheet);

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

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(Household $household): View
    {
        $this->authorize('update', $household);
        $household->load('representedHouseholds');
        $householdSettings = $this->householdFormSettings();
        return view('households.edit', compact('household', 'householdSettings'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateHouseholdRequest $request, Household $household): RedirectResponse
    {
        $this->authorize('update', $household);
        $this->householdService->update($household, $request->validated());

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Household updated successfully.');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Household $household): RedirectResponse
    {
        $this->authorize('delete', $household);
        $household->delete();

        return redirect()
            ->route('households.index')
            ->with('success', 'Household deleted successfully.');
    }

    // ─── Regenerate QR ───────────────────────────────────────────────────────

    public function regenerateQr(Household $household): RedirectResponse
    {
        $this->authorize('update', $household);
        $this->householdService->regenerateQrToken($household);

        return back()->with('success', 'QR code regenerated successfully.');
    }

    // ─── Attach a household to this representative ────────────────────────────

    public function attach(Request $request, Household $household): RedirectResponse
    {
        $this->authorize('update', $household);
        $data = $request->validate([
            'represented_id' => ['required', 'integer', 'exists:households,id'],
        ]);

        $represented = Household::findOrFail($data['represented_id']);

        try {
            $this->householdService->attach($household, $represented);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "\"{$represented->full_name}\" is now linked to this household.");
    }

    // ─── Detach a represented household ──────────────────────────────────────

    public function detach(Household $household, Household $represented): RedirectResponse
    {
        $this->authorize('update', $household);
        if ($represented->representative_household_id !== $household->id) {
            return back()->with('error', 'That household is not linked to this representative.');
        }

        $this->householdService->detach($represented);

        return back()->with('success', "\"{$represented->full_name}\" has been unlinked.");
    }

    // ─── Merge into another household (Phase 6.5.d) ──────────────────────────

    /**
     * Merge this household (the "duplicate") into a chosen "keeper".
     *
     * Authorization: requires `households.delete` (the duplicate row will be
     * deleted) AND `households.edit` (the keeper row gets new attached
     * visits / pre-regs / pledges / represented households). Mirrors the
     * volunteer-merge precedent — both abilities checked explicitly so role
     * granularity holds. Route middleware additionally filters on
     * households.edit; the policy on $duplicate enforces households.delete.
     *
     * The atomic transfer is in HouseholdMergeService; this method is the
     * HTTP shim — it validates the keeper id, runs the service, and
     * surfaces HouseholdMergeConflictException as a friendly error
     * (same pattern as the Volunteer merge flow).
     */
    public function merge(Request $request, Household $household): RedirectResponse
    {
        $this->authorize('delete', $household);

        $data = $request->validate([
            'keeper_id' => ['required', 'integer', 'exists:households,id'],
        ]);

        // Laravel's `different:` rule references INPUT FIELDS, not route
        // bindings, so we can't express "different from the URL household"
        // declaratively. Compare here so the validator surfaces the
        // session error in the same shape any other field rule would.
        if ((int) $data['keeper_id'] === $household->id) {
            return back()
                ->withErrors(['keeper_id' => 'Cannot merge a household with itself.'])
                ->withInput();
        }

        $keeper = Household::findOrFail($data['keeper_id']);
        $this->authorize('update', $keeper);

        try {
            $result = $this->mergeService->merge($keeper, $household);
        } catch (HouseholdMergeConflictException $e) {
            $count = count($e->conflictingIds);
            $copy  = match ($e->conflictType) {
                'open_visit' =>
                    "Can't merge — both households have an active visit at {$count} event"
                    . ($count === 1 ? '' : 's')
                    . '. Please complete or exit one of them first.',
                'representative_cycle' =>
                    "Can't merge — doing so would create a circular link in the representative chain "
                    . "(would loop on {$count} household" . ($count === 1 ? '' : 's')
                    . '). Detach the affected representative link first.',
                default => $e->getMessage(),
            };
            return back()->with('error', $copy);
        }

        $visits     = $result['visits_transferred'];
        $preRegs    = $result['pre_regs_transferred'];
        $cancelled  = $result['pre_regs_cancelled'];
        $pledges    = $result['pledges_transferred'];
        $represented = $result['represented_transferred'];

        $parts = [
            "Transferred {$visits} visit"               . ($visits === 1 ? '' : 's'),
            "{$preRegs} pre-registration"               . ($preRegs === 1 ? '' : 's'),
            "{$pledges} pledge"                         . ($pledges === 1 ? '' : 's'),
            "{$represented} represented household"      . ($represented === 1 ? '' : 's'),
        ];
        $cancelledNote = $cancelled > 0
            ? " ({$cancelled} duplicate pre-registration" . ($cancelled === 1 ? '' : 's')
              . ' cancelled to avoid a same-event collision)'
            : '';

        return redirect()
            ->route('households.show', $keeper)
            ->with(
                'success',
                "Merged \"{$result['merged_household_name']}\" (#{$result['merged_household_number']}) into "
                . "\"{$keeper->full_name}\". " . implode(', ', $parts) . '.' . $cancelledNote,
            );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function householdFormSettings(): array
    {
        return [
            'require_phone'          => (bool) SettingService::get('households.require_phone',           false),
            'require_address'        => (bool) SettingService::get('households.require_address',         false),
            'require_vehicle_info'   => (bool) SettingService::get('households.require_vehicle_info',    false),
            'warn_duplicate_email'   => (bool) SettingService::get('households.warn_duplicate_email',    true),
            'warn_duplicate_phone'   => (bool) SettingService::get('households.warn_duplicate_phone',    true),
            'auto_generate_number'   => (bool) SettingService::get('households.auto_generate_household_number', true),
        ];
    }
}
