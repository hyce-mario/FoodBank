# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-30 (Session 4, **Phases 1–3 fully closed**)

### Where we are

**Phases 1, 2, and 3 are fully complete and tagged.** Suite is green at 107/107.

Phase 3 delivered:
- **3.1**: `throttle:5,1` on all public POST endpoints; custom `auth-code` rate limiter (IP+role+event, 5/min) on auth-code submission. Code-review catch: `$request->route('event')?->getKey()` crashes because route model binding fires after throttle middleware — fixed with `is_object()` guard.
- **3.2.a-c**: 6-char uppercase alphanumeric auth codes (36⁶ ≈ 2B), bcrypt-hashed at rest, verified via `Hash::check()`. Widen char(4)→char(6), add `*_auth_code_hash` columns, backfill all upcoming/current events.
- **3.2.d**: Drop plaintext columns. Codes shown once via session flash (`new_auth_codes`) after create/regenerate. Code-review catch: validator still said `size:4` — blocked every valid 6-char code with 422 before hash check; fixed to `size:Event::AUTH_CODE_LENGTH`.
- **3.3**: Remove `is_visible` from `EventReview::$fillable`; fix `PublicReviewController::store()` and `ReviewController::toggleVisibility()` to use direct property assignment.

The next call is **Phase 4 — Authorization & Audit.**

### Active branch

`main` — Phase 3 commits landed directly on main. Tag `phase-3-complete` pushed.

### Tags on main

- `phase-1.1-complete`, `phase-1.2-complete`, `phase-1.3-complete`
- `phase-2-complete`
- `phase-3-complete` ← new

### What's next — start here on resume

**Phase 4 — Authorization & Audit.** Two sub-phases, both meaty:

**4.1 — Resource policies (HouseholdPolicy + others):**
- `php artisan make:policy HouseholdPolicy --model=Household` (plus Visit, Review, Event, Volunteer).
- Implement `viewAny`, `view`, `create`, `update`, `delete` per role.
- Replace bare `Model::find($id)` with `$this->authorize()` in every controller method.
- Acceptance: INTAKE-role user gets 403 trying to PUT `/households/{id}`.

**4.2 — Audit log:**
- New `audit_logs` table: `(id, user_id, action, target_type, target_id, before_json, after_json, ip, user_agent, created_at)`.
- `Auditable` trait: attaches model `saving`/`deleting` events to write rows.
- Apply to: `User`, `Role`, `AppSetting`, `Household`, `Visit` (status overrides), `EventInventoryAllocation`.
- Admin-only `/audit-logs` page with filters (who/when/what).

Before starting Phase 4, note: the `checkin_overrides` table from Phase 1.3.c is a precursor — Phase 4 may absorb it into `audit_logs` or leave it as a specialized table. Surface this to the user before implementing the audit log structure.

### Phase 4 sub-task status
- ⬜ **4.1.a** HouseholdPolicy + register
- ⬜ **4.1.b** VisitPolicy / EventPolicy / ReviewPolicy / VolunteerPolicy
- ⬜ **4.1.c** Replace bare `find` with policy-checked `findOrFail` + `authorize`
- ⬜ **4.2.a** Migration: `audit_logs` table
- ⬜ **4.2.b** `Auditable` trait + apply to models
- ⬜ **4.2.c** Admin `/audit-logs` page with filters

### Key Phase 3 learnings (carry forward)

- **Throttle middleware fires before route model binding.** `$request->route('event')` returns a raw string ID inside a `RateLimiter::for()` closure — use `is_object($event) ? $event->getKey() : $event` defensively.
- **Auth code validator must match `Event::AUTH_CODE_LENGTH`.** Use `'size:' . Event::AUTH_CODE_LENGTH` in the request validator so a future length change doesn't silently break auth. The `size:4` → `size:6` oversight was caught by code review.
- **`Mail::raw()` bypasses `Mail::fake()`** — use `Mail::to()->send(new Mailable)` for testable email.
- **`allocation_ruleset_components` index name limit**: MySQL 64-char identifier limit; explicit short names required.
- **Phase 3.2.d "show once" pattern**: `EventController::store()` pre-generates codes before `Event::create()` and passes hashes in `$data`. Boot observer sees hashes set and skips. Plaintext flushed via `->with('new_auth_codes', ...)` redirect flash.

### Environment state

- PHP 8.2.12 via XAMPP, `c:\xampp\htdocs\Foodbank`.
- MySQL dev DB. **All Phase 1–3 migrations applied.** Phase 4.2.a will need a new migration — take mysqldump backup first.
- Tests use sqlite `:memory:`. **107 tests passing** on main.
- Node/npm not installed — prebuilt CSS constraint applies.
- Windows scheduled task runs `php artisan schedule:run` every minute.
- Git identity: `-c user.name="Tobby" -c user.email="digienergy0@gmail.com"`.

### In-flight files / unfinished work

None. Phase 3 is fully closed.

### Blockers

None. Phase 4 can start immediately. See open question about `checkin_overrides` vs `audit_logs` above.

### User's pre-existing uncommitted work

Many files remain modified/untracked. Phase 3 pulled in: `EventController.php`, `EventDayController.php`, `PublicReviewController.php`, `ReviewController.php`, `EventReview.php`, `show.blade.php` (events). More untracked files likely remain in controllers and models.

**Stage explicitly via `git add <path>`** — never `git add .`.

### Open questions for the user
- **`checkin_overrides` vs `audit_logs`** (Phase 4 prerequisite): absorb `checkin_overrides` into the new `audit_logs` table, or keep as a specialized satellite table that feeds into it? Surface before 4.2.a migration design.
- **Backfill scope** (Phase 2.1.f): historical exited visits — forward-only or backfill? Still open.

### ADR index
- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only

### Constraints discovered during prior phases (carry forward)

- **Tailwind prebuilt CSS is frozen.** Check `grep -o "[.]CLASSNAME" public/build/assets/app-*.css`.
- **Settings section blades are hardcoded.** Adding a setting key requires editing the section blade too.
- **JS fetch paths need `appUrl()`** — raw `/checkin/...` breaks subdirectory deployment.
- **IDE Blade/JS false positives** — TypeScript LSP misreads Blade directives in script blocks.

### Coverage gaps (carry forward)

- HTTP feature tests for event-day routes (markExited, EventDayController::reorder) — Phase 5.
- Monitor route is `auth`-only (no `permission:` middleware).
- MySQL-only SQL in ReportAnalyticsService not covered by sqlite tests.
- Override modal + insufficient-stock modal — no browser-level tests (Phase 5 Dusk).
- PII retention on `checkin_overrides.reason` — Phase 4.
- Substitute option on insufficient-stock modal — "coming soon."

### Working rules carried across sessions
- Thoroughness over speed; sub-tasks touching >4 files get split into smaller commits.
- `mysqldump` before any schema migration; every migration has working `down()`.
- Code-reviewer subagent before each commit.
- Commit messages reference `AUDIT_REPORT.md` Part/Phase.
- Stage explicitly — never `git add .`.
- Plain-English orientation before each step.

### Context budget at handoff

Session 4 (continued): completed all of Phase 3 (3.1, 3.2.a-d, 3.3). Two code-review-caught blockers fixed before committing: rate-limiter route parameter (3.1) and size:4 validator (3.2.c/d). 107/107.

Recent commits:
- `1f6d0a1` — feat(security): Phase 3.3 — mass-assignment cleanup
- `d704323` — feat(security): Phase 3.2.d — drop auth code plaintext columns
- `fa79297` — feat(security): Phase 3.2.a-c — lengthen and hash event-day auth codes
- `4cd2399` — feat(security): Phase 3.1 — rate limit public POST endpoints
- `4784032` — docs(remediation): Phase 2 close
