# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-30 (Session 4, **Phase 2 fully closed**)

### Where we are

**Phases 1 and 2 are fully complete and tagged.** Suite is green at 100/100.

Phase 2 delivered:
- `DistributionPostingService` with live bag-composition resolver (alloc_ruleset_components → pivot snapshots → movements)
- `markLoaded` (loader tablet + supervisor monitor) auto-deducts inventory on visit completion
- Insufficient-stock modal with Skip/Cancel on both pages
- `inventory:reconcile {event}` artisan command (dry-run + `--post` backfill)
- Nightly reconciliation schedule (`inventory:reconcile-nightly` daily at 00:05) with `InventoryReconcileAlert` mailable

The next call is **Phase 3 — Public-surface hardening.** See sub-task status below.

### Active branch

`main` — Phase 2 commits landed directly on main. Tag `phase-2-complete` pushed.

### Tags on main

- `phase-1.1-complete`
- `phase-1.2-complete`
- `phase-1.3-complete`
- `phase-2-complete` ← new

### What's done in Phase 2

- ✅ **2.1.a** — `DistributionPostingService` skeleton + `InsufficientStockException` (14e1fd7)
- ✅ **2.1.b** — `allocation_ruleset_components` table + live `resolveBagComposition()` (79531ee)
- ✅ **2.1.c** — Hook into `EventDayController::markLoaded` (5c93d45)
- ✅ **2.1.d** — Hook into `VisitMonitorController::transition` (71d3cac)
- ✅ **2.1.e** — Insufficient-stock modal (skip/cancel) on loader + monitor (b2d6506)
- ✅ **2.1.f** — `inventory:reconcile {event}` artisan command (50f7b6c)
- ✅ **2.2** — Nightly reconciliation schedule + `InventoryReconcileAlert` mailable (7d9de17)

### What's next — start here on resume

**Phase 3 — Public-surface hardening.**

Three sub-phases, in order:

**3.1 Rate limits on public POST endpoints** (fastest, no migration):
- Public endpoints that need rate limits: `POST /register/{event}` (EventPreRegistration), `POST /review/` (PublicReviewController).
- Add Laravel `ThrottleRequests` middleware per the Phase 3 spec: 6 attempts per 1 minute per IP.
- Acceptance: "6th attempt within 1 min returns 429."

**3.2 Hashed event auth codes** (multi-step migration):
- 3.2.a: Migration — add `intake_auth_code_hash` etc. columns (nullable, hashed storage).
- 3.2.b: Code generation → 6 alphanumeric chars, stored as `Hash::make()`.
- 3.2.c: Verification switches from `===` comparison to `Hash::check()`.
- 3.2.d: Migration — drop plaintext columns after grace period.

**3.3 Mass-assignment cleanup** (UserController + EventReview):
- Public `POST /review/` must not allow `is_visible=1` to be set by the submitter.
- Acceptance: "Public POST /review with `is_visible=1` is ignored."

Before starting Phase 3, confirm with the user:
- **Phase 3.2 grace period**: how long to keep both plaintext and hashed columns live? (i.e. how long before 3.2.d runs in production)
- **Phase 3.1 route identification**: confirm which public routes need rate limiting (the public intake check-in route `/checkin` is behind auth-or-event-session, not fully public).

### Phase 3 sub-task status
- ⬜ **3.1** Rate limits on public POST endpoints
- ⬜ **3.2.a** Migration: hashed code columns
- ⬜ **3.2.b** Code generation → 6 alphanumeric, hashed
- ⬜ **3.2.c** Verification with `Hash::check` + constant-time
- ⬜ **3.2.d** Migration: drop plaintext columns
- ⬜ **3.3** Mass-assignment cleanup

### Key Phase 2 implementation details (carry forward)

- `allocation_ruleset_components` FK on `inventory_item_id` is `restrictOnDelete` (deleting an item raises DB error if any component references it).
- Unique index name `arc_ruleset_item_unique` (MySQL 64-char identifier limit — auto-name was 76 chars).
- `resolveBagComposition()` is `protected`; use `compositionForVisit()` (public wrapper) from outside the service.
- `skip_inventory=1` in the PATCH body bypasses `postForVisit()` — used by the modal's Skip path.
- `InventoryReconcileAlert` Mailable uses `Mail::to()->send()` (NOT `Mail::raw()` — raw bypasses MailFake interception in tests).
- The Phase 2.1.e modal's "Substitute" option was deferred — both modals show "Substitute item: coming soon."

### Environment state

- PHP 8.2.12 via XAMPP, `c:\xampp\htdocs\Foodbank`.
- MySQL dev DB. **Migrations applied through Phase 2**: all Phase 1 migrations + `2026_04_30_190000_create_allocation_ruleset_components_table`. Phase 3.2 will need new migrations — take mysqldump backup first.
- Tests use sqlite `:memory:`. **100 tests passing** on main.
- Node/npm not installed — prebuilt CSS constraint still applies (see constraints section).
- Windows scheduled task `FoodBank Schedule Runner` runs `php artisan schedule:run` every minute. `inventory:reconcile-nightly` now runs at 00:05 daily via this task.
- Git identity: `-c user.name="Tobby" -c user.email="digienergy0@gmail.com"`.

### In-flight files / unfinished work

None. Phase 2 is fully closed.

### Blockers

None. Phase 3 can start immediately after reading AUDIT_REPORT.md Part 13 §3.

### User's pre-existing uncommitted work

`AllocationRuleset.php`, `AllocationRulesetComponent.php`, `EventDayController.php`, `VisitMonitorController.php` were pulled into version control during Phase 2. Likely still untracked:
- Various other controllers in `app/Http/Controllers/`
- Various models not yet touched by Phase 1–2 work

**Stage explicitly via `git add <path>`** — never `git add .`.

### Open questions for the user
- **Backfill scope** (Phase 2.1.f prerequisite for historical events): forward-only or backfill pre-Phase-2 events? Audit says "only if ops confirms historical data was zeroed elsewhere."
- **Phase 3.2 grace period**: how long to run dual (plaintext + hash) columns before dropping plaintext?

### ADR index
- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only

### Constraints discovered during prior phases (carry forward)

- **Tailwind classes must be verified against the prebuilt CSS.** `public/build/assets/app-*.css` is frozen. Bad: `sm:max-w-md`, `bg-amber-600`, `hover:bg-amber-700`, `gap-1.5`, `py-2.5`, `min-w-40`. Good: `sm:max-w-sm`, `max-w-md`, `bg-brand-600 hover:bg-brand-700`, `gap-2`, `py-2`, `min-w-32`. Check: `grep -o "[.]CLASSNAME" public/build/assets/app-*.css`.
- **Settings pages use hardcoded section blades.** Adding a key to `SettingService::definitions()` requires also editing `resources/views/settings/sections/<group>.blade.php`.
- **JS in checkin/index.blade.php uses `appUrl(path)`.** Don't reintroduce raw fetch paths — breaks subdirectory deployment.
- **IDE diagnostics in monitor/loader blades are false positives.** The TypeScript LSP misreads Blade directives in `<script>` blocks. Not real errors.

### Coverage gaps and known issues (carry forward)

- HTTP feature tests for event-day routes (markExited, EventDayController::reorder) deferred to Phase 5.
- Monitor route is `auth`-only (no `permission:` middleware).
- `overview()` / `overviewTrend()` / `trends()` MySQL-only SQL — no sqlite test coverage.
- Override modal (1.3.d) + insufficient-stock modal (2.1.e) not tested at browser level — PHP unit can't exercise Alpine/vanilla JS. Phase 5 Dusk.
- PII retention TODO on `checkin_overrides.reason` — Phase 4.
- A11y on Alpine modals — Phase 5.
- Substitute option on insufficient-stock modal — deferred, both modals say "coming soon."

### Working rules carried across sessions
- Thoroughness over speed; sub-tasks touching >4 files get split into smaller commits.
- `mysqldump` before any schema migration; every migration has working `down()`.
- Code-reviewer subagent before each commit.
- Commit messages reference `AUDIT_REPORT.md` Part/Phase. ADRs for non-obvious decisions.
- Subagent delegation for read-only research.
- Stage explicitly via `git add <path>` — never `git add .`.
- Plain-English orientation before each step, framed in food-bank-operational terms.

### Context budget at handoff

Session 4 was long: covered Phase 2 entirely (2.1.a–f + 2.2) plus Phase 2.1.e skip-inventory modal on two blades. Encountered and fixed: MySQL 64-char index name limit (2.1.b), `Mail::raw()` bypassing MailFake (2.2). 100/100.

Recent Phase 2 commits on main:
- `7d9de17` — feat(inventory): Phase 2.2 — nightly reconciliation schedule
- `50f7b6c` — feat(inventory): Phase 2.1.f — inventory:reconcile artisan command
- `b2d6506` — feat(inventory): Phase 2.1.e — InsufficientStockException UX modal
- `71d3cac` — feat(inventory): Phase 2.1.d — hook postForVisit into supervisor transition
- `5c93d45` — feat(inventory): Phase 2.1.c — hook postForVisit into markLoaded
- `79531ee` — feat(inventory): Phase 2.1.b — bag-composition resolver from AllocationRuleset
- `14e1fd7` — feat(inventory): Phase 2.1.a — DistributionPostingService skeleton + unit tests
