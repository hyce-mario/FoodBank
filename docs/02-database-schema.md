# Database Schema

All tables follow Laravel conventions: `id` (bigint unsigned, auto-increment), `created_at`, `updated_at` timestamps unless noted.

---

## Authentication & Users

### `users`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| email | string UNIQUE | |
| email_verified_at | timestamp nullable | |
| password | string | bcrypt hashed |
| role_id | bigint FK→roles.id nullable | |
| remember_token | string nullable | |

### `roles`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string UNIQUE | ADMIN, INTAKE, SCANNER, LOADER, REPORTS, VOL_MANAGER |
| display_name | string | |
| description | text nullable | |

### `role_permissions`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| role_id | bigint FK→roles.id | |
| permission | string | dot-notation e.g. `households.view`, `*` for full access |

### `sessions`
Standard Laravel session table (id, user_id, ip_address, user_agent, payload, last_activity).

### `password_reset_tokens`
Standard Laravel password reset table (email PK, token, created_at).

---

## Households

### `households`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| household_number | string(5) UNIQUE | auto-generated |
| first_name | string | |
| last_name | string | |
| email | string nullable | |
| phone | string nullable | |
| city | string | |
| state | char(2) | |
| zip | string | |
| vehicle_make | string nullable | drive-through events |
| vehicle_color | string nullable | |
| household_size | smallint | total people |
| children_count | tinyint | |
| adults_count | tinyint | |
| seniors_count | tinyint | |
| number_of_families | tinyint default 1 | |
| family_breakdown | JSON nullable | `[{label, size}, ...]` |
| representative_household_id | bigint FK→households.id nullable | the household that represents this one |
| notes | text nullable | |
| qr_token | string(64) UNIQUE | UUID for QR code |

**Indexes:** (first_name, last_name), zip, household_size

---

## Volunteers

### `volunteers`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| first_name | string | |
| last_name | string | |
| phone | string nullable | |
| email | string nullable | |
| role | string(50) | Driver, Loader, Intake, Scanner, Coordinator, Other |
| user_id | bigint FK→users.id UNIQUE nullable | linked user account |

**Indexes:** (last_name, first_name)

### `volunteer_groups`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string(100) UNIQUE | |
| description | text nullable | |

### `volunteer_group_memberships`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| volunteer_id | bigint FK→volunteers.id CASCADE | |
| group_id | bigint FK→volunteer_groups.id CASCADE | |
| joined_at | timestamp | |

**Unique:** (volunteer_id, group_id)

---

## Events & Operations

### `events`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string(150) | |
| date | date | |
| status | enum(upcoming, current, past) | auto-derived from date |
| location | string(255) nullable | |
| lanes | tinyint default 1 | number of queue lanes |
| ruleset_id | bigint FK→allocation_rulesets.id nullable | |
| volunteer_group_id | bigint FK→volunteer_groups.id nullable | |
| notes | text nullable | |
| intake_auth_code | string(4) | 4-digit staff auth code |
| scanner_auth_code | string(4) | |
| loader_auth_code | string(4) | |
| exit_auth_code | string(4) | |

**Indexes:** date

### `visits`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| event_id | bigint FK→events.id CASCADE | |
| lane | tinyint | queue lane number |
| queue_position | integer | position within lane |
| visit_status | enum(checked_in, queued, loading, loaded, exited) | |
| start_time | datetime | |
| end_time | datetime nullable | null = still active |
| queued_at | datetime nullable | |
| loading_completed_at | datetime nullable | |
| exited_at | datetime nullable | |
| served_bags | smallint default 0 | |

### `visit_households` (pivot)
| Column | Type | Notes |
|--------|------|-------|
| visit_id | bigint FK→visits.id CASCADE | |
| household_id | bigint FK→households.id CASCADE | |

**Primary Key:** (visit_id, household_id)

---

## Pre-Registration

### `event_pre_registrations`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| event_id | bigint FK→events.id CASCADE | |
| attendee_number | string(5) | unique per event |
| first_name | string | |
| last_name | string | |
| email | string(255) | |
| city | string nullable | |
| state | string nullable | |
| zipcode | string nullable | |
| household_size | smallint default 1 | |
| children_count | integer | |
| adults_count | integer | |
| seniors_count | integer | |
| household_id | bigint FK→households.id nullable | matched household |
| potential_household_id | bigint FK→households.id nullable | suggested match |
| match_status | enum(unmatched, matched, needs_review) nullable | |

**Indexes:** event_id, email

---

## Event Media & Reviews

### `event_media`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| event_id | bigint FK→events.id CASCADE | |
| disk | string default 'public' | |
| path | string | e.g. `event-media/12/abc.jpg` |
| name | string | original filename |
| mime_type | string(100) | |
| size | bigint default 0 | bytes |
| type | enum(image, video) default image | |
| sort_order | unsigned int default 0 | |

### `event_reviews`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| event_id | bigint FK→events.id CASCADE | |
| rating | tinyint | 1–5 |
| review_text | text | |
| reviewer_name | string(100) nullable | null = anonymous |
| email | string(255) nullable | |
| is_visible | boolean default true | |

**Indexes:** (event_id, is_visible), rating

---

## Inventory

### `inventory_categories`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string(100) UNIQUE | |
| description | text nullable | |

### `inventory_items`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string(150) | |
| sku | string(100) UNIQUE nullable | |
| category_id | bigint FK→inventory_categories.id nullable | |
| unit_type | string(50) | box, bag, case, etc. |
| quantity_on_hand | unsigned int default 0 | |
| reorder_level | unsigned int default 0 | low-stock threshold |
| description | text nullable | |
| is_active | boolean default true | |

### `inventory_movements`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| inventory_item_id | bigint FK→inventory_items.id CASCADE | |
| movement_type | string(30) | see types below |
| quantity | integer (signed) | positive = in, negative = out |
| event_id | bigint FK→events.id nullable | |
| user_id | bigint FK→users.id nullable | |
| notes | text nullable | |
| created_at | timestamp | immutable — no updated_at |

**Movement Types:**
- Inbound: `stock_in`, `event_returned`
- Outbound: `stock_out`, `damaged`, `expired`, `event_allocated`, `event_distributed`

### `event_inventory_allocations`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| event_id | bigint FK→events.id CASCADE | |
| inventory_item_id | bigint FK→inventory_items.id CASCADE | |
| allocated_quantity | unsigned int | |
| distributed_quantity | unsigned int default 0 | |
| returned_quantity | unsigned int default 0 | |
| notes | text nullable | |

---

## Finance

### `finance_categories`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string(100) | |
| type | enum(income, expense) | |
| description | text nullable | |
| is_active | boolean default true | |

### `finance_transactions`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| transaction_type | enum(income, expense) | |
| title | string | |
| category_id | bigint FK→finance_categories.id RESTRICT | |
| amount | decimal(10,2) | |
| transaction_date | date | |
| source_or_payee | string | |
| payment_method | string(50) nullable | Cash, Bank Transfer, Check, Online, Other |
| reference_number | string(100) nullable | |
| event_id | bigint FK→events.id nullable | |
| notes | text nullable | |
| attachment_path | string nullable | receipt/document path |
| status | string(20) default 'completed' | pending, completed, cancelled |
| created_by | bigint FK→users.id nullable | |

---

## Allocation Rulesets

### `allocation_rulesets`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string(100) | |
| allocation_type | string nullable | |
| description | text nullable | |
| is_active | boolean default true | |
| max_household_size | smallint default 20 | |
| rules | JSON | `[{min, max, bags}, ...]` — max null means "and above" |

**Example rules:**
```json
[
  {"min": 1, "max": 1, "bags": 1},
  {"min": 2, "max": 4, "bags": 2},
  {"min": 5, "max": null, "bags": 3}
]
```

---

## Settings

### `app_settings`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| group | string(64) | e.g. `general`, `branding` |
| key | string(128) UNIQUE | full key e.g. `general.app_name` |
| value | text nullable | stored as string |
| type | string(32) | string, boolean, integer, float, json, text |

**Indexes:** group
