# Eloquent Models

All models live in `app/Models/`. All extend `Illuminate\Database\Eloquent\Model` unless noted.

---

## User (`app/Models/User.php`)

Extends `Authenticatable`.

**Fillable:** name, email, password, role_id  
**Hidden:** password, remember_token  
**Casts:** email_verified_at → datetime, password → hashed  

**Relationships:**
- `role(): BelongsTo` → Role

**Methods:**
- `hasPermission(string $permission): bool` — checks if user's role has the given dot-notation permission or a wildcard `*`
- `isAdmin(): bool` — role name equals 'ADMIN'

---

## Role (`app/Models/Role.php`)

**Fillable:** name, display_name, description  

**Relationships:**
- `permissions(): HasMany` → RolePermission
- `users(): HasMany` → User

---

## RolePermission (`app/Models/RolePermission.php`)

**Fillable:** role_id, permission  

**Relationships:**
- `role(): BelongsTo` → Role

---

## Household (`app/Models/Household.php`)

**Fillable:** household_number, first_name, last_name, email, phone, city, state, zip, vehicle_make, vehicle_color, household_size, children_count, adults_count, seniors_count, representative_household_id, notes, qr_token  
**Casts:** household_size, children_count, adults_count, seniors_count, representative_household_id → integer  

**Accessors (get-only):**
- `full_name` — `"{first_name} {last_name}"`
- `location` — `"{city}, {state}"`
- `vehicle_label` — `"{vehicle_color} {vehicle_make}"` or null if both empty
- `is_represented` — boolean, `representative_household_id` is set
- `is_representative` — boolean, has at least one `representedHouseholds` record

**Scopes:**
- `search(string $term)` — LIKE match on first_name, last_name, household_number, phone, email

**Relationships:**
- `visits(): BelongsToMany` → Visit via `visit_households`
- `representative(): BelongsTo` → Household (self-referential, foreign: representative_household_id)
- `representedHouseholds(): HasMany` → Household (foreign: representative_household_id)

---

## Volunteer (`app/Models/Volunteer.php`)

**Fillable:** first_name, last_name, phone, email, role, user_id  

**Constants:**
```php
ROLES = ['Driver', 'Loader', 'Intake', 'Scanner', 'Coordinator', 'Other']
```

**Accessors:**
- `full_name` — `"{first_name} {last_name}"`

**Scopes:**
- `search(string $term)` — match name, email, phone

**Relationships:**
- `groups(): BelongsToMany` → VolunteerGroup via `volunteer_group_memberships` (with pivot: joined_at)
- `user(): BelongsTo` → User (nullable)

---

## VolunteerGroup (`app/Models/VolunteerGroup.php`)

**Fillable:** name, description  

**Scopes:**
- `search(string $term)` — match name or description

**Relationships:**
- `volunteers(): BelongsToMany` → Volunteer via `volunteer_group_memberships`

---

## VolunteerGroupMembership (`app/Models/VolunteerGroupMembership.php`)

**Fillable:** volunteer_id, group_id, joined_at  
**Casts:** joined_at → datetime  

**Relationships:**
- `volunteer(): BelongsTo` → Volunteer
- `group(): BelongsTo` → VolunteerGroup (foreign key: group_id)

---

## Event (`app/Models/Event.php`)

**Fillable:** name, date, status, location, lanes, ruleset_id, volunteer_group_id, notes, intake_auth_code, scanner_auth_code, loader_auth_code, exit_auth_code  
**Casts:** date → date, lanes → integer, ruleset_id → integer  

**Boot logic:** On `creating`, if `auto_generate_codes` setting is enabled, generates all four auth codes automatically.

**Status Constants:** `'upcoming'`, `'current'`, `'past'`

**Methods:**
- `generateAuthCode(int $length = 4): string` — generates a random numeric code of given length
- `regenerateAuthCodes(): void` — regenerates all four codes and saves
- `authCodeFor(string $role): ?string` — returns the code for `intake`/`scanner`/`loader`/`exit`
- `authCodesActive(): bool` — returns true only when status is `current`
- `deriveStatus(Carbon $date): string` — returns the correct status string based on today vs event date
- `isLocked(): bool` — returns true if status is `past`
- `statusLabel(): string` — human-readable label
- `statusBadgeClasses(): string` — Tailwind CSS classes for the status badge

**Scopes:**
- `search(string $term)` — match name or location
- `upcoming()`, `current()`, `past()` — filter by status value

**Relationships:**
- `volunteerGroup(): BelongsTo` → VolunteerGroup (nullable)
- `ruleset(): BelongsTo` → AllocationRuleset (foreign: ruleset_id, nullable)
- `assignedVolunteers(): BelongsToMany` → Volunteer
- `preRegistrations(): HasMany` → EventPreRegistration
- `visits(): HasMany` → Visit
- `media(): HasMany` → EventMedia (ordered by sort_order, then id)
- `reviews(): HasMany` → EventReview (ordered latest first)
- `inventoryAllocations(): HasMany` → EventInventoryAllocation

---

## EventPreRegistration (`app/Models/EventPreRegistration.php`)

**Fillable:** event_id, attendee_number, first_name, last_name, email, city, state, zipcode, household_size, children_count, adults_count, seniors_count, household_id, potential_household_id, match_status  
**Casts:** household_size, children_count, adults_count, seniors_count → integer  

**Accessors:**
- `full_name` — `"{first_name} {last_name}"`

**Methods:**
- `generateAttendeeNumber(): string` — generates unique 5-char attendee number scoped to the event

**Relationships:**
- `event(): BelongsTo` → Event
- `household(): BelongsTo` → Household (nullable)
- `potentialHousehold(): BelongsTo` → Household (foreign: potential_household_id, nullable)

---

## Visit (`app/Models/Visit.php`)

**Fillable:** event_id, lane, queue_position, visit_status, start_time, end_time, served_bags, queued_at, loading_completed_at, exited_at  
**Casts:** start_time, end_time, queued_at, loading_completed_at, exited_at → datetime; lane, queue_position, served_bags → integer  

**Status Helpers (bool):**
- `isCheckedIn()`, `isQueued()`, `isLoading()`, `isLoaded()`, `isExited()`

**Other Methods:**
- `statusLabel(): string`
- `isActive(): bool` — end_time is null (legacy helper)
- `durationMinutes(): int` — minutes elapsed since start_time

**Relationships:**
- `event(): BelongsTo` → Event
- `households(): BelongsToMany` → Household via `visit_households`
- `primaryHousehold(): ?Household` — returns first household in the visit

---

## EventMedia (`app/Models/EventMedia.php`)

**Fillable:** event_id, disk, path, name, mime_type, size, type, sort_order  
**Casts:** size → integer, sort_order → integer  

**Accessors:**
- `url` — builds public asset URL from `path`
- `size_formatted` — formats bytes as "12.5 MB"

**Methods:**
- `isImage(): bool`, `isVideo(): bool`

**Relationships:**
- `event(): BelongsTo` → Event

---

## EventReview (`app/Models/EventReview.php`)

**Fillable:** event_id, rating, review_text, reviewer_name, email, is_visible  
**Casts:** rating → integer, is_visible → boolean  

**Relationships:**
- `event(): BelongsTo` → Event

---

## EventInventoryAllocation (`app/Models/EventInventoryAllocation.php`)

**Fillable:** event_id, inventory_item_id, allocated_quantity, distributed_quantity, returned_quantity, notes  
**Casts:** allocated_quantity, distributed_quantity, returned_quantity → integer  

**Methods:**
- `remainingQuantity(): int` — `allocated - distributed - returned`
- `maxReturnable(): int` — same as remainingQuantity
- `canReturn(): bool` — remaining > 0

**Relationships:**
- `event(): BelongsTo` → Event
- `item(): BelongsTo` → InventoryItem (foreign key: inventory_item_id)

---

## AllocationRuleset (`app/Models/AllocationRuleset.php`)

**Fillable:** name, allocation_type, description, is_active, max_household_size, rules  
**Casts:** is_active → boolean, max_household_size → integer, rules → array  

**Methods:**
- `getBagsFor(int $size): int` — iterates rules array, returns `bags` for the matching `min`/`max` range. Returns 0 if no rule matches.
- `ruleLabel(array $rule, string $unit = 'person'): string` — returns a readable string like "2–4 people: 2 bags"

---

## InventoryCategory (`app/Models/InventoryCategory.php`)

**Fillable:** name, description  

**Relationships:**
- `items(): HasMany` → InventoryItem

---

## InventoryItem (`app/Models/InventoryItem.php`)

**Fillable:** name, sku, category_id, unit_type, quantity_on_hand, reorder_level, description, is_active  
**Casts:** quantity_on_hand, reorder_level → integer, is_active → boolean  

**Stock Status Helpers:**
- `stockStatus(): string` — `'out'` (qty=0), `'low'` (qty <= reorder_level), `'in'`
- `stockLabel(): string` — human label
- `stockBadgeClasses(): string` — Tailwind badge CSS

**Scopes:**
- `active()` — is_active = true
- `search(string $term)` — match name, sku, or category name (join)
- `lowStock()` — quantity_on_hand <= reorder_level
- `outOfStock()` — quantity_on_hand = 0

**Relationships:**
- `category(): BelongsTo` → InventoryCategory (nullable)
- `movements(): HasMany` → InventoryMovement
- `eventAllocations(): HasMany` → EventInventoryAllocation

---

## InventoryMovement (`app/Models/InventoryMovement.php`)

**Fillable:** inventory_item_id, movement_type, quantity, event_id, user_id, notes  
**Casts:** quantity → integer, created_at → datetime  
**Updated At:** `null` — records are immutable  

**Constants:**
```php
TYPES = [
    'stock_in'          => 'Stock In',
    'stock_out'         => 'Stock Out',
    'adjustment'        => 'Adjustment',
    'damaged'           => 'Damaged',
    'expired'           => 'Expired',
    'event_allocated'   => 'Event Allocated',
    'event_returned'    => 'Event Returned',
    'event_distributed' => 'Event Distributed',
]
INBOUND  = ['stock_in', 'event_returned']
OUTBOUND = ['stock_out', 'damaged', 'expired', 'event_allocated', 'event_distributed']
```

**Display Methods:**
- `typeLabel(): string`
- `typeBadgeClasses(): string`
- `quantityDisplay(): string` — `"+50"` or `"−10"`
- `quantityClasses(): string` — green for inbound, red for outbound

**Scopes:**
- `ofType(string $type)`, `forItem(int $itemId)`

**Relationships:**
- `item(): BelongsTo` → InventoryItem
- `event(): BelongsTo` → Event (nullable)
- `user(): BelongsTo` → User (nullable)

---

## FinanceCategory (`app/Models/FinanceCategory.php`)

**Fillable:** name, type, description, is_active  
**Casts:** is_active → boolean  

**Scopes:** `active()`, `income()`, `expense()`

**Display Methods:**
- `typeBadgeClasses(): string`, `typeLabel(): string`

**Relationships:**
- `transactions(): HasMany` → FinanceTransaction

---

## FinanceTransaction (`app/Models/FinanceTransaction.php`)

**Fillable:** transaction_type, title, category_id, amount, transaction_date, source_or_payee, payment_method, reference_number, event_id, notes, attachment_path, status, created_by  
**Casts:** amount → decimal:2, transaction_date → date  

**Constants:**
```php
PAYMENT_METHODS = ['Cash', 'Bank Transfer', 'Check', 'Online', 'Other']
STATUSES        = ['pending', 'completed', 'cancelled']
```

**Methods:**
- `isIncome(): bool`, `isExpense(): bool`
- `typeBadgeClasses(): string`, `statusBadgeClasses(): string`
- `formattedAmount(): string` — `"$1,234.56"`

**Scopes:** `income()`, `expense()`, `forEvent(int $eventId)`

**Relationships:**
- `category(): BelongsTo` → FinanceCategory
- `event(): BelongsTo` → Event (nullable)
- `creator(): BelongsTo` → User (foreign: created_by, nullable)

---

## AppSetting (`app/Models/AppSetting.php`)

**Fillable:** group, key, value, type  

**Accessors:**
- `casted_value` — returns value cast to the correct PHP type based on `type` column (boolean, integer, float, json, or raw string/text)
