<?php

use App\Http\Controllers\AllocationRulesetController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\FinanceCategoryController;
use App\Http\Controllers\FinanceController;
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
    Route::resource('households', HouseholdController::class);
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

    // Event Inventory Allocations
    Route::post('events/{event}/inventory',                                      [EventInventoryAllocationController::class, 'store'])              ->name('events.inventory.store');
    // Phase D — atomic bulk allocation drawer
    Route::post('events/{event}/inventory/bulk',                                 [EventInventoryAllocationController::class, 'bulkStore'])          ->name('events.inventory.bulk');
    Route::patch('events/{event}/inventory/{allocation}/distributed',            [EventInventoryAllocationController::class, 'updateDistributed'])   ->name('events.inventory.distributed');
    Route::post('events/{event}/inventory/{allocation}/return',                  [EventInventoryAllocationController::class, 'returnStock'])         ->name('events.inventory.return');
    Route::delete('events/{event}/inventory/{allocation}',                       [EventInventoryAllocationController::class, 'destroy'])             ->name('events.inventory.destroy');
    Route::post('events/{event}/media',          [EventMediaController::class, 'store'])  ->name('events.media.store');
    Route::delete('events/{event}/media/{media}',[EventMediaController::class, 'destroy'])->name('events.media.destroy');

    // Check-in — admin-only routes (the page itself and admin-shaped actions)
    Route::get('/checkin',                  [CheckInController::class, 'index'])    ->name('checkin.index');
    Route::get('/checkin/queue',            [CheckInController::class, 'queue'])    ->name('checkin.queue');
    Route::post('/checkin/quick-add',       [CheckInController::class, 'quickAdd']) ->name('checkin.quickAdd');
    Route::patch('/checkin/{visit}/done',   [CheckInController::class, 'done'])     ->name('checkin.done');

    // Volunteers
    Route::resource('volunteers', VolunteerController::class);

    // Inventory — Categories
    Route::resource('inventory/categories', InventoryCategoryController::class)
         ->names('inventory.categories')
         ->parameters(['categories' => 'inventoryCategory'])
         ->only(['index', 'store', 'update', 'destroy']);

    // Inventory — Items
    Route::resource('inventory/items', InventoryItemController::class)
         ->names('inventory.items')
         ->parameters(['items' => 'inventory_item']);

    // Inventory — Quick-create JSON endpoint (used by Purchase Order line-item
    // picker to create + select an item without leaving the form).
    Route::post('inventory/items/quick-create', [InventoryItemController::class, 'quickStore'])
         ->name('inventory.items.quick-create');

    // Inventory — Movements (manual stock operations from item show page)
    Route::post('inventory/items/{inventory_item}/movements', [InventoryMovementController::class, 'store'])
         ->name('inventory.movements.store');

    // Phase 6.6 — Purchase Orders (Inventory ↔ Finance bridge)
    Route::resource('purchase-orders', PurchaseOrderController::class)
         ->parameters(['purchase-orders' => 'purchaseOrder'])
         ->except(['edit', 'update', 'destroy']);
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'markReceived'])
         ->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
         ->name('purchase-orders.cancel');
    Route::get('purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])
         ->name('purchase-orders.print');

    // Allocation Rulesets
    Route::resource('allocation-rulesets', AllocationRulesetController::class)->except(['show', 'create', 'edit']);
    Route::get('allocation-rulesets/{allocation_ruleset}/preview', [AllocationRulesetController::class, 'preview'])
        ->name('allocation-rulesets.preview');

    // Users
    Route::resource('users', UserController::class);

    // Roles & Permissions
    Route::resource('roles', RoleController::class);

    // Volunteer Groups
    Route::resource('volunteer-groups', VolunteerGroupController::class);
    Route::get('volunteer-groups/{volunteer_group}/members', [VolunteerGroupController::class, 'editMembers'])
        ->name('volunteer-groups.members.edit');
    Route::post('volunteer-groups/{volunteer_group}/members', [VolunteerGroupController::class, 'updateMembers'])
        ->name('volunteer-groups.members.update');

    // Audit Log (admin-only, read-only)
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Event Reviews (admin)
    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}/toggle-visibility', [ReviewController::class, 'toggleVisibility'])
        ->name('reviews.toggle-visibility');

    // Visit Monitor
    Route::get('monitor',                                     [VisitMonitorController::class, 'index'])      ->name('monitor.index');
    Route::get('monitor/{event}/data',                        [VisitMonitorController::class, 'data'])       ->name('monitor.data');
    Route::post('monitor/{event}/reorder',                    [VisitMonitorController::class, 'reorder'])    ->name('monitor.reorder');
    Route::patch('monitor/{event}/visits/{visit}/transition', [VisitMonitorController::class, 'transition']) ->name('monitor.transition');

    // Visit Log / Event Operations Report
    Route::get('visit-log',        [VisitLogController::class, 'index'])  ->name('visit-log.index');
    Route::get('visit-log/export', [VisitLogController::class, 'export']) ->name('visit-log.export');

    // Finance Module
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/',        [FinanceController::class, 'dashboard'])->name('dashboard');
        Route::get('/reports', [FinanceController::class, 'reports'])  ->name('reports');

        Route::resource('categories', FinanceCategoryController::class)
             ->except(['show', 'create', 'edit']);

        Route::resource('transactions', FinanceTransactionController::class);
        Route::get('transactions/{transaction}/attachment',    [FinanceTransactionController::class, 'downloadAttachment'])->name('transactions.attachment.download');
        Route::delete('transactions/{transaction}/attachment', [FinanceTransactionController::class, 'removeAttachment'])  ->name('transactions.attachment.remove');
    });

    // Reports Module
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',            [ReportsController::class, 'overview'])   ->name('overview');
        Route::get('/events',      [ReportsController::class, 'events'])     ->name('events');
        Route::get('/trends',      [ReportsController::class, 'trends'])     ->name('trends');
        Route::get('/demographics',[ReportsController::class, 'demographics'])->name('demographics');
        Route::get('/lanes',       [ReportsController::class, 'lanes'])      ->name('lanes');
        Route::get('/queue-flow',  [ReportsController::class, 'queueFlow'])  ->name('queue-flow');
        Route::get('/volunteers',  [ReportsController::class, 'volunteers']) ->name('volunteers');
        Route::get('/reviews',     [ReportsController::class, 'reviews'])    ->name('reviews');
        Route::get('/inventory',    [ReportsController::class, 'inventory'])   ->name('inventory');
        Route::get('/first-timers', [ReportsController::class, 'firstTimers'])->name('first-timers');
        Route::get('/export',       [ReportsController::class, 'export'])     ->name('export');
        Route::get('/download',     [ReportsController::class, 'downloadExport'])->name('download');
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
