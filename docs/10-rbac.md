# Role-Based Access Control (RBAC)

---

## Overview

The system uses a custom RBAC implementation — not Laravel Gates/Policies by default, though the gate can be integrated. Permissions are stored in the database and checked at request time via middleware.

---

## Data Model

```
users ──── role_id ──▶ roles ──── id ──▶ role_permissions
                                         (role_id, permission)
```

- Each user has one role
- Each role has many permissions
- Permissions are dot-notation strings: `module.action`

---

## Permission Strings

Format: `module.action`

| Module | Actions |
|--------|---------|
| households | view, create, edit, delete |
| checkin | view, scan |
| events | view, create, edit, delete |
| inventory | view, create, edit, delete |
| finance | view, create, edit, delete |
| volunteers | view, create, edit, delete |
| volunteer_groups | view, create, edit, delete |
| reports | view |
| settings | view, update |
| users | view, create, edit, delete |
| roles | view, create, edit, delete |
| reviews | view, moderate |

### Special: Wildcard Permission
A role with permission `*` has access to everything. The ADMIN role has this by default.

---

## Permission Check: `User::hasPermission(string $permission): bool`

```php
public function hasPermission(string $permission): bool
{
    if (!$this->role) return false;
    
    $permissions = $this->role->permissions->pluck('permission');
    
    // Wildcard check
    if ($permissions->contains('*')) return true;
    
    // Exact match
    return $permissions->contains($permission);
}
```

---

## Middleware: `CheckPermission`

Applied to routes using:
```php
->middleware('permission:households.view')
->middleware('permission:settings.update')
```

Registered in `AppServiceProvider` (or `bootstrap/app.php`) as:
```php
$middleware->alias(['permission' => CheckPermission::class]);
```

---

## Default Roles

| Role | Key Permissions |
|------|----------------|
| ADMIN | `*` (everything) |
| INTAKE | households.view, checkin.view, checkin.scan, events.view |
| SCANNER | checkin.view, checkin.scan, events.view |
| LOADER | checkin.view, events.view |
| REPORTS | reports.view, households.view, events.view |
| VOL_MANAGER | volunteers.*, volunteer_groups.*, events.view |

---

## Event-Day Auth (Separate from RBAC)

Event-day role pages (`/intake/{event}`, `/scanner/{event}`, etc.) use a different authentication mechanism:

1. Page is publicly accessible (no Laravel auth required)
2. Each event has 4 numeric codes: `intake_auth_code`, `scanner_auth_code`, `loader_auth_code`, `exit_auth_code`
3. On first visit, a code entry form is shown
4. The submitted code is validated against the event record
5. Success stores a session key: `event_day_auth_{role}_{event_id} = true`
6. Subsequent requests check this session key

This allows operational staff to use shared tablets without user accounts.

---

## Adding Permissions to Routes

```php
// Single permission
Route::get('/households', [HouseholdController::class, 'index'])
    ->middleware('permission:households.view');

// Multiple permissions on a group
Route::group(['middleware' => 'permission:finance.view'], function () {
    Route::get('/finance/', ...);
    Route::get('/finance/reports', ...);
});
```

---

## Role Management UI

Available at `/roles` (requires `roles.view` permission).

- View all roles and their permissions
- Create new roles with a permission matrix (checkboxes grouped by module)
- Edit existing roles (cannot change ADMIN name)
- Delete roles (blocked if ADMIN or has assigned users)

---

## Automated Guardrails (don't break these)

Two feature tests catch the entire class of "I created a role with limited
permissions and now this page leaks" bugs that recur whenever new admin
surface area is added:

### `tests/Feature/RbacRouteAuditTest.php`

Static analysis. Walks `Route::getRoutes()`, classifies every authenticated
route as **GATED** (route middleware `permission:*`) or **POLICY**
(controller `$this->authorize(...)` or typed FormRequest containing
`hasPermission(`). Anything else must appear in the test's `ALLOWLIST`
constant. **Adding a new auth-protected route without a gate breaks CI.**

### `tests/Feature/RbacNoPermissionSmokeTest.php`

Behavioural complement. Creates a user whose role has zero permissions,
hits every parameterless GET admin route, asserts `status !== 200`. Catches
the "policy exists but checks the wrong permission string" class of bug
that static analysis can't see.

The `scripts/rbac-audit.php` CLI does the same classification ad-hoc:

```bash
php artisan route:list --json | php scripts/rbac-audit.php
```

---

## Catalog validation

`Store/UpdateRoleRequest` validate `permissions[]` against
`RolePermissionService::allPermissions() + ['*']` via `Rule::in(...)`.
Custom roles cannot save typo'd or unknown permission strings. Adding a
new permission means **also** updating
`RolePermissionService::permissionGroups()` — otherwise the role-edit form
won't render the checkbox AND the validator will reject the string.

---

## Contributor checklist

When **adding a new permission** (e.g. `purchase_orders.export`):

1. Add it to `RolePermissionService::permissionGroups()`.
2. Reference it from the gate that enforces it — route middleware
   `permission:purchase_orders.export` and/or a Policy method.
3. Optionally seed it into `database/seeders/RoleSeeder.php` for the
   roles that should have it by default.
4. Run the suite. The audit + smoke tests will pass automatically because
   the new gate is in place.

When **adding a new admin route**:

1. Pick the permission that gates the action (or create a new one — see
   above).
2. Apply `->middleware('permission:<perm>')` on the route. **Prefer route
   middleware over controller-only `$this->authorize()`** — it's visible
   in `php artisan route:list` and survives controller refactors.
3. If the action is intentionally accessible to all authenticated users
   (Profile, Logout, Dashboard), add the route name to `ALLOWLIST` in
   `RbacRouteAuditTest` AND `ALWAYS_ACCESSIBLE` in
   `RbacNoPermissionSmokeTest` with a one-line justification comment.

When **adding a new admin UI** (button, link, modal, form):

1. Wrap the element in `@can('<perm>')` so users without the permission
   don't see it. The permission system supports both dot-notation
   (`@can('settings.update')`) and policy-style (`@can('viewAny', App\Models\Event::class)`).
2. For forms whose POST/PUT route is gated, also disable inputs and hide
   the submit button when the user lacks the permission. See
   `resources/views/settings/show.blade.php` for the canonical pattern
   (read-only banner + `<fieldset disabled>` + submit hidden via
   `@can('settings.update')`).

---

## Production deploy hygiene

Cached routes + views can mask permission middleware that was added in
a later commit. After `git pull`, **always** clear-then-rebuild:

```bash
php artisan route:clear  && php artisan route:cache
php artisan config:clear && php artisan config:cache
php artisan view:clear   && php artisan view:cache
php artisan event:clear  && php artisan event:cache
```

The `clear` step is load-bearing — see [DEPLOY.md](../DEPLOY.md) "Updating
production" section for the full flow.
