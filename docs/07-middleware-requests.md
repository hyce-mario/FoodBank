# Middleware & Form Requests

---

## Middleware

### `CheckPermission` (`app/Http/Middleware/CheckPermission.php`)

Attached to routes via `middleware('permission:some.permission')`.

**Logic:**
1. If user is not authenticated → redirect to login
2. Call `$user->hasPermission($permission)` (checks role's `role_permissions`)
3. If user has `*` (wildcard) permission → allow
4. If user has the exact dot-notation permission → allow
5. Otherwise → abort(403)

**Usage examples:**
```php
Route::get('/settings/{group}', ...)->middleware('permission:settings.view');
Route::put('/settings/{group}', ...)->middleware('permission:settings.update');
```

### `MaintenanceMode` (`app/Http/Middleware/MaintenanceMode.php`)

Checks `system.maintenance_mode` setting. If enabled:
- Allows through: admin users, login page, and public pages
- All other requests → renders a maintenance page

Registered in `bootstrap/app.php` middleware stack.

---

## Form Request Classes

All live in `app/Http/Requests/`. Every class extends `FormRequest`. Controllers type-hint these instead of `Request` to get automatic validation.

### Authentication
**`Auth/LoginRequest`**
- `email`: required, email
- `password`: required, string

### Households
**`StoreHouseholdRequest`**
- `first_name`, `last_name`: required, string, max:100
- `email`: nullable, email
- `phone`: nullable, string
- `city`, `state`, `zip`: required, string
- `household_size`: required, integer, min:1
- `children_count`, `adults_count`, `seniors_count`: nullable, integer, min:0
- `vehicle_make`, `vehicle_color`: nullable, string
- `notes`: nullable, string
- `represented_households`: nullable, array (sub-household data)

**`UpdateHouseholdRequest`** — same rules with `sometimes` wrapping for partial updates

### Events
**`StoreEventRequest`**
- `name`: required, string, max:150
- `date`: required, date
- `location`: nullable, string, max:255
- `lanes`: required, integer, min:1, max:10
- `ruleset_id`: nullable, exists:allocation_rulesets,id
- `volunteer_group_id`: nullable, exists:volunteer_groups,id
- `notes`: nullable, string

**`UpdateEventRequest`** — same with `sometimes`

### Volunteers
**`StoreVolunteerRequest`** / **`UpdateVolunteerRequest`**
- `first_name`, `last_name`: required, string
- `phone`, `email`: nullable
- `role`: required, in:Volunteer::ROLES
- `user_id`: nullable, exists:users,id, unique:volunteers (on store)

### Volunteer Groups
**`StoreVolunteerGroupRequest`** / **`UpdateVolunteerGroupRequest`**
- `name`: required, string, max:100, unique:volunteer_groups
- `description`: nullable, string

### Users
**`StoreUserRequest`** / **`UpdateUserRequest`**
- `name`: required, string
- `email`: required, email, unique:users
- `password`: required on store, min:8, confirmed
- `role_id`: required, exists:roles,id

### Roles
**`StoreRoleRequest`** / **`UpdateRoleRequest`**
- `name`: required, string, unique:roles, alpha_dash
- `display_name`: required, string
- `description`: nullable, string
- `permissions`: nullable, array, each in:all_permission_strings

### Inventory
**`StoreInventoryItemRequest`** / **`UpdateInventoryItemRequest`**
- `name`: required, string, max:150
- `sku`: nullable, string, unique:inventory_items
- `category_id`: nullable, exists:inventory_categories,id
- `unit_type`: required, string
- `quantity_on_hand`: required, integer, min:0
- `reorder_level`: required, integer, min:0
- `description`: nullable, string
- `is_active`: boolean

**`StoreInventoryMovementRequest`**
- `movement_type`: required, in:InventoryMovement::TYPES keys
- `quantity`: required, integer, min:1
- `notes`: nullable, string

**`StoreEventInventoryAllocationRequest`**
- `inventory_item_id`: required, exists:inventory_items,id
- `allocated_quantity`: required, integer, min:1
- `notes`: nullable, string

**`UpdateAllocationDistributedRequest`**
- `distributed_quantity`: required, integer, min:0

**`ReturnInventoryAllocationRequest`**
- `return_quantity`: required, integer, min:1

### Finance
**`StoreFinanceCategoryRequest`** / **`UpdateFinanceCategoryRequest`**
- `name`: required, string, max:100
- `type`: required, in:income,expense
- `description`: nullable, string
- `is_active`: boolean

**`StoreFinanceTransactionRequest`** / **`UpdateFinanceTransactionRequest`**
- `transaction_type`: required, in:income,expense
- `title`: required, string
- `category_id`: required, exists:finance_categories,id
- `amount`: required, numeric, min:0.01
- `transaction_date`: required, date
- `source_or_payee`: required, string
- `payment_method`: nullable, in:FinanceTransaction::PAYMENT_METHODS
- `reference_number`: nullable, string
- `event_id`: nullable, exists:events,id
- `notes`: nullable, string
- `attachment`: nullable, file, mimes:pdf,jpg,jpeg,png, max:5120
- `status`: required, in:FinanceTransaction::STATUSES

### Check-In
**`CheckInRequest`**
- `event_id`: required, exists:events,id
- `household_id`: required, exists:households,id
- `lane`: required, integer, min:1
- `represented_ids`: nullable, array of household IDs

### Reviews
**`StoreReviewRequest`**
- `event_id`: required, exists:events,id
- `rating`: required, integer, between:1,5
- `review_text`: required, string, min:[reviews.min_review_length setting]
- `reviewer_name`: nullable, string, max:100
- `email`: nullable, email
