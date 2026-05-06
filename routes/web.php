<?php

use App\Http\Controllers\AllocationRulesetController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\FinanceCategoryController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\FinanceTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\EventInventoryAllocationController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\EventDayController;
use App\Http\Controllers\EventVolunteerCheckInController;
use App\Http\Controllers\VisitLogController;
use App\Http\Controllers\EventMediaController;
use App\Http\Controllers\PublicReviewController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\VisitMonitorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicVolunteerCheckInController;
use App\Http\Controllers\VolunteerController;
use App\Http\Controllers\VolunteerGroupController;
use Illuminate\Support\Facades\Route;

// ─── Event-Day Operational Pages (session auth, no admin nav) ────────────────
// Typeable URLs: /intake, /scanner, /loader, /exit  → picker (current events)
// After picking: /intake/9, /scanner/9, /loader/9, /exit/9 → auth-code form → role view
foreach (['intake', 'scanner', 'loader', 'exit'] as $_edRole) {
    Route::get("/{$_edRole}",                [EventDayController::class, 'landing'])    ->name("event-day.{$_edRole}.landing")->defaults('role', $_edRole);
    Route::get("/{$_edRole}/{event}",        [EventDayController::class, 'page'])       ->name("event-day.{$_edRole}");
    // Phase 3.1: auth-code POST is throttled per IP + role + event to prevent brute-force.
    Route::post("/{$_edRole}/{event}/auth",  [EventDayController::class, 'submitAuth']) ->name("event-day.{$_edRole}.auth")
         ->middleware('throttle:auth-code');
    Route::post("/{$_edRole}/{event}/out",   [EventDayController::class, 'logout'])     ->name("event-day.{$_edRole}.logout");
    Route::get("/{$_edRole}/{event}/data",   [EventDayController::class, 'data'])       ->name("event-day.{$_edRole}.data");
}
unset($_edRole);

// Visit status transitions (shared)
Route::prefix('ed')->name('event-day.')->group(function () {
    Route::patch('/{event}/visits/{visit}/queued',  [EventDayController::class, 'markQueued'])  ->name('visits.queued');
    Route::patch('/{event}/visits/{visit}/loaded',  [EventDayController::class, 'markLoaded'])  ->name('visits.loaded');
    Route::patch('/{event}/visits/{visit}/exited',  [EventDayController::class, 'markExited'])  ->name('visits.exited');
    Route::post('/{event}/reorder',                 [EventDayController::class, 'reorder'])      ->name('reorder');
});

// ─── Public Event Registration (no auth required) ─────────────────────────────
Route::prefix('register')->name('public.')->group(function () {
    Route::get('/',              [PublicEventController::class, 'index'])    ->name('events');
    Route::get('/{event}',       [PublicEventController::class, 'register']) ->name('register');
    // Phase 3.1: throttle submission to prevent form spam / duplicate registrations.
    Route::post('/{event}',      [PublicEventController::class, 'submit'])   ->name('submit')
         ->middleware('throttle:5,1');
    Route::get('/{event}/success',[PublicEventController::class, 'success']) ->name('success');
});

// ─── Public Review Submission (no auth required) ──────────────────────────────
Route::prefix('review')->name('public.reviews.')->group(function () {
    Route::get('/',  [PublicReviewController::class, 'create'])->name('create');
    // Phase 3.1: throttle to prevent review spam.
    Route::post('/', [PublicReviewController::class, 'store']) ->name('store')
         ->middleware('throttle:5,1');
});

// ─── Public Volunteer Check-In (no auth required) ─────────────────────────────
// Single permanent URL — resolves the active current event automatically.
// Page shows check-in form when an event is current; shows empty state otherwise.
Route::prefix('volunteer-checkin')->name('volunteer-checkin.')->group(function () {
    Route::get('/',         [PublicVolunteerCheckInController::class, 'index'])   ->name('index');
    Route::get('/search',   [PublicVolunteerCheckInController::class, 'search'])  ->name('search');
    // Phase 3.1: throttle write endpoints to prevent check-in spam.
    Route::post('/checkin',  [PublicVolunteerCheckInController::class, 'checkIn'])  ->name('checkin')
         ->middleware('throttle:5,1');
    Route::post('/checkout', [PublicVolunteerCheckInController::class, 'checkOut']) ->name('checkout')
         ->middleware('throttle:10,1');
    Route::post('/signup',   [PublicVolunteerCheckInController::class, 'signUp'])   ->name('signup')
         ->middleware('throttle:5,1');
});

// ─── Guest Routes ─────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// ─── Authenticated Routes ─────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Profile
    Route::get('/profile',          [ProfileController::class, 'show'])           ->name('profile');
    Route::put('/profile/info',     [ProfileController::class, 'updateInfo'])     ->name('profile.info');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']) ->name('profile.password');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Households
    // Phase C exports — registered BEFORE the resource route so /households/export/*
    // doesn't get parsed as Route::resource's show action with {household}=export.
    Route::get('households/export/print', [HouseholdController::class, 'exportPrint'])->name('households.export.print');
    Route::get('households/export/xlsx',  [HouseholdController::class, 'exportXlsx'])->name('households.export.xlsx');

    Route::resource('households', HouseholdController::class);

    // Phase D — per-household event report exports
    Route::get('households/{household}/event-report/print', [HouseholdController::class, 'eventReportPrint'])->name('households.event-report.print');
    Route::get('households/{household}/event-report/pdf',   [HouseholdController::class, 'eventReportPdf'])->name('households.event-report.pdf');
    Route::get('households/{household}/event-report/xlsx',  [HouseholdController::class, 'eventReportXlsx'])->name('households.event-report.xlsx');

    Route::post('households/{household}/regenerate-qr', [HouseholdController::class, 'regenerateQr'])
        ->name('households.regenerate-qr');
    Route::post('households/{household}/attach', [HouseholdController::class, 'attach'])
        ->name('households.attach');
    Route::delete('households/{household}/detach/{represented}', [HouseholdController::class, 'detach'])
        ->name('households.detach');

    // Events
    Route::resource('events', EventController::class);
    Route::patch('events/{event}/status', [EventController::class, 'updateStatus'])
        ->name('events.status');
    Route::delete('events/{event}/volunteers/{volunteer}', [EventController::class, 'detachVolunteer'])
        ->name('events.volunteers.detach');
    Route::post('events/{event}/attendees/{attendee}/match', [EventController::class, 'matchAttendee'])
        ->name('events.attendees.match');
    Route::post('events/{event}/attendees/{attendee}/dismiss', [EventController::class, 'dismissAttendee'])
        ->name('events.attendees.dismiss');
    Route::post('events/{event}/attendees/{attendee}/register', [EventController::class, 'registerAttendee'])
        ->name('events.attendees.register');
    Route::delete('events/{event}/attendees/{attendee}', [EventController::class, 'deleteAttendee'])
        ->name('events.attendees.delete');
    // Phase C.3 — branded printable sheet + streamed CSV download
    Route::get('events/{event}/attendees/print', [EventController::class, 'attendeesPrint'])
        ->name('events.attendees.print');
    Route::get('events/{event}/attendees/export.csv', [EventController::class, 'attendeesCsv'])
        ->name('events.attendees.csv');
    Route::post('events/{event}/regenerate-codes', [EventController::class, 'regenerateCodes'])
        ->name('events.regenerate-codes');

    // Admin-side volunteer check-in / checkout for a specific event.
    // Tier 2 — all routes mutate volunteer service rows; gate on volunteers.edit.
    Route::middleware('permission:volunteers.edit')->group(function () {
        Route::post('events/{event}/volunteer-checkins',
            [EventVolunteerCheckInController::class, 'store'])
            ->name('events.volunteer-checkins.store');
        Route::post('events/{event}/volunteer-checkins/bulk',
            [EventVolunteerCheckInController::class, 'bulkStore'])
            ->name('events.volunteer-checkins.bulk');
        Route::post('events/{event}/volunteer-checkins/bulk-checkout',
            [EventVolunteerCheckInController::class, 'bulkCheckout'])
            ->name('events.volunteer-checkins.bulk-checkout');
        Route::patch('events/{event}/volunteer-checkins/{checkIn}/checkout',
            [EventVolunteerCheckInController::class, 'checkout'])
            ->name('events.volunteer-checkins.checkout');
    });

    // Event Inventory Allocations — Tier 2. All routes mutate stock, so
    // the gate is inventory.edit (matching the FormRequests' authorize).
    Route::middleware('permission:inventory.edit')->group(function () {
        Route::post('events/{event}/inventory',                                      [EventInventoryAllocationController::class, 'store'])              ->name('events.inventory.store');
        // Phase D — atomic bulk allocation drawer
        Route::post('events/{event}/inventory/bulk',                                 [EventInventoryAllocationController::class, 'bulkStore'])          ->name('events.inventory.bulk');
        Route::patch('events/{event}/inventory/{allocation}/distributed',            [EventInventoryAllocationController::class, 'updateDistributed'])   ->name('events.inventory.distributed');
        Route::post('events/{event}/inventory/{allocation}/return',                  [EventInventoryAllocationController::class, 'returnStock'])         ->name('events.inventory.return');
        Route::delete('events/{event}/inventory/{allocation}',                       [EventInventoryAllocationController::class, 'destroy'])             ->name('events.inventory.destroy');
    });
    // Event Media — Tier 2. Photos / videos / docs attached to an event;
    // gate on events.edit since they're event-scoped attachments.
    Route::middleware('permission:events.edit')->group(function () {
        Route::post('events/{event}/media',          [EventMediaController::class, 'store'])  ->name('events.media.store');
        Route::delete('events/{event}/media/{media}',[EventMediaController::class, 'destroy'])->name('events.media.destroy');
    });

    // Check-in — admin-only routes (the page itself and admin-shaped actions).
    // Tier 2 — reads (index/queue) gated on checkin.view; writes (quick-add,
    // mark done) gated on checkin.scan. Note: the shared /checkin POST + GET
    // /checkin/search etc. that sit in the event-day-or-auth group below are
    // intentionally NOT gated by permission middleware — they're shared with
    // the public intake kiosk flow which auths via event-day session, not user.
    Route::get('/checkin',                  [CheckInController::class, 'index'])
         ->middleware('permission:checkin.view')
         ->name('checkin.index');
    Route::get('/checkin/queue',            [CheckInController::class, 'queue'])
         ->middleware('permission:checkin.view')
         ->name('checkin.queue');
    Route::post('/checkin/quick-add',       [CheckInController::class, 'quickAdd'])
         ->middleware('permission:checkin.scan')
         ->name('checkin.quickAdd');
    Route::patch('/checkin/{visit}/done',   [CheckInController::class, 'done'])
         ->middleware('permission:checkin.scan')
         ->name('checkin.done');

    // Volunteers
    // List exports — registered BEFORE the resource route so /volunteers/export/*
    // doesn't get parsed as Route::resource's show action with {volunteer}=export.
    Route::get('volunteers/export/print', [VolunteerController::class, 'exportPrint'])->name('volunteers.export.print');
    Route::get('volunteers/export/csv',   [VolunteerController::class, 'exportCsv'])  ->name('volunteers.export.csv');

    Route::resource('volunteers', VolunteerController::class);
    Route::post('volunteers/{volunteer}/groups', [VolunteerController::class, 'attachGroup'])
        ->name('volunteers.groups.attach');
    Route::post('volunteers/{volunteer}/merge', [VolunteerController::class, 'merge'])
        ->name('volunteers.merge');
    Route::get('volunteers/{volunteer}/service-history/print', [VolunteerController::class, 'serviceHistoryPrint'])
        ->name('volunteers.service-history.print');
    Route::get('volunteers/{volunteer}/service-history/export.csv', [VolunteerController::class, 'serviceHistoryCsv'])
        ->name('volunteers.service-history.csv');

    // Inventory — Tier 2. Reads gated on inventory.view; writes gated on
    // inventory.edit. The catalog uses just two keys (view/edit) — no
    // separate inventory.create or inventory.delete since the pre-Tier-1
    // catalog already had this layout.
    //
    // Categories — InventoryCategoryController uses inline $request->validate
    // (no FormRequest) so writes need explicit middleware. Resource is split
    // into read-only index (inventory.view) and write-only store/update/
    // destroy (inventory.edit).
    Route::resource('inventory/categories', InventoryCategoryController::class)
         ->names('inventory.categories')
         ->parameters(['categories' => 'inventoryCategory'])
         ->only(['index'])
         ->middleware('permission:inventory.view');
    Route::resource('inventory/categories', InventoryCategoryController::class)
         ->names('inventory.categories')
         ->parameters(['categories' => 'inventoryCategory'])
         ->only(['store', 'update', 'destroy'])
         ->middleware('permission:inventory.edit');

    // Inventory — Items: print + CSV export (registered BEFORE resource so the
    // wildcard {inventory_item} show route doesn't swallow these literals).
    // Both are reads — gated on inventory.view.
    Route::get('inventory/items/print',      [InventoryItemController::class, 'print'])
         ->middleware('permission:inventory.view')
         ->name('inventory.items.print');
    Route::get('inventory/items/export.csv', [InventoryItemController::class, 'export'])
         ->middleware('permission:inventory.view')
         ->name('inventory.items.export');

    // Inventory — Items. index/show are reads (inventory.view); Store/Update
    // FormRequests gate writes on inventory.edit; destroy gets explicit
    // middleware (no FormRequest on the destroy action).
    Route::resource('inventory/items', InventoryItemController::class)
         ->names('inventory.items')
         ->parameters(['items' => 'inventory_item'])
         ->only(['index', 'show'])
         ->middleware('permission:inventory.view');
    Route::resource('inventory/items', InventoryItemController::class)
         ->names('inventory.items')
         ->parameters(['items' => 'inventory_item'])
         ->only(['create', 'store', 'edit', 'update'])
         ->middleware('permission:inventory.edit');
    Route::delete('inventory/items/{inventory_item}', [InventoryItemController::class, 'destroy'])
         ->middleware('permission:inventory.edit')
         ->name('inventory.items.destroy');

    // Inventory — Quick-create JSON endpoint (used by Purchase Order line-item
    // picker to create + select an item without leaving the form). Writes
    // require inventory.edit.
    Route::post('inventory/items/quick-create', [InventoryItemController::class, 'quickStore'])
         ->middleware('permission:inventory.edit')
         ->name('inventory.items.quick-create');

    // Inventory — Movements (manual stock operations). FormRequest authorize
    // also gates on inventory.edit; this middleware is the route-level mirror.
    Route::post('inventory/items/{inventory_item}/movements', [InventoryMovementController::class, 'store'])
         ->middleware('permission:inventory.edit')
         ->name('inventory.movements.store');

    // Phase 6.6 — Purchase Orders (Inventory ↔ Finance bridge). Tier 2 —
    // gates split per action so a Buyer (create) and a Receiver (receive)
    // can be configured independently:
    //   - reads (index/show/print) → purchase_orders.view
    //   - create (store)           → purchase_orders.create
    //                                 (Tier 3c FormRequest authorize is the
    //                                  defense-in-depth check inside)
    //   - receive (markReceived)   → purchase_orders.receive
    //   - cancel                   → purchase_orders.cancel
    // create/store registered FIRST so the literal /create URL doesn't
    // get swallowed by show's wildcard /{purchaseOrder}.
    Route::resource('purchase-orders', PurchaseOrderController::class)
         ->parameters(['purchase-orders' => 'purchaseOrder'])
         ->only(['create', 'store'])
         ->middleware('permission:purchase_orders.create');
    Route::resource('purchase-orders', PurchaseOrderController::class)
         ->parameters(['purchase-orders' => 'purchaseOrder'])
         ->only(['index', 'show'])
         ->middleware('permission:purchase_orders.view');
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'markReceived'])
         ->middleware('permission:purchase_orders.receive')
         ->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
         ->middleware('permission:purchase_orders.cancel')
         ->name('purchase-orders.cancel');
    Route::get('purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])
         ->middleware('permission:purchase_orders.view')
         ->name('purchase-orders.print');

    // Allocation Rulesets — Tier 2. Rulesets drive bag composition (sit on
    // the inventory side of the system), so they reuse inventory.edit
    // rather than introducing a separate rulesets.* permission. Preview is
    // a read but only useful to someone who can already edit, so it shares
    // the inventory.edit gate.
    Route::resource('allocation-rulesets', AllocationRulesetController::class)
         ->except(['show', 'create', 'edit'])
         ->middleware('permission:inventory.edit');
    Route::get('allocation-rulesets/{allocation_ruleset}/preview', [AllocationRulesetController::class, 'preview'])
        ->middleware('permission:inventory.edit')
        ->name('allocation-rulesets.preview');

    // Users — Tier 3b. Baseline gate is users.view; Store/UpdateUserRequest +
    // UserPolicy::delete handle the granular create/edit/delete checks.
    // UserController::update line 97 keeps a defense-in-depth isAdmin() check
    // on role assignment so a non-admin users.edit grantee can rename + email
    // change, but NOT mutate role_id.
    Route::resource('users', UserController::class)
         ->middleware('permission:users.view');

    // Roles & Permissions — Tier 2. CRITICAL prior gap: any authenticated user
    // could create / edit / delete roles, including assigning the '*' wildcard
    // to themselves. Baseline gate is roles.view; FormRequests + RolePolicy
    // handle the granular create/edit/delete checks.
    Route::resource('roles', RoleController::class)
         ->middleware('permission:roles.view');

    // Volunteer Groups
    Route::resource('volunteer-groups', VolunteerGroupController::class);
    Route::get('volunteer-groups/{volunteer_group}/members', [VolunteerGroupController::class, 'editMembers'])
        ->name('volunteer-groups.members.edit');
    Route::post('volunteer-groups/{volunteer_group}/members', [VolunteerGroupController::class, 'updateMembers'])
        ->name('volunteer-groups.members.update');

    // Audit Log (read-only). Tier 3a — gated behind the dedicated audit_logs.view
    // permission rather than hard-coded isAdmin(); ADMIN keeps full access via the
    // '*' wildcard. Lets a Compliance Officer / Reports role read audits without
    // being granted full admin powers.
    Route::get('audit-logs', [AuditLogController::class, 'index'])
         ->middleware('permission:audit_logs.view')
         ->name('audit-logs.index');

    // Event Reviews (admin) — Tier 2. ReviewController already calls
    // \$this->authorize() which routes through EventReviewPolicy
    // (reviews.view / reviews.moderate). Adding route middleware as
    // defense in depth so the gate is visible at the route table.
    Route::get('reviews', [ReviewController::class, 'index'])
         ->middleware('permission:reviews.view')
         ->name('reviews.index');
    Route::patch('reviews/{review}/toggle-visibility', [ReviewController::class, 'toggleVisibility'])
        ->middleware('permission:reviews.moderate')
        ->name('reviews.toggle-visibility');

    // Visit Monitor — Tier 2. Read endpoints gated on checkin.view; reorder
    // + transition mutate visit state and gate on checkin.scan.
    Route::get('monitor',                                     [VisitMonitorController::class, 'index'])
         ->middleware('permission:checkin.view')
         ->name('monitor.index');
    Route::get('monitor/{event}/data',                        [VisitMonitorController::class, 'data'])
         ->middleware('permission:checkin.view')
         ->name('monitor.data');
    Route::post('monitor/{event}/reorder',                    [VisitMonitorController::class, 'reorder'])
         ->middleware('permission:checkin.scan')
         ->name('monitor.reorder');
    Route::patch('monitor/{event}/visits/{visit}/transition', [VisitMonitorController::class, 'transition'])
         ->middleware('permission:checkin.scan')
         ->name('monitor.transition');

    // Visit Log / Event Operations Report — Tier 2. All reads, gate on checkin.view.
    Route::middleware('permission:checkin.view')->group(function () {
        Route::get('visit-log',        [VisitLogController::class, 'index'])  ->name('visit-log.index');
        Route::get('visit-log/print',  [VisitLogController::class, 'print'])  ->name('visit-log.print');
        Route::get('visit-log/export', [VisitLogController::class, 'export']) ->name('visit-log.export');
    });

    // Finance Module — Tier 2. Each sub-section is gated independently:
    //   /finance               → finance.view (dashboard)
    //   /finance/categories    → finance.view (read), finance.edit (writes)
    //   /finance/transactions  → finance.view (read), finance.{create,edit,delete} via FormRequest
    //   /finance/reports/*     → finance_reports.view, +finance_reports.export on print/pdf/csv
    // Reports sit in their own permission key so a Finance Reports analyst
    // role doesn't transitively require finance.view (and vice versa).
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/',        [FinanceController::class, 'dashboard'])
             ->middleware('permission:finance.view')
             ->name('dashboard');

        // Phase 7.1+ — Finance Reports module. Tier 2 — gated independently
        // from the rest of /finance so a Reports analyst role doesn't
        // transitively require finance.view (and vice versa). Read endpoints
        // get finance_reports.view; print/pdf/csv siblings additionally
        // require finance_reports.export so a viewer can browse the data on
        // screen without being able to bulk-export it (matches the
        // /reports/* + /reports/download split established in Phase 5.13).
        Route::middleware('permission:finance_reports.view')->group(function () {
            Route::get('/reports', [FinanceReportController::class, 'hub'])->name('reports');

            Route::prefix('reports/statement-of-activities')->name('reports.statement-of-activities')->group(function () {
                Route::get('/', [FinanceReportController::class, 'statementOfActivities']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'statementOfActivitiesPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'statementOfActivitiesPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'statementOfActivitiesCsv'])  ->name('.csv');
                });
            });

            Route::prefix('reports/income-detail')->name('reports.income-detail')->group(function () {
                Route::get('/', [FinanceReportController::class, 'incomeDetail']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'incomeDetailPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'incomeDetailPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'incomeDetailCsv'])  ->name('.csv');
                });
            });

            Route::prefix('reports/expense-detail')->name('reports.expense-detail')->group(function () {
                Route::get('/', [FinanceReportController::class, 'expenseDetail']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'expenseDetailPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'expenseDetailPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'expenseDetailCsv'])  ->name('.csv');
                });
            });

            Route::prefix('reports/general-ledger')->name('reports.general-ledger')->group(function () {
                Route::get('/', [FinanceReportController::class, 'generalLedger']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'generalLedgerPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'generalLedgerPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'generalLedgerCsv'])  ->name('.csv');
                });
            });

            // Phase 7.3.a — Donor / Source Analysis
            Route::prefix('reports/donor-analysis')->name('reports.donor-analysis')->group(function () {
                Route::get('/', [FinanceReportController::class, 'donorAnalysis']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'donorAnalysisPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'donorAnalysisPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'donorAnalysisCsv'])  ->name('.csv');
                });
            });

            // Phase 7.3.b — Vendor / Payee Analysis
            Route::prefix('reports/vendor-analysis')->name('reports.vendor-analysis')->group(function () {
                Route::get('/', [FinanceReportController::class, 'vendorAnalysis']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'vendorAnalysisPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'vendorAnalysisPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'vendorAnalysisCsv'])  ->name('.csv');
                });
            });

            // Phase 7.3.c — Per-Event P&L
            Route::prefix('reports/per-event-pnl')->name('reports.per-event-pnl')->group(function () {
                Route::get('/', [FinanceReportController::class, 'perEventPnl']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'perEventPnlPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'perEventPnlPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'perEventPnlCsv'])  ->name('.csv');
                });
            });

            // Phase 7.3.d — Category Trend Report
            Route::prefix('reports/category-trend')->name('reports.category-trend')->group(function () {
                Route::get('/', [FinanceReportController::class, 'categoryTrend']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'categoryTrendPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'categoryTrendPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'categoryTrendCsv'])  ->name('.csv');
                });
            });

            // Phase 7.4.a — Statement of Functional Expenses
            Route::prefix('reports/functional-expenses')->name('reports.functional-expenses')->group(function () {
                Route::get('/', [FinanceReportController::class, 'functionalExpenses']);
                Route::middleware('permission:finance_reports.export')->group(function () {
                    Route::get('/print', [FinanceReportController::class, 'functionalExpensesPrint'])->name('.print');
                    Route::get('/pdf',   [FinanceReportController::class, 'functionalExpensesPdf'])  ->name('.pdf');
                    Route::get('/csv',   [FinanceReportController::class, 'functionalExpensesCsv'])  ->name('.csv');
                });
            });
        });

        // Categories — finance.view baseline; Store/Update FormRequests gate
        // writes on finance.edit; destroy gets finance.edit middleware (no
        // FormRequest on the destroy action).
        Route::resource('categories', FinanceCategoryController::class)
             ->except(['show', 'create', 'edit', 'destroy'])
             ->middleware('permission:finance.view');
        Route::delete('categories/{category}', [FinanceCategoryController::class, 'destroy'])
             ->middleware('permission:finance.edit')
             ->name('categories.destroy');

        // Transaction list exports — registered BEFORE the resource route so
        // /finance/transactions/export/* doesn't get parsed as Route::resource's
        // show action with {transaction}=export. Tier 2 — exports are reads
        // (finance.view); destroy + removeAttachment are deletes (finance.delete).
        Route::get('transactions/export/print', [FinanceTransactionController::class, 'exportPrint'])
             ->middleware('permission:finance.view')
             ->name('transactions.export.print');
        Route::get('transactions/export/csv',   [FinanceTransactionController::class, 'exportCsv'])
             ->middleware('permission:finance.view')
             ->name('transactions.export.csv');

        Route::resource('transactions', FinanceTransactionController::class)
             ->except(['destroy'])
             ->middleware('permission:finance.view');
        Route::delete('transactions/{transaction}', [FinanceTransactionController::class, 'destroy'])
             ->middleware('permission:finance.delete')
             ->name('transactions.destroy');

        Route::get('transactions/{transaction}/attachment', [FinanceTransactionController::class, 'downloadAttachment'])
             ->middleware('permission:finance.view')
             ->name('transactions.attachment.download');
        Route::delete('transactions/{transaction}/attachment', [FinanceTransactionController::class, 'removeAttachment'])
             ->middleware('permission:finance.delete')
             ->name('transactions.attachment.remove');
    });

    // Reports Module — gated behind reports.view (matches the seeded REPORTS role).
    // The /download endpoint additionally requires reports.export so a viewer
    // can browse but not bulk-export PII (household contact info, demographics).
    Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
        Route::get('/',             [ReportsController::class, 'overview'])    ->name('overview');
        Route::get('/events',       [ReportsController::class, 'events'])      ->name('events');
        Route::get('/trends',       [ReportsController::class, 'trends'])      ->name('trends');
        Route::get('/demographics', [ReportsController::class, 'demographics'])->name('demographics');
        Route::get('/lanes',        [ReportsController::class, 'lanes'])       ->name('lanes');
        Route::get('/queue-flow',   [ReportsController::class, 'queueFlow'])   ->name('queue-flow');
        Route::get('/volunteers',   [ReportsController::class, 'volunteers']) ->name('volunteers');
        Route::get('/reviews',      [ReportsController::class, 'reviews'])    ->name('reviews');
        Route::get('/inventory',    [ReportsController::class, 'inventory'])   ->name('inventory');
        Route::get('/first-timers', [ReportsController::class, 'firstTimers'])->name('first-timers');
        Route::get('/export',       [ReportsController::class, 'export'])     ->name('export');
        Route::get('/download',     [ReportsController::class, 'downloadExport'])
             ->middleware('permission:reports.export')
             ->name('download');
    });

    // Settings Module
    Route::prefix('settings')->name('settings.')->middleware('permission:settings.view')->group(function () {
        Route::get('/',          [SettingsController::class, 'index'])->name('index');
        Route::get('/{group}',   [SettingsController::class, 'show'])->name('show');
        Route::put('/{group}',   [SettingsController::class, 'update'])
            ->middleware('permission:settings.update')
            ->name('update');

        // Branding asset upload / delete
        Route::post('/branding/{asset}',   [SettingsController::class, 'uploadBrandingAsset'])
            ->middleware('permission:settings.update')
            ->name('branding.upload');
        Route::delete('/branding/{asset}', [SettingsController::class, 'deleteBrandingAsset'])
            ->middleware('permission:settings.update')
            ->name('branding.delete');
    });
});

// ─── /checkin/* endpoints shared by admin and public intake ──────────────────
// Admin uses these via session login (auth middleware path); public intake
// (event-day session, 4-digit code) uses the same endpoints because the
// intake page is a tablet-shaped clone of the admin /checkin UI. The
// `event-day-or-auth` middleware accepts either auth path.
Route::middleware('event-day-or-auth')->group(function () {
    Route::get('/checkin/search',                            [CheckInController::class, 'search'])             ->name('checkin.search');
    Route::get('/checkin/log',                               [CheckInController::class, 'log'])                ->name('checkin.log');
    Route::post('/checkin',                                  [CheckInController::class, 'store'])              ->name('checkin.store');
    Route::post('/checkin/quick-create',                     [CheckInController::class, 'quickCreate'])       ->name('checkin.quickCreate');
    Route::patch('/checkin/households/{household}/vehicle',  [CheckInController::class, 'updateVehicle'])     ->name('checkin.updateVehicle');
    Route::get('/checkin/represented/search',                [CheckInController::class, 'searchRepresented']) ->name('checkin.searchRepresented');
    Route::post('/checkin/represented/attach',               [CheckInController::class, 'attachRepresented']) ->name('checkin.attachRepresented');
    Route::post('/checkin/represented/create',               [CheckInController::class, 'createRepresented']) ->name('checkin.createRepresented');
});
