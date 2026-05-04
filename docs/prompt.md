# Food Bank Management System — Rebuild Prompt

This document is a self-contained instruction set for an AI agent (Claude or similar) to rebuild the Food Bank Management System from scratch in a clean Laravel project. Follow every section in order.

---

## Project Summary

Build a full-featured food bank operations management system.

**Stack:**
- Laravel 11, PHP ^8.2
- MySQL database
- Blade templates (no Vue/React/Alpine)
- Tailwind CSS v3 + Vite
- Plain JavaScript (fetch API for AJAX, no frameworks)

**Scope:** Household registry, event management with multi-lane check-in, volunteer management, inventory tracking, finance ledger, reports/analytics, role-based access control, and public-facing pages (event registration, reviews).

---

## Phase 1: Laravel Project Setup

### 1.1 Create the project

```bash
composer create-project laravel/laravel foodbank
cd foodbank
```

### 1.2 Install and configure Tailwind CSS

```bash
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p
npm install
```

**`tailwind.config.js`:**
```js
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    safelist: [
        // Role badge colors
        'bg-blue-100', 'text-blue-800',
        'bg-purple-100', 'text-purple-800',
        'bg-orange-100', 'text-orange-800',
        'bg-green-100', 'text-green-800',
        'bg-red-100', 'text-red-800',
        'bg-yellow-100', 'text-yellow-800',
        'bg-gray-100', 'text-gray-800',
    ],
    theme: {
        extend: {
            colors: {
                brand: { DEFAULT: '#f97316', dark: '#ea580c' },
                navy:  { DEFAULT: '#1e3a5f', light: '#2d5282' },
            },
            gridTemplateColumns: {
                settings: '220px 1fr',
            },
        },
    },
    plugins: [],
}
```

**`resources/css/app.css`:**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer components {
    .btn-primary   { @apply bg-brand text-white px-4 py-2 rounded-lg font-medium hover:bg-brand-dark transition; }
    .btn-secondary { @apply bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-50 transition; }
    .btn-danger    { @apply bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition; }
    .card          { @apply bg-white rounded-xl border border-gray-200 shadow-sm; }
    .form-input    { @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand; }
    .form-label    { @apply block text-sm font-medium text-gray-700 mb-1; }
    .form-error    { @apply text-red-600 text-xs mt-1; }
    .badge         { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium; }
}
```

### 1.3 Configure `.env`

```
APP_NAME="FoodBank"
APP_ENV=local
APP_KEY=   (generate with: php artisan key:generate)
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=foodbank
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

---

## Phase 2: Database Migrations

Create migrations in this order. Run `php artisan make:migration create_X_table` for each.

### 2.1 Core tables (in dependency order)

**`create_roles_table`**
```php
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('display_name');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**Add `role_id` to `users` table** (modify existing migration or create new):
```php
$table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
```

**`create_role_permissions_table`**
```php
Schema::create('role_permissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->string('permission');
    $table->timestamps();
});
```

**`create_households_table`**
```php
Schema::create('households', function (Blueprint $table) {
    $table->id();
    $table->string('household_number', 5)->unique();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->string('city');
    $table->char('state', 2);
    $table->string('zip');
    $table->string('vehicle_make')->nullable();
    $table->string('vehicle_color')->nullable();
    $table->smallInteger('household_size')->default(1);
    $table->tinyInteger('children_count')->default(0);
    $table->tinyInteger('adults_count')->default(1);
    $table->tinyInteger('seniors_count')->default(0);
    $table->tinyInteger('number_of_families')->default(1);
    $table->json('family_breakdown')->nullable();
    $table->foreignId('representative_household_id')->nullable()->constrained('households')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->string('qr_token', 64)->unique();
    $table->timestamps();
    $table->index(['first_name', 'last_name']);
    $table->index('zip');
    $table->index('household_size');
});
```

**`create_volunteer_groups_table`**
```php
Schema::create('volunteer_groups', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**`create_allocation_rulesets_table`**
```php
Schema::create('allocation_rulesets', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('allocation_type')->nullable();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->smallInteger('max_household_size')->default(20);
    $table->json('rules');
    $table->timestamps();
});
```

**`create_events_table`**
```php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->string('name', 150);
    $table->date('date');
    $table->enum('status', ['upcoming', 'current', 'past'])->default('upcoming');
    $table->string('location', 255)->nullable();
    $table->tinyInteger('lanes')->default(1);
    $table->foreignId('ruleset_id')->nullable()->constrained('allocation_rulesets')->nullOnDelete();
    $table->foreignId('volunteer_group_id')->nullable()->constrained('volunteer_groups')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->string('intake_auth_code', 4)->nullable();
    $table->string('scanner_auth_code', 4)->nullable();
    $table->string('loader_auth_code', 4)->nullable();
    $table->string('exit_auth_code', 4)->nullable();
    $table->timestamps();
    $table->index('date');
});
```

**`create_visits_table`**
```php
Schema::create('visits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->tinyInteger('lane')->default(1);
    $table->integer('queue_position')->default(0);
    $table->enum('visit_status', ['checked_in', 'queued', 'loading', 'loaded', 'exited'])->default('checked_in');
    $table->dateTime('start_time');
    $table->dateTime('end_time')->nullable();
    $table->dateTime('queued_at')->nullable();
    $table->dateTime('loading_completed_at')->nullable();
    $table->dateTime('exited_at')->nullable();
    $table->smallInteger('served_bags')->default(0);
    $table->timestamps();
});
```

**`create_visit_households_table`**
```php
Schema::create('visit_households', function (Blueprint $table) {
    $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
    $table->foreignId('household_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->primary(['visit_id', 'household_id']);
});
```

**`create_volunteers_table`**
```php
Schema::create('volunteers', function (Blueprint $table) {
    $table->id();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->string('role', 50);
    $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
    $table->timestamps();
    $table->index(['last_name', 'first_name']);
});
```

**`create_volunteer_group_memberships_table`**
```php
Schema::create('volunteer_group_memberships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('volunteer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('group_id')->constrained('volunteer_groups')->cascadeOnDelete();
    $table->timestamp('joined_at');
    $table->timestamps();
    $table->unique(['volunteer_id', 'group_id']);
});
```

**`create_event_pre_registrations_table`**
```php
Schema::create('event_pre_registrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('attendee_number', 5);
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email', 255);
    $table->string('city')->nullable();
    $table->string('state')->nullable();
    $table->string('zipcode')->nullable();
    $table->smallInteger('household_size')->default(1);
    $table->integer('children_count')->default(0);
    $table->integer('adults_count')->default(1);
    $table->integer('seniors_count')->default(0);
    $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('potential_household_id')->nullable()->constrained('households')->nullOnDelete();
    $table->enum('match_status', ['unmatched', 'matched', 'needs_review'])->nullable();
    $table->timestamps();
    $table->index('event_id');
    $table->index('email');
});
```

**`create_event_media_table`**
```php
Schema::create('event_media', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('disk')->default('public');
    $table->string('path');
    $table->string('name');
    $table->string('mime_type', 100);
    $table->bigInteger('size')->default(0);
    $table->enum('type', ['image', 'video'])->default('image');
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

**`create_event_reviews_table`**
```php
Schema::create('event_reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->tinyInteger('rating');
    $table->text('review_text');
    $table->string('reviewer_name', 100)->nullable();
    $table->string('email', 255)->nullable();
    $table->boolean('is_visible')->default(true);
    $table->timestamps();
    $table->index(['event_id', 'is_visible']);
    $table->index('rating');
});
```

**`create_inventory_categories_table`**
```php
Schema::create('inventory_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**`create_inventory_items_table`**
```php
Schema::create('inventory_items', function (Blueprint $table) {
    $table->id();
    $table->string('name', 150);
    $table->string('sku', 100)->unique()->nullable();
    $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
    $table->string('unit_type', 50);
    $table->unsignedInteger('quantity_on_hand')->default(0);
    $table->unsignedInteger('reorder_level')->default(0);
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**`create_inventory_movements_table`**
```php
Schema::create('inventory_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
    $table->string('movement_type', 30);
    $table->integer('quantity');   // signed: positive = in, negative = out
    $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamp('created_at')->useCurrent();
    // No updated_at — movements are immutable
});
```

**`create_event_inventory_allocations_table`**
```php
Schema::create('event_inventory_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('allocated_quantity');
    $table->unsignedInteger('distributed_quantity')->default(0);
    $table->unsignedInteger('returned_quantity')->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**`create_finance_categories_table`**
```php
Schema::create('finance_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->enum('type', ['income', 'expense']);
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**`create_finance_transactions_table`**
```php
Schema::create('finance_transactions', function (Blueprint $table) {
    $table->id();
    $table->enum('transaction_type', ['income', 'expense']);
    $table->string('title');
    $table->foreignId('category_id')->constrained('finance_categories')->restrictOnDelete();
    $table->decimal('amount', 10, 2);
    $table->date('transaction_date');
    $table->string('source_or_payee');
    $table->string('payment_method', 50)->nullable();
    $table->string('reference_number', 100)->nullable();
    $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
    $table->text('notes')->nullable();
    $table->string('attachment_path')->nullable();
    $table->string('status', 20)->default('completed');
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

**`create_app_settings_table`**
```php
Schema::create('app_settings', function (Blueprint $table) {
    $table->id();
    $table->string('group', 64);
    $table->string('key', 128)->unique();
    $table->text('value')->nullable();
    $table->string('type', 32)->default('string');
    $table->timestamps();
    $table->index('group');
});
```

**`create_sessions_table`** — run `php artisan session:table`

---

## Phase 3: Eloquent Models

Create each model with `php artisan make:model ModelName`. Implement exactly as documented in `docs/03-models.md`.

**Models to create:**
- User (modify existing)
- Role
- RolePermission
- Household
- Volunteer
- VolunteerGroup
- VolunteerGroupMembership
- Event
- EventPreRegistration
- Visit
- EventMedia
- EventReview
- EventInventoryAllocation
- AllocationRuleset
- InventoryCategory
- InventoryItem
- InventoryMovement
- FinanceCategory
- FinanceTransaction
- AppSetting

**Critical implementation notes:**
1. `Household` has a self-referential relationship (`representative_household_id → id`)
2. `Visit` has a `BelongsToMany` to `Household` via `visit_households` pivot
3. `InventoryMovement` has `public $timestamps = false;` and manually defines `created_at` only
4. `Event::boot()` auto-generates auth codes on `creating` if the `auto_generate_codes` setting is enabled
5. All status/badge helper methods belong on the model, not in views or controllers

---

## Phase 4: Service Classes

Create each service in `app/Services/`. See `docs/05-services.md` for full method signatures.

**Services to create:**
1. `HouseholdService` — CRUD + QR + represented families
2. `EventCheckInService` — search, checkIn, markDone, activeQueue, recentLog
3. `InventoryService` — addStock, removeStock, adjustStock, allocateToEvent, returnFromEvent
4. `RolePermissionService` — permission groups, create/update/delete role, syncPermissions
5. `SettingService` — get, group, updateGroup, set, flush, formatCurrency, definitions (all 98 settings)
6. `FinanceService` — dashboardKpis, monthlyTrend, expenseByCategory, incomeBySource, eventFinanceSummary
7. `VolunteerGroupService` — group operations + membership sync
8. `ReportAnalyticsService` — cross-module aggregations
9. `EventAnalyticsService` — per-event metrics
10. `InventoryReportService` — inventory analytics

**Critical implementation notes:**
1. `InventoryService` methods must use `DB::transaction()` with `lockForUpdate()` to prevent race conditions
2. `SettingService` must cache the full settings table in a private property and clear it via `flush()` after writes
3. `HouseholdService::create()` accepts a `represented_households` array in `$data` for inline creation of represented families
4. `EventCheckInService::checkIn()` must guard against duplicate check-ins (household already active in event)

---

## Phase 5: Middleware

### `CheckPermission` middleware
```php
// app/Http/Middleware/CheckPermission.php
public function handle(Request $request, Closure $next, string $permission): Response
{
    if (!auth()->check()) return redirect('/login');
    if (!auth()->user()->hasPermission($permission)) abort(403);
    return $next($request);
}
```

Register alias in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['permission' => CheckPermission::class]);
})
```

### `MaintenanceMode` middleware
Check `app(SettingService::class)->get('system.maintenance_mode', false)`. Allow through: admin users, login routes, public routes. Others → show maintenance view.

---

## Phase 6: Form Request Classes

Create with `php artisan make:request RequestName`. See `docs/07-middleware-requests.md` for all validation rules.

**Requests to create (30+):**
- Auth/LoginRequest
- StoreHouseholdRequest, UpdateHouseholdRequest
- StoreEventRequest, UpdateEventRequest
- StoreVolunteerRequest, UpdateVolunteerRequest
- StoreVolunteerGroupRequest, UpdateVolunteerGroupRequest
- StoreUserRequest, UpdateUserRequest
- StoreRoleRequest, UpdateRoleRequest
- StoreInventoryItemRequest, UpdateInventoryItemRequest
- StoreInventoryMovementRequest
- StoreEventInventoryAllocationRequest
- UpdateAllocationDistributedRequest
- ReturnInventoryAllocationRequest
- StoreFinanceCategoryRequest, UpdateFinanceCategoryRequest
- StoreFinanceTransactionRequest, UpdateFinanceTransactionRequest
- CheckInRequest
- StoreReviewRequest

---

## Phase 7: Controllers

Create each with `php artisan make:controller ControllerName`. See `docs/04-controllers.md` for all methods.

**Controllers to create (28):**
- LoginController
- DashboardController
- HouseholdController
- EventController
- CheckInController
- EventDayController
- VisitMonitorController
- VisitLogController
- VolunteerController
- VolunteerGroupController
- UserController
- RoleController
- InventoryCategoryController
- InventoryItemController
- InventoryMovementController
- EventInventoryAllocationController
- EventMediaController
- FinanceController
- FinanceCategoryController
- FinanceTransactionController
- AllocationRulesetController
- ReportsController
- SettingsController
- ReviewController
- PublicReviewController
- PublicEventController
- ProfileController

---

## Phase 8: Routes

Define all routes in `routes/web.php` as documented in `docs/06-routes.md`.

**Key structural points:**
- Guest routes (login): outside all middleware groups
- Event-day routes: no `auth` middleware, uses session-based event code auth
- Public routes (/register, /review): no auth
- All admin routes: inside `middleware('auth')` group
- Settings routes: additionally wrapped in `middleware('permission:settings.view')`

---

## Phase 9: Blade Layout & Views

### 9.1 Main Layout (`resources/views/layouts/app.blade.php`)

Build a full-page layout with:
- Left sidebar: logo/app name, nav links (with icons), user info at bottom
- Top bar: page title, user menu
- Main content: `@yield('content')`
- Scripts stack: `@stack('scripts')`
- Dynamic theming: inject branding settings as CSS custom properties in `<head>`

### 9.2 Blade Components (in `resources/views/components/`)
Create: `stat-card.blade.php`, `flash-message.blade.php`, `form-field.blade.php`, `badge.blade.php`, `pagination.blade.php`

### 9.3 Views by module
Create all views as listed in `docs/08-frontend-views.md`.

**Most complex views to implement carefully:**
1. **`checkin/index.blade.php`** — real-time check-in with household search, QR input, inline create, queue display, JS polling
2. **`event-day/index.blade.php`** — minimal tablet-friendly layout with auth code gate
3. **`events/show.blade.php`** — tabbed view covering: details, inventory allocations, media gallery, reviews, pre-registrations
4. **`finance/dashboard.blade.php`** — Chart.js charts for monthly trend and category breakdown
5. **`settings/show.blade.php`** — dynamic form that renders the right input type for each setting key

---

## Phase 10: Seeders

Create all seeders documented in `docs/09-seeders-commands.md`.

`DatabaseSeeder.php` call order:
1. RoleSeeder
2. AdminUserSeeder
3. VolunteerSeeder
4. DemoSeeder
5. InventoryCategorySeeder
6. InventoryItemSeeder
7. FinanceCategorySeeder
8. FinanceTransactionSeeder
9. SettingsSeeder

**SettingsSeeder** must insert all 98 settings from `SettingService::definitions()` using `upsert()`.

---

## Phase 11: Console Command

Create `app/Console/Commands/SyncEventStatuses.php` and register it in `routes/console.php`:
```php
Schedule::command('events:sync-statuses')->dailyAt('00:01');
```

---

## Phase 12: AppServiceProvider

Register middleware aliases and any global bindings in `app/Providers/AppServiceProvider.php`:
```php
public function boot(): void
{
    // Register SettingService as singleton so cache persists within a request
    $this->app->singleton(SettingService::class);
}
```

---

## Phase 13: Final Configuration

### `bootstrap/app.php`
Register `MaintenanceMode` middleware in the web middleware group.

### `config/filesystems.php`
Ensure `public` disk is configured and `php artisan storage:link` is run.

---

## Build Checklist

### Foundation
- [ ] Laravel project created and configured
- [ ] MySQL database created and `.env` configured
- [ ] Tailwind CSS installed and configured
- [ ] All migrations created and run: `php artisan migrate`

### Data Layer
- [ ] All 20 models created with relationships, casts, scopes, and helper methods
- [ ] All 10 service classes created
- [ ] All 9 seeders created and tested: `php artisan db:seed`

### HTTP Layer
- [ ] All 2 middleware classes created and registered
- [ ] All 30+ FormRequest classes created
- [ ] All 28 controllers created with correct method signatures
- [ ] All routes defined in `routes/web.php`

### Frontend
- [ ] Main layout built with responsive sidebar
- [ ] All Blade components created
- [ ] All 60+ view files created
- [ ] Check-in UI working with JS polling
- [ ] Event-day pages working with auth-code gate
- [ ] Finance dashboard charts working (Chart.js)

### Operations
- [ ] `events:sync-statuses` command created and scheduled
- [ ] All 12 settings groups accessible via `/settings/{group}`
- [ ] `php artisan storage:link` run for public file access

### Testing
- [ ] Login with seeded admin account works
- [ ] Create a household, check it in to an event
- [ ] Event-day page accessible with auth code
- [ ] Inventory movement records stock change
- [ ] Finance transaction saves and appears on dashboard
- [ ] Settings save and reflect in UI

---

## Key Constraints & Gotchas

1. **No Livewire or Vue** — all interactivity is Blade + vanilla JS fetch()
2. **Inventory movements are immutable** — no `updated_at`, quantity adjustments create new records
3. **Settings must use SettingService** — never query `app_settings` directly in controllers or views
4. **Representatives**: a household can "represent" multiple others (one-to-many self-join on households table)
5. **Event auth codes are separate from user auth** — store validated state in `session('event_day_auth_intake_123')`
6. **ADMIN role cannot be deleted** — guard this in `RolePermissionService::delete()`
7. **Finance category delete is RESTRICT** — if transactions exist, refuse deletion
8. **Check-in guards duplicates** — call `EventCheckInService::checkIn()` which throws if household is already active

---

## Reference Documentation

All detailed documentation is in the `docs/` folder:
- `01-overview.md` — project overview and architecture decisions
- `02-database-schema.md` — complete schema with column types and indexes
- `03-models.md` — all Eloquent models with relationships, scopes, methods
- `04-controllers.md` — all controllers with HTTP methods and route mapping
- `05-services.md` — all service classes with method signatures
- `06-routes.md` — complete route list
- `07-middleware-requests.md` — middleware logic and all validation rules
- `08-frontend-views.md` — view structure and JS interaction patterns
- `09-seeders-commands.md` — seeders and scheduled commands
- `10-rbac.md` — RBAC implementation details
- `11-settings-module.md` — settings groups and all 98 default values
