# Seeders & Console Commands

---

## Seeders

Run with: `php artisan db:seed`

All seeders called from `database/seeders/DatabaseSeeder.php` in this order:

### 1. `RoleSeeder`
Creates the default roles:

| name | display_name | Default Permissions |
|------|-------------|---------------------|
| ADMIN | Administrator | `*` (full access wildcard) |
| INTAKE | Intake Staff | households.view, checkin.view, checkin.scan, events.view |
| SCANNER | Scanner Staff | checkin.view, checkin.scan, events.view |
| LOADER | Loader Staff | checkin.view, events.view |
| REPORTS | Reports Viewer | reports.view, households.view, events.view |
| VOL_MANAGER | Volunteer Manager | volunteers.view, volunteers.create, volunteers.edit, volunteer_groups.view, volunteer_groups.create, volunteer_groups.edit, events.view |

### 2. `AdminUserSeeder`
Creates the default admin account:
- Email: `admin@foodbank.local`
- Password: `password` (change immediately in production)
- Role: ADMIN

### 3. `VolunteerSeeder`
Creates 15–20 sample volunteers with randomized roles and names using Faker.

### 4. `DemoSeeder`
Creates demo data for development and testing:
- 30 sample households with realistic demographics
- 3 events (one past, one current, one upcoming)
- Visit records linking households to past/current events
- Check-in records with varied statuses

### 5. `InventoryCategorySeeder`
Creates standard inventory categories:
- Canned Goods
- Dry Goods
- Fresh Produce
- Dairy
- Frozen
- Personal Care
- Baby Items
- Beverages

### 6. `InventoryItemSeeder`
Creates 20–30 common food bank items, each assigned to a category, with starting quantities and reorder levels.

### 7. `FinanceCategorySeeder`
Creates standard finance categories:

**Income:**
- Donations (Individual)
- Corporate Donations
- Grants
- Fundraising Events
- Government Funding

**Expense:**
- Food Procurement
- Facility Rent
- Utilities
- Staff Wages
- Volunteer Expenses
- Equipment

### 8. `FinanceTransactionSeeder`
Creates 30–50 sample transactions across the last 12 months using the seeded categories.

### 9. `SettingsSeeder`
Seeds all 98 default settings into `app_settings` using `SettingService::definitions()`.
- Inserts one row per definition using `upsert()` (safe to re-run)
- Values default to the `default` property from each definition

---

## Console Commands

### `events:sync-statuses`

**Location:** `app/Console/Commands/SyncEventStatuses.php`

**Schedule:** Daily at `00:01` (`routes/console.php`)

**Logic:**
```php
// Mark events as 'current' if their date is today
Event::where('date', today())->where('status', '!=', 'current')->update(['status' => 'current']);

// Mark events as 'past' if their date has passed
Event::where('date', '<', today())->where('status', '!=', 'past')->update(['status' => 'past']);

// Mark future events as 'upcoming'
Event::where('date', '>', today())->where('status', '!=', 'upcoming')->update(['status', 'upcoming']);
```

This keeps event statuses accurate without requiring manual updates.

---

## Running Seeders in Production

For fresh production deployment:
```bash
php artisan migrate --seed
```

To reseed settings only (safe, uses upsert):
```bash
php artisan db:seed --class=SettingsSeeder
```

To reseed roles only:
```bash
php artisan db:seed --class=RoleSeeder
```
