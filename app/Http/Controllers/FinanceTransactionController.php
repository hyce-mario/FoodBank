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

        $baseQuery = FinanceTransaction::query();

        if ($type = $request->get('type')) {
            $baseQuery->where('transaction_type', $type);
        }
        if ($categoryId = $request->get('category_id')) {
            $baseQuery->where('category_id', $categoryId);
        }
        if ($status = $request->get('status')) {
            $baseQuery->where('status', $status);
        }
        if ($from = $request->get('date_from')) {
            $baseQuery->whereDate('transaction_date', '>=', $from);
        }
        if ($to = $request->get('date_to')) {
            $baseQuery->whereDate('transaction_date', '<=', $to);
        }
        if ($search = $request->get('search')) {
            $like = "%{$search}%";
            $baseQuery->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                  ->orWhere('source_or_payee', 'like', $like)
                  ->orWhere('reference_number', 'like', $like);
            });
        }

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
