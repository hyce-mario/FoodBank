# Service Layer

All services live in `app/Services/`. Business logic belongs here — controllers only call services and return responses.

---

## EventCheckInService

Manages the entire household check-in workflow during an event.

### `search(string $query, ?int $eventId = null): Collection`
Searches households by name, household number, phone, or QR token.
- Returns up to 10 results
- Eager-loads represented families
- Indicates whether each household is already checked in to the given event

### `checkIn(Event $event, Household $household, int $lane, ?array $representedIds = null): Visit`
Checks in a household to an event.
- **Guards:** throws `RuntimeException` if the household or any represented household is already active in the event
- Creates a `Visit` record with `visit_status = 'checked_in'`
- Assigns `queue_position` (next available in lane)
- Attaches the primary household via `visit_households`
- Optionally attaches additional represented household IDs
- Returns the `Visit` with households eager-loaded

### `markDone(Visit $visit): Visit`
Marks a visit as exited immediately (skips queue/loading steps).
- Sets `queued_at`, `loading_completed_at`, `exited_at` to now if not already set
- Sets `visit_status = 'exited'` and `end_time = now()`
- Returns updated visit

### `activeQueue(Event $event): Collection`
Returns all non-exited visits for the event, ordered by `start_time` descending.
- Eager-loads `households`

### `recentLog(Event $event): Collection`
Returns the 20 most recent visits (newest first) for the event.

---

## HouseholdService

Manages household creation, updating, QR generation, and represented-family relationships.

### `generateHouseholdNumber(): string`
Generates a unique household number using the `households.household_number_length` setting.

### `generateQrToken(): string`
Generates a unique UUID v4 for the QR token field.

### `create(array $data): Household`
Creates a primary household and optionally creates represented households inline.
- `$data` may contain a `represented_households` array of sub-household data
- Applies demographic computation (computes `household_size` from counts)
- Auto-generates `household_number` and `qr_token`
- Returns the primary Household

### `createRepresented(Household $representative, array $data): Household`
Creates a new household and immediately links it to the given representative.

### `update(Household $household, array $data): Household`
Updates a household and syncs its represented families.
- Sub-items with `id` → update existing represented household
- Sub-items with `_detach` flag → clear `representative_household_id`
- Sub-items without `id` → create new represented household

### `regenerateQrToken(Household $household): Household`
Generates and saves a new unique QR token for the household.

### `attach(Household $representative, Household $represented): void`
Sets `represented->representative_household_id` to `representative->id`.

### `detach(Household $represented): void`
Clears `represented->representative_household_id` to `null`.

---

## InventoryService

Manages all stock operations. All methods use database transactions with pessimistic locking to prevent race conditions.

### `addStock(InventoryItem $item, int $quantity, ?string $notes, ?int $userId): InventoryMovement`
Records a `stock_in` movement and increases `quantity_on_hand`.
- Throws `RuntimeException` if `$quantity <= 0`

### `removeStock(InventoryItem $item, int $quantity, string $type, ?string $notes, ?int $userId): InventoryMovement`
Records a removal movement (`stock_out`, `damaged`, `expired`) and decreases `quantity_on_hand`.
- Validates that `$type` is in `InventoryMovement::OUTBOUND`
- Throws `RuntimeException` if insufficient stock (unless `allow_negative_stock` setting is enabled)

### `adjustStock(InventoryItem $item, int $targetQty, ?string $notes, ?int $userId): InventoryMovement`
Sets stock to an exact target value.
- Calculates delta: `targetQty - quantity_on_hand`
- Records an `adjustment` movement with the signed delta

### `allocateToEvent(InventoryItem $item, Event $event, int $quantity, ?string $notes, ?int $userId): InventoryMovement`
Records `event_allocated` movement and decreases stock.

### `returnFromEvent(InventoryItem $item, Event $event, int $quantity, ?string $notes, ?int $userId): InventoryMovement`
Records `event_returned` movement and increases stock.

---

## RolePermissionService

Manages roles, permissions, and RBAC configuration.

### `permissionGroups(): array`
Returns a structured array of all available permissions grouped by module:
```php
[
    'households' => ['view', 'create', 'edit', 'delete'],
    'checkin'    => ['view', 'scan'],
    'events'     => ['view', 'create', 'edit', 'delete'],
    'inventory'  => ['view', 'create', 'edit', 'delete'],
    'finance'    => ['view', 'create', 'edit', 'delete'],
    'volunteers' => ['view', 'create', 'edit', 'delete'],
    'reports'    => ['view'],
    'settings'   => ['view', 'update'],
    'users'      => ['view', 'create', 'edit', 'delete'],
    'roles'      => ['view', 'create', 'edit', 'delete'],
    'reviews'    => ['view', 'moderate'],
]
```

### `allPermissions(): array`
Flat array of all permission strings in `module.action` format.

### `create(array $data): Role`
Creates a role and assigns its permissions in a single transaction.
- `$data` keys: `name`, `display_name`, `description`, `permissions[]`

### `update(Role $role, array $data): Role`
Updates role details and syncs permissions. Does not change the `name` slug.

### `delete(Role $role): void`
Deletes a role if:
- It is not `ADMIN`
- It has no assigned users

Throws `RuntimeException` otherwise.

### `syncPermissions(Role $role, array $permissions): void`
Replaces all permissions for the role. Passing `['*']` grants full access wildcard.

---

## SettingService

Provides cached read/write access to the `app_settings` table. Caches the full settings table in-request.

### `get(string $key, mixed $default = null): mixed`
Retrieves a single setting by full key (e.g. `'general.app_name'`).
- Falls back to definition default, then `$default`
- Applies type casting via `casted_value` accessor

### `group(string $group): array`
Returns all settings for a group as a keyed array (short keys, without group prefix).
- Merges with definition defaults so un-set keys always have a value

### `updateGroup(string $group, array $data): void`
Persists all settings for a group.
- Only saves keys present in the definitions
- Boolean fields absent from `$data` are stored as `false` (hidden-input pattern for checkboxes)
- Calls `flush()` after saving

### `set(string $key, mixed $value): void`
Persists a single setting by full key.

### `flush(): void`
Clears the in-request settings cache, forcing the next `get()` to reload from DB.

### `formatCurrency(float $amount, ?int $decimals = null): string`
Formats an amount using `finance.currency_symbol` and `finance.decimal_precision` settings.

### Settings Groups & Keys

| Group | Keys |
|-------|------|
| general | app_name, timezone, date_format, time_format, currency, records_per_page, dashboard_default_event |
| organization | name, email, phone, website, address, about |
| branding | primary_color, secondary_color, accent_color, sidebar_bg, nav_text_color, logo_display, appearance, logo_path, favicon_path |
| event_queue | default_lane_count, allow_lane_drag, allow_queue_reorder, queue_poll_interval, show_household_names_scanner, show_vehicle_info_queue, show_family_breakdown, bag_calculation_strategy, default_bags_per_person |
| public_access | allow_code_regeneration, require_event_date_validation, invalidate_on_completion, session_timeout_minutes, auto_generate_codes, one_code_per_role |
| households | household_number_length, auto_generate_household_number, require_phone, require_address, require_vehicle_info, enable_represented_families, max_represented_families, warn_duplicate_email, warn_duplicate_phone |
| reviews | enable_reviews, allow_anonymous, email_optional, require_moderation, default_visibility, restrict_to_recent_events, thankyou_message, min_review_length, max_review_length, show_average_rating |
| inventory | low_stock_threshold, allow_negative_stock, require_movement_notes, show_inactive_items, enable_event_allocations, dashboard_low_stock_alert, out_of_stock_behavior |
| finance | currency_symbol, decimal_precision, allow_attachments, allowed_attachment_types, require_category, enable_event_metrics, default_date_range, allow_draft_expenses |
| notifications | sender_email, sender_name, reply_to_email, support_email, public_contact_email, notify_admin_on_review, notify_low_stock, enable_event_day_alerts |
| security | session_timeout_minutes, password_min_length, require_strong_password, allow_self_delete, allow_user_deactivation, default_new_user_role, audit_logging_enabled, protect_system_roles, role_deletion_protection |
| system | maintenance_mode, default_pagination_limit, chart_default_period, report_export_format, soft_delete_enabled, archive_completed_events_after_days, show_debug_to_admin |

---

## FinanceService

Aggregates financial data for dashboards and reports.

### `dashboardKpis(): array`
Returns:
```php
[
    'total_income'        => float,
    'total_expenses'      => float,
    'net_balance'         => float,
    'month_income'        => float,
    'month_expenses'      => float,
    'top_expense_category'=> string|null,
    'event_linked_spend'  => float,
]
```

### `monthlyTrend(int $months = 12): array`
Returns chart data for the last N months:
```php
['labels' => [...], 'income' => [...], 'expense' => [...]]
```

### `expenseByCategory(): array`
Returns category breakdown:
```php
['labels' => [...], 'totals' => [...]]
```

### `incomeBySource(): Collection`
Top 10 income sources: `{source_or_payee, total, count}`

### `eventFinanceSummary(): Collection`
Top 10 events by transaction count: `{event_id, total_income, total_expense, transaction_count, event}`

### `eventKpis(int $eventId): array`
```php
['income' => float, 'expenses' => float, 'net' => float]
```

---

## Other Services

### `VolunteerGroupService`
Manages volunteer group operations and membership syncing.

### `ReportAnalyticsService`
Aggregates cross-module data for the reports module (event counts, household demographics, lane metrics, review summaries).

### `EventAnalyticsService`
Per-event metrics: attendance counts, average queue times, bag distributions.

### `InventoryReportService`
Inventory analytics: turnover rates, low-stock history, allocation vs distribution comparisons.
