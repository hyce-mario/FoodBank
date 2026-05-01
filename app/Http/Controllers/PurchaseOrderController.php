<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\FinanceCategory;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): View
    {
        $query = PurchaseOrder::with(['creator', 'financeTransaction'])
            ->withCount('items')
            ->latest('order_date')
            ->latest('id');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('purchase-orders.index', compact('orders'));
    }

    public function create(): View
    {
        $items = InventoryItem::active()->with('category')->orderBy('name')->get();
        return view('purchase-orders.create', compact('items'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $po = $this->service->create($request->validated());

        return redirect()
            ->route('purchase-orders.show', $po)
            ->with('success', "Purchase order {$po->po_number} saved as draft.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'items.item.category',
            'items.inventoryMovement',
            'financeTransaction.category',
            'creator',
        ]);

        $expenseCategories = FinanceCategory::where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('purchase-orders.show', [
            'po'                => $purchaseOrder,
            'expenseCategories' => $expenseCategories,
        ]);
    }

    public function markReceived(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $request->validate([
            'finance_category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
        ]);

        try {
            $this->service->markReceived(
                $purchaseOrder,
                $request->integer('finance_category_id') ?: null,
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', "Received {$purchaseOrder->po_number}: stock posted and expense recorded.");
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->service->cancel($purchaseOrder);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', "Purchase order {$purchaseOrder->po_number} cancelled.");
    }
}
