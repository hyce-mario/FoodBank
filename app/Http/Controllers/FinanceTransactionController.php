<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinanceTransactionRequest;
use App\Http\Requests\UpdateFinanceTransactionRequest;
use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceTransactionController extends Controller
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'category_id', 'status', 'date_from', 'date_to', 'search']);

        $baseQuery = $this->filteredTransactionQuery($request);

        // Totals across all filtered rows (before pagination)
        $totals = (clone $baseQuery)
            ->select('transaction_type', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $incomeTotals  = (float) ($totals['income']  ?? 0);
        $expenseTotals = (float) ($totals['expense'] ?? 0);

        $perPage = (int) SettingService::get('general.records_per_page', 25);

        $transactions = $baseQuery
            ->with(['category', 'event'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = FinanceCategory::active()->orderBy('type')->orderBy('name')->get();

        return view('finance.transactions.index', compact(
            'transactions', 'categories', 'incomeTotals', 'expenseTotals', 'filters'
        ));
    }

    /**
     * Shared query builder for the index page AND the print/CSV exports.
     * Pulling this out keeps "the export shows what the screen shows" —
     * same filter rules apply, no opportunity for the two paths to drift.
     */
    private function filteredTransactionQuery(Request $request)
    {
        $query = FinanceTransaction::query();

        if ($type = $request->get('type')) {
            $query->where('transaction_type', $type);
        }
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->get('date_from')) {
            $query->whereDate('transaction_date', '>=', $from);
        }
        if ($to = $request->get('date_to')) {
            $query->whereDate('transaction_date', '<=', $to);
        }
        if ($search = $request->get('search')) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                  ->orWhere('source_or_payee', 'like', $like)
                  ->orWhere('reference_number', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * Build the human-readable applied-filters summary for the print
     * header. Mirrors the pattern from VolunteerController + HouseholdController.
     */
    private function exportFilterSummary(Request $request): array
    {
        $applied = [];
        if ($s = $request->get('search'))      $applied[] = "Search: \"{$s}\"";
        if ($t = $request->get('type'))        $applied[] = "Type: " . ucfirst($t);
        if ($c = $request->get('category_id')) {
            $name = FinanceCategory::find($c)?->name;
            if ($name) $applied[] = "Category: {$name}";
        }
        if ($st = $request->get('status'))     $applied[] = "Status: " . ucfirst($st);
        if ($f = $request->get('date_from'))   $applied[] = "From: {$f}";
        if ($t = $request->get('date_to'))     $applied[] = "To: {$t}";
        return $applied;
    }

    /**
     * Branding payload for exports — same shape as Volunteer/Household
     * exports (logo data URI + app name from SettingService).
     */
    private function exportBranding(): array
    {
        return [
            'logo_src' => SettingService::brandingLogoDataUri(),
            'app_name' => (string) SettingService::get('general.app_name', config('app.name')),
        ];
    }

    // ─── Exports — Print + CSV (no PDF per user direction) ───────────────────

    /**
     * Branded standalone HTML print sheet for the filtered transaction
     * list, with applied filters surfaced in the header. Auto-fires
     * window.print() after a paint tick — same pattern as the volunteer
     * + household + visit-log + service-history print sheets.
     *
     * Income/expense totals are computed up-front so the print header
     * carries the same KPI strip the screen shows (filtered Income /
     * Expenses / Net).
     */
    public function exportPrint(Request $request): View
    {
        $base = $this->filteredTransactionQuery($request);

        $totals = (clone $base)
            ->select('transaction_type', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $incomeTotals  = (float) ($totals['income']  ?? 0);
        $expenseTotals = (float) ($totals['expense'] ?? 0);

        $transactions = $base
            ->with(['category', 'event'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $appliedFilters = $this->exportFilterSummary($request);
        $branding = $this->exportBranding();
        $autoPrint = true;

        return view('finance.transactions.exports.print', compact(
            'transactions', 'incomeTotals', 'expenseTotals',
            'appliedFilters', 'branding', 'autoPrint',
        ));
    }

    /**
     * Streamed CSV download of the filtered transaction list. UTF-8 BOM
     * prepended so Excel opens it cleanly. Lazy-chunks at 500 rows so a
     * large transaction history doesn't blow memory.
     *
     * Columns chosen for accountant import (Quickbooks / Excel /
     * Google Sheets all consume this directly):
     *   Date, Type, Title, Category, Source/Payee, Amount, Status,
     *   Payment Method, Reference, Event, Notes.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = 'transactions-' . now()->format('Y-m-d-His') . '.csv';
        $query = $this->filteredTransactionQuery($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM — Excel respects this for proper encoding.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Date', 'Type', 'Title', 'Category', 'Source / Payee',
                'Amount', 'Status', 'Payment Method', 'Reference',
                'Event', 'Notes',
            ]);

            $query->with(['category', 'event'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->lazy(500)
                ->each(function ($tx) use ($out) {
                    fputcsv($out, [
                        $tx->transaction_date?->format('Y-m-d'),
                        ucfirst($tx->transaction_type),
                        $tx->title,
                        $tx->category?->name ?? '',
                        $tx->source_or_payee ?? '',
                        // 2 decimals, no thousands separator — accountant-friendly
                        number_format((float) $tx->amount, 2, '.', ''),
                        ucfirst($tx->status ?? ''),
                        $tx->payment_method ?? '',
                        $tx->reference_number ?? '',
                        $tx->event?->name ?? '',
                        $tx->notes ?? '',
                    ]);
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $categories = FinanceCategory::active()->orderBy('type')->orderBy('name')->get();
        $events     = Event::orderByDesc('date')->limit(50)->get();
        $preType    = $request->get('type', 'expense');
        $preEventId = $request->get('event_id');

        return view('finance.transactions.create', compact('categories', 'events', 'preType', 'preEventId'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreFinanceTransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')
                ->store('finance/attachments', 'local');
        }

        unset($data['attachment']); // not a DB column
        $transaction = FinanceTransaction::create($data);

        return redirect()
            ->route('finance.transactions.show', $transaction)
            ->with('success', "\"{$transaction->title}\" has been recorded.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(FinanceTransaction $transaction): View
    {
        $transaction->loadMissing('category', 'event', 'creator');
        return view('finance.transactions.show', compact('transaction'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(FinanceTransaction $transaction): View
    {
        $categories = FinanceCategory::active()->orderBy('type')->orderBy('name')->get();
        $events     = Event::orderByDesc('date')->limit(50)->get();

        return view('finance.transactions.edit', compact('transaction', 'categories', 'events'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateFinanceTransactionRequest $request, FinanceTransaction $transaction): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            // Delete old file if it exists
            if ($transaction->attachment_path) {
                Storage::disk('local')->delete($transaction->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')
                ->store('finance/attachments', 'local');
        }

        unset($data['attachment']);
        $transaction->update($data);

        return redirect()
            ->route('finance.transactions.show', $transaction)
            ->with('success', "\"{$transaction->title}\" has been updated.");
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(FinanceTransaction $transaction): RedirectResponse
    {
        if ($transaction->attachment_path) {
            Storage::disk('local')->delete($transaction->attachment_path);
        }

        $title = $transaction->title;
        $transaction->delete();

        return redirect()
            ->route('finance.transactions.index')
            ->with('success', "\"{$title}\" has been deleted.");
    }

    // ─── Download Attachment ──────────────────────────────────────────────────

    public function downloadAttachment(FinanceTransaction $transaction): StreamedResponse
    {
        abort_unless($transaction->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($transaction->attachment_path), 404);

        $filename  = basename($transaction->attachment_path);
        $mimeType  = Storage::disk('local')->mimeType($transaction->attachment_path);

        return Storage::disk('local')->download(
            $transaction->attachment_path,
            $filename,
            ['Content-Type' => $mimeType]
        );
    }

    // ─── Remove Attachment ────────────────────────────────────────────────────

    public function removeAttachment(FinanceTransaction $transaction): RedirectResponse
    {
        if ($transaction->attachment_path) {
            Storage::disk('local')->delete($transaction->attachment_path);
            $transaction->update(['attachment_path' => null]);
        }

        return redirect()
            ->route('finance.transactions.show', $transaction)
            ->with('success', 'Attachment removed.');
    }
}
