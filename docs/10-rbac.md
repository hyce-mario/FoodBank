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
