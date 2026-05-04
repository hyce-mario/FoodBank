# Controllers

All controllers live in `app/Http/Controllers/`. Controllers are thin — they resolve input, call the appropriate service, and return a view or redirect. Business logic lives in `app/Services/`.

---

## Authentication

### `LoginController`
Handles session-based login and logout.

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `showLogin()` | GET | `/login` | Renders the login form |
| `login()` | POST | `/login` | Validates credentials via `LoginRequest`, creates session |
| `logout()` | POST | `/logout` | Destroys session, redirects to login |

---

## Dashboard

### `DashboardController`
Compiles KPI data for the admin dashboard.

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `index()` | GET | `/` | Passes KPIs, current event summary, and low-stock alerts to `dashboard/index` view |

---

## Households

### `HouseholdController`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `index(Request)` | GET | `/households` | Paginated list with search, zip, and size filters |
| `create()` | GET | `/households/create` | Create form |
| `store(StoreHouseholdRequest)` | POST | `/households` | Calls `HouseholdService::create()` |
| `show(Household)` | GET | `/households/{id}` | Detail view with represented families and visit history |
| `edit(Household)` | GET | `/households/{id}/edit` | Edit form |
| `update(UpdateHouseholdRequest, Household)` | PUT | `/households/{id}` | Calls `HouseholdService::update()` |
| `destroy(Household)` | DELETE | `/households/{id}` | Delete with guard (no active visits) |
| `regenerateQr(Household)` | POST | `/households/{id}/regenerate-qr` | Calls `HouseholdService::regenerateQrToken()` |
| `attach(Household)` | POST | `/households/{id}/attach` | Link another household as represented |
| `detach(Household, $represented)` | DELETE | `/households/{id}/detach/{rep}` | Unlink represented household |

---

## Events

### `EventController`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `index(Request)` | GET | `/events` | Paginated list, filterable by status |
| `create()` | GET | `/events/create` | Create form |
| `store(StoreEventRequest)` | POST | `/events` | Create event with optional auth codes |
| `show(Event)` | GET | `/events/{id}` | Detailed view: allocations, media, reviews, pre-regs |
| `edit(Event)` | GET | `/events/{id}/edit` | Edit form |
| `update(UpdateEventRequest, Event)` | PUT | `/events/{id}` | Update event |
| `destroy(Event)` | DELETE | `/events/{id}` | Delete (guard: no visits) |
| `updateStatus(Event)` | PATCH | `/events/{id}/status` | Manually override status |
| `detachVolunteer(Event, Volunteer)` | DELETE | `/events/{id}/volunteers/{vol}` | Remove volunteer from event |
| `matchAttendee(Event, $attendee)` | POST | `/events/{id}/attendees/{att}/match` | Link pre-registration to existing household |
| `deleteAttendee(Event, $attendee)` | DELETE | `/events/{id}/attendees/{att}` | Remove pre-registration |
| `regenerateCodes(Event)` | POST | `/events/{id}/regenerate-codes` | Regenerate all 4 auth codes |

---

## Check-In

### `CheckInController`
Handles the real-time check-in interface including inline household creation and represented-family management.

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `index(Request)` | GET | `/checkin` | Renders check-in UI with active event selector |
| `search(Request)` | GET | `/checkin/search` | JSON: household search by number/name/phone/QR |
| `queue(Request)` | GET | `/checkin/queue` | JSON: active visit queue for selected event (polling) |
| `log(Request)` | GET | `/checkin/log` | JSON: last 20 visits for selected event |
| `store(CheckInRequest)` | POST | `/checkin` | JSON: check in household → calls `EventCheckInService::checkIn()` |
| `quickAdd(Request)` | POST | `/checkin/quick-add` | JSON: fast lookup by QR code |
| `quickCreate(Request)` | POST | `/checkin/quick-create` | JSON: create household inline during check-in |
| `done(Visit)` | PATCH | `/checkin/{visit}/done` | Mark visit exited immediately |
| `updateVehicle(Household)` | PATCH | `/checkin/households/{hh}/vehicle` | Update vehicle make/color |
| `createRepresented(Request)` | POST | `/checkin/represented/create` | JSON: create new represented household inline |
| `attachRepresented(Request)` | POST | `/checkin/represented/attach` | JSON: attach existing household as represented |
| `searchRepresented(Request)` | GET | `/checkin/represented/search` | JSON: search households to attach |

---

## Event-Day Roles

### `EventDayController`
Public-facing pages for intake, scanner, loader, and exit roles. No admin nav shown. Auth via event-specific numeric codes stored in session.

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `page(Event)` | GET | `/{role}/{event}` | Role-specific page (queue view, actions) |
| `submitAuth(Event)` | POST | `/{role}/{event}/auth` | Validate auth code; set session flag |
| `logout(Event)` | POST | `/{role}/{event}/out` | Clear event-day session |
| `data(Event)` | GET | `/{role}/{event}/data` | JSON: current queue state (AJAX polling) |
| `markQueued(Event, Visit)` | PATCH | `/ed/{event}/visits/{visit}/queued` | Transition visit to `queued` |
| `markLoaded(Event, Visit)` | PATCH | `/ed/{event}/visits/{visit}/loaded` | Transition visit to `loaded` |
| `markExited(Event, Visit)` | PATCH | `/ed/{event}/visits/{visit}/exited` | Transition visit to `exited` |
| `reorder(Event)` | POST | `/ed/{event}/reorder` | Reorder visits within a lane |

---

## Visit Monitor

### `VisitMonitorController`
Staff-side real-time monitor showing all lanes for an event.

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `index(Request)` | GET | `/monitor` | Monitor dashboard (event selector, lane grid) |
| `data(Event)` | GET | `/monitor/{event}/data` | JSON: live queue data for all lanes |
| `reorder(Event)` | POST | `/monitor/{event}/reorder` | Reorder visits |

---

## Visit Log

### `VisitLogController`
Post-event reporting.

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `index(Request)` | GET | `/visit-log` | Paginated visit list with event selector |
| `export()` | GET | `/visit-log/export` | Download visits as CSV/Excel |

---

## Volunteers

### `VolunteerController`
Standard CRUD.

| Method | HTTP | Route |
|--------|------|-------|
| `index()` | GET | `/volunteers` |
| `create()` | GET | `/volunteers/create` |
| `store(StoreVolunteerRequest)` | POST | `/volunteers` |
| `show(Volunteer)` | GET | `/volunteers/{id}` |
| `edit(Volunteer)` | GET | `/volunteers/{id}/edit` |
| `update(UpdateVolunteerRequest, Volunteer)` | PUT | `/volunteers/{id}` |
| `destroy(Volunteer)` | DELETE | `/volunteers/{id}` |

### `VolunteerGroupController`

Standard CRUD plus member management.

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `index()` through `destroy()` | — | — | Standard CRUD |
| `editMembers(VolunteerGroup)` | GET | `/volunteer-groups/{id}/members` | Group member assignment UI |
| `updateMembers(VolunteerGroup)` | POST | `/volunteer-groups/{id}/members` | Sync members (checkbox list) |

---

## Inventory

### `InventoryCategoryController`
No standalone create/edit/show views — categories managed inline on the items page.

| Method | HTTP | Route |
|--------|------|-------|
| `index()` | GET | `/inventory/categories` |
| `store(StoreInventoryCategoryRequest)` | POST | `/inventory/categories` |
| `update(UpdateInventoryCategoryRequest, $cat)` | PATCH | `/inventory/categories/{id}` |
| `destroy($cat)` | DELETE | `/inventory/categories/{id}` |

### `InventoryItemController`
Full CRUD.

| Method | HTTP | Route |
|--------|------|-------|
| `index()` | GET | `/inventory/items` |
| `create()` | GET | `/inventory/items/create` |
| `store(StoreInventoryItemRequest)` | POST | `/inventory/items` |
| `show(InventoryItem)` | GET | `/inventory/items/{id}` |
| `edit(InventoryItem)` | GET | `/inventory/items/{id}/edit` |
| `update(UpdateInventoryItemRequest, InventoryItem)` | PUT | `/inventory/items/{id}` |
| `destroy(InventoryItem)` | DELETE | `/inventory/items/{id}` |

### `InventoryMovementController`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `store(StoreInventoryMovementRequest, InventoryItem)` | POST | `/inventory/items/{id}/movements` | Record manual stock movement |

### `EventInventoryAllocationController`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `store(StoreEventInventoryAllocationRequest, Event)` | POST | `/events/{id}/inventory` | Allocate items to event |
| `updateDistributed(Event, $alloc)` | PATCH | `/events/{id}/inventory/{alloc}/distributed` | Update distributed quantity |
| `returnStock(Event, $alloc)` | POST | `/events/{id}/inventory/{alloc}/return` | Return unused stock |
| `destroy(Event, $alloc)` | DELETE | `/events/{id}/inventory/{alloc}` | Remove allocation |

---

## Finance

### `FinanceController`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `dashboard()` | GET | `/finance/` | KPIs, monthly chart, category breakdown |
| `reports()` | GET | `/finance/reports` | Detailed finance report |

### `FinanceCategoryController`
Inline-managed (no show/create/edit pages).

| Method | HTTP | Route |
|--------|------|-------|
| `index()` | GET | `/finance/categories` |
| `store()` | POST | `/finance/categories` |
| `update($cat)` | PATCH | `/finance/categories/{id}` |
| `destroy($cat)` | DELETE | `/finance/categories/{id}` |

### `FinanceTransactionController`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| Standard CRUD | — | — | index/create/store/show/edit/update/destroy |
| `downloadAttachment(FinanceTransaction)` | GET | `/finance/transactions/{id}/attachment` | Stream receipt file |
| `removeAttachment(FinanceTransaction)` | DELETE | `/finance/transactions/{id}/attachment` | Delete stored receipt |

---

## Reports

### `ReportsController`

| Method | HTTP | Route |
|--------|------|-------|
| `overview()` | GET | `/reports/` |
| `events()` | GET | `/reports/events` |
| `trends()` | GET | `/reports/trends` |
| `demographics()` | GET | `/reports/demographics` |
| `lanes()` | GET | `/reports/lanes` |
| `queueFlow()` | GET | `/reports/queue-flow` |
| `volunteers()` | GET | `/reports/volunteers` |
| `reviews()` | GET | `/reports/reviews` |
| `inventory()` | GET | `/reports/inventory` |
| `export()` | GET | `/reports/export` |
| `downloadExport()` | GET | `/reports/download` |

---

## Settings

### `SettingsController`

| Method | HTTP | Route | Notes |
|--------|------|-------|-------|
| `index()` | GET | `/settings/` | Settings home (group list) |
| `show(string $group)` | GET | `/settings/{group}` | Settings for one group |
| `update(string $group)` | PUT | `/settings/{group}` | Calls `SettingService::updateGroup()` |
| `uploadBrandingAsset(string $asset)` | POST | `/settings/branding/{asset}` | Upload logo or favicon |
| `deleteBrandingAsset(string $asset)` | DELETE | `/settings/branding/{asset}` | Delete logo or favicon |

---

## Users & Roles

### `UserController`
Full CRUD for admin user management.

### `RoleController`
Full CRUD plus permission assignment.

---

## Media & Reviews

### `EventMediaController`

| Method | HTTP | Route |
|--------|------|-------|
| `store(Event)` | POST | `/events/{id}/media` |
| `destroy(Event, EventMedia)` | DELETE | `/events/{id}/media/{media}` |

### `ReviewController`
Admin moderation.

| Method | HTTP | Route |
|--------|------|-------|
| `index()` | GET | `/reviews` |
| `toggleVisibility(EventReview)` | PATCH | `/reviews/{review}/toggle-visibility` |

### `PublicReviewController`
No auth required.

| Method | HTTP | Route |
|--------|------|-------|
| `create()` | GET | `/review/` |
| `store(StoreReviewRequest)` | POST | `/review/` |

---

## Public Event Registration

### `PublicEventController`
No auth required.

| Method | HTTP | Route |
|--------|------|-------|
| `index()` | GET | `/register/` |
| `register(Event)` | GET | `/register/{event}` |
| `submit(Event)` | POST | `/register/{event}` |
| `success(Event)` | GET | `/register/{event}/success` |

---

## Profile

### `ProfileController`

| Method | HTTP | Route |
|--------|------|-------|
| `show()` | GET | `/profile` |
| `updateInfo()` | PUT | `/profile/info` |
| `updatePassword()` | PUT | `/profile/password` |

---

## Allocation Rulesets

### `AllocationRulesetController`

| Method | HTTP | Route |
|--------|------|-------|
| `index()` | GET | `/allocation-rulesets` |
| `store()` | POST | `/allocation-rulesets` |
| `update($ruleset)` | PUT | `/allocation-rulesets/{id}` |
| `destroy($ruleset)` | DELETE | `/allocation-rulesets/{id}` |
| `preview(AllocationRuleset)` | GET | `/allocation-rulesets/{id}/preview` |
