# ADR-002: UserController is admin-only

- **Date:** 2026-04-29
- **Status:** accepted
- **Phase:** Phase 0.1
- **Author:** Claude (session 1)

## Context

Before this work, `StoreUserRequest::authorize()` and `UpdateUserRequest::authorize()` both unconditionally returned `true`. Combined with `role_id` in `User::$fillable`, any authenticated user could:
- POST `/users` with any role_id (create an admin)
- PUT `/users/{anyone}` with `role_id = ADMIN.id` (promote anyone, including themselves)

This is the highest-blast-radius bug in the audit (AUDIT_REPORT.md Part 10, Part 13 §0.1).

The user instructed (2026-04-29): *strictest — ADMIN-only role changes, everyone else can edit name/email but not role.*

That phrasing has two reasonable readings:

1. **Strict interpretation:** UserController is a privileged admin tool. Non-admin self-profile editing happens through a separate ProfileController. Outside of admin work, UserController is fully off-limits.
2. **Permissive interpretation:** Anyone with `users.edit` permission can edit any user's name/email but role changes are admin-only.

## Decision

We adopt the **strict interpretation**: `UserController` is fully admin-only. Both `StoreUserRequest::authorize()` and `UpdateUserRequest::authorize()` require `Auth::user()->isAdmin()`. Self-profile editing for non-admins is the responsibility of `ProfileController` (which is outside Phase 0.1 scope and not modified here).

We additionally add **defense in depth** in `UserController::update`: even if `UpdateUserRequest::authorize()` is later widened to include non-admin permissions, `role_id` is only assigned when `$request->user()->isAdmin()`. This prevents a future weakening of authorize() from silently re-introducing the privilege escalation.

## Alternatives considered

- **Permissive interpretation** (any `users.edit` holder can edit name/email; role-only is admin-locked). Rejected because:
  - Currently no role besides ADMIN has any `users.*` permission, so the reading is functionally identical today, but
  - The permissive reading creates more surface area to defend in Phase 4 (policies, ownership checks),
  - It conflates "user administration" with "self-profile editing," whereas Laravel idiom keeps these in separate controllers.
- **Hard-prohibit `role_id` field via FormRequest rules** (e.g., `Rule::prohibitedIf` for non-admins). Considered as additional belt-and-suspenders; rejected for now because `authorize()` already gates the request entirely. Re-evaluate if non-admin ever gains controller access.

## Consequences

- **Positive:** privilege escalation closed at the route + request + controller layers (defense in depth). The headline regression test (`test_non_admin_cannot_promote_self_to_admin`) pins this in place.
- **Positive:** UserController has a single, simple security model: ADMIN-only.
- **Negative / accepted trade-offs:** any non-admin self-edit features must go through ProfileController. If that controller has its own authorize bugs (untested in Phase 0.1), they remain — Phase 4 (policies + audit) covers it.
- **Follow-ups:**
  - Phase 4 will introduce a `UserPolicy` to formalize this; the FormRequest authorize() can then delegate to the policy.
  - `routes/web.php` `Route::resource('users', ...)` should additionally carry a `permission:users.edit` middleware once granular permissions exist (Phase 4).
  - ProfileController self-edit paths should be audited in Phase 4 alongside the policy work.

## Implementation notes

Files touched in Phase 0.1:
- [app/Http/Requests/StoreUserRequest.php](../../../app/Http/Requests/StoreUserRequest.php) — `authorize()` now `$this->user()?->isAdmin() ?? false`
- [app/Http/Requests/UpdateUserRequest.php](../../../app/Http/Requests/UpdateUserRequest.php) — same
- [app/Http/Controllers/UserController.php](../../../app/Http/Controllers/UserController.php):
  - `update()` only assigns `role_id` when caller is admin (defense in depth)
  - `destroy()` now `abort(403)` for non-admin callers — caught by code review on 2026-04-29; previously a non-admin could DELETE any user (including the only admin), causing permanent loss of administrative access
- [tests/Feature/UserAuthorizationTest.php](../../../tests/Feature/UserAuthorizationTest.php) — 8 regression tests, all passing (admin/non-admin × create/update/delete + unauthenticated coverage + headline self-promotion test)

Incidental fixes (required to get the test suite running on sqlite-in-memory):
- [phpunit.xml](../../../phpunit.xml) — uncommented sqlite + `:memory:` env
- [database/migrations/2026_04_13_155610_create_sessions_table.php](../../../database/migrations/2026_04_13_155610_create_sessions_table.php) — idempotent `up()` (skip if table already exists, since the Laravel skeleton migration also creates it)
- [database/migrations/2026_04_14_210000_add_queue_position_to_visits_table.php](../../../database/migrations/2026_04_14_210000_add_queue_position_to_visits_table.php) — skip MySQL-only backfill when the visits table is empty (fresh installs / sqlite tests)
- [database/migrations/2026_04_15_000912_add_status_to_events_table.php](../../../database/migrations/2026_04_15_000912_add_status_to_events_table.php) — same pattern (CURDATE backfill skipped when empty)
- [tests/Feature/ExampleTest.php](../../../tests/Feature/ExampleTest.php) — Laravel stub updated to tolerate auth-redirect (200 or 302 acceptable)

These migration tweaks are real portability fixes and would also have surfaced on any fresh deploy. They're noted as a Phase 0.1 deviation in `LOG.md`.
