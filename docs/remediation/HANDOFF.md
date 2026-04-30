# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-30 (Session 4, **Phase 2.1.a + 2.1.b closed**)

### Where we are

**Phase 1 is fully complete** (tagged). **Phase 2.1.a and 2.1.b are done** — `DistributionPostingService` with a live resolver committed to `main` (79531ee). Suite is green at 83/83.

The next call is **Phase 2.1.c — hook `postForVisit` into `EventDayController::markLoaded`**. This is the wiring step: when a loader marks a visit as loaded, the service posts the inventory deduction automatically. No new schema needed.

### Active branch

`main` — 2.1.a and 2.1.b committed directly to main. No feature branch cut yet.

> **Note on branching:** Sub-tasks 2.1.a and 2.1.b landed directly on main this session. The original HANDOFF said to cut `phase-2.1/distribution-posting-service`. Confirm with user before 2.1.c whether to cut a branch now or continue on main through 2.1.f then merge+tag.

### Tags on main (pushed to origin)

- `phase-1.1-complete` (queue race conditions + reorder hardening)
- `phase-1.2-complete` (visit-households snapshot demographics)
- `phase-1.3-complete` (one-visit-per-event guard + override flow + auth_code_length fix)

*(No phase-2.x tag yet — 2.1 is in-progress.)*

### What's done in Phase 2

- ✅ **2.1.a** — `DistributionPostingService` skeleton + `InsufficientStockException` + 4 unit tests (14e1fd7)
- ✅ **2.1.b** — `allocation_ruleset_components` table + `AllocationRulesetComponent` model + live `resolveBagComposition()` + 6 resolver tests (79531ee)

### What's done in Phase 1

- ✅ **1.1.a** — Unique index `(event_id, lane, queue_position)` on visits
- ✅ **1.1.b** — `EventCheckInService::checkIn` transaction + `lockForUpdate`
- ✅ **1.1.c.1** — `queue_position` nullable + null-on-exit
- ✅ **1.1.c.2** — `EventDayController::reorder` + `VisitReorderService` with optimistic versioning
- ✅ **1.1.c.3** — `VisitMonitorController::reorder` swap to shared service
- ✅ **1.2.a** — Snapshot columns on `visit_households` + `withPivot()`
- ✅ **1.2.b** — Snapshot at attach time + NOT NULL flip + shared `Household::toVisitPivotSnapshot()`
- ✅ **1.2.c** — `ReportAnalyticsService` switched to pivot-snapshot reads
- ✅ **1.3.a** — Re-check-in policy setting + `HouseholdAlreadyServedException` (3-mode user extension)
- ✅ **1.3.b** — `CheckInController::store` catch + 422 override-modal payload
- ✅ **1.3.c** — `checkin_overrides` table + `CheckInOverride` model (replaces `Log::warning`)
- ✅ **1.3.d** — Override modal in `checkin/index.blade.php` (Alpine.js)
- ✅ **drive-by fix** — Removed configurable `auth_code_length`; pinned to `Event::AUTH_CODE_LENGTH = 4`

### What's next — start here on resume

**Phase 2.1.c — hook `postForVisit` into `EventDayController::markLoaded`.**

When a loader taps "Loaded" on a visit, `EventDayController::markLoaded()` transitions the visit to `visit_status = 'loaded'` and sets `loading_completed_at`. Phase 2.1.c adds a call to `DistributionPostingService::postForVisit($visit)` immediately after the status transition, still inside the same request.

Key decisions for 2.1.c:
1. **Where to call**: inside `EventDayController::markLoaded()` after the visit update, or inside a dedicated `EventCheckInService::markLoaded()` method that the controller calls. The "thin controller" pattern says to put it in the service, but `markLoaded` is currently controller logic.
2. **Error handling**: if `postForVisit` throws `InsufficientStockException`, the loader sees what? The spec says 2.1.e handles the UX modal — for now, 2.1.c should return a structured 422 or a flash error (the same pattern as 1.3.b did for `HouseholdAlreadyServedException`), not a 500.
3. **`EventDayController.php` is currently untracked** — this commit will pull it into version control for the first time (same organic pattern as prior phases).

Before starting 2.1.c, read `EventDayController::markLoaded()` to understand the current flow.

### Phase 2 sub-task status
- ✅ **2.1.a** Service skeleton + unit tests (14e1fd7)
- ✅ **2.1.b** Bag-composition resolver from `AllocationRuleset` (79531ee)
- ⬜ **2.1.c** Hook into `markLoaded` happy path
- ⬜ **2.1.d** Hook into supervisor override path (`VisitMonitorController`)
- ⬜ **2.1.e** `InsufficientStockException` UX (modal with skip/substitute/cancel)
- ⬜ **2.1.f** Backfill + reconciliation artisan command (`inventory:reconcile {event}`)
- ⬜ **2.2** Nightly reconciliation schedule

### Key implementation details from 2.1.a–b (carry into 2.1.c–f)

- `postForVisit(Visit $visit): void` is the single public entry point.
- Inside `DB::transaction`, iterates `resolveBagComposition()` result: each component is `['inventory_item_id' => int, 'quantity' => int]` where `quantity` is the **total for this visit** (not per-household — the resolver handles multiplication).
- `InventoryItem::lockForUpdate()->findOrFail($itemId)` serialises concurrent calls for the same item.
- `InsufficientStockException` is thrown *before* any movement is written — the transaction has no partial state to roll back.
- `EventInventoryAllocation::where(...)->increment(...)` is a delta SQL UPDATE (no row lock needed; immune to phantom reads because it is not a SELECT-then-UPDATE).
- `inventory_movements` schema: no `visit_id` column. Movement is linked to event only.
- `resolveBagComposition()` is `protected` — anonymous subclass injection in tests is the approved pattern for this service.
- `allocation_ruleset_components` FK on `inventory_item_id` is `restrictOnDelete` (deleting an item raises a DB error if any component references it — reviewer-caught fix).
- Explicit unique index name `arc_ruleset_item_unique` (auto-name exceeded MySQL 64-char identifier limit — discovered on first migration run).

### Branch / merge guidance

Per the established convention:
- Per-phase branch name: `phase-N.M/short-descriptive-name`
- `--no-ff` merge with title `Merge Phase N.M (short description)`
- Tag `phase-N.M-complete` on the merge commit, push tag

### Environment state

- PHP 8.2.12 via XAMPP, working directory `c:\xampp\htdocs\Foodbank`.
- MySQL DB `foodbank` is the dev DB. **Migrations applied to MySQL through Phase 1**: 1.1.a, 1.1.c.1, 1.2.a, 1.2.b, 1.3.c (`checkin_overrides`), drive-by `remove_auth_code_length_setting_row`. Phase 2.1.b will introduce a new migration — take a mysqldump backup first.
- Tests use sqlite `:memory:`. **77 tests passing** on main.
- Node/npm not installed. Phase 2.1.e has a UI modal — server-rendered Alpine.js Blade is fine, no Vite needed (same pattern used for Phase 1.3.d override modal).
- Windows scheduled task `FoodBank Schedule Runner` runs `php artisan schedule:run` every minute, hidden (LogonType=S4U as of 2026-04-30).
- Git identity per-command: `-c user.name="Tobby" -c user.email="digienergy0@gmail.com"`.

### In-flight files / unfinished work

None from Phase 1. Phase 2.1.a added three new tracked files:
- `app/Exceptions/InsufficientStockException.php`
- `app/Services/DistributionPostingService.php`
- `tests/Feature/DistributionPostingServiceTest.php`

### Blockers

None for 2.1.c. The bag-composition schema decision is made (Option A, done).

- **2.1.f backfill scope**: historical exited visits — backfill `event_distributed` movements, or leave history alone? Audit says "**only if** ops confirms historical data was zeroed elsewhere." Confirm with user before 2.1.f.

### User's pre-existing uncommitted work

Many files remain modified/untracked from before Phase 0 began. 2.1.b pulled in `AllocationRuleset.php` (first time tracked). Still to pull in:
- `app/Http/Controllers/EventDayController.php` (untracked; 2.1.c hooks `markLoaded` here)
- `app/Http/Controllers/VisitMonitorController.php` (untracked; 2.1.d hooks the supervisor override path)
- Possibly `app/Models/EventInventoryAllocation.php`, `app/Models/InventoryItem.php`, `app/Models/InventoryMovement.php`

**Stage explicitly via `git add <path>`** — never `git add .`.

### Open questions for the user
- **Backfill scope** (2.1.f prerequisite): historical exited visits — backfill `event_distributed` movements for them, or forward-only? Surface before 2.1.f.

### ADR index
- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only

### Constraints discovered during the Phase 1.3.d browser walkthrough (2026-04-30)

These are *environmental* constraints, not phase work. Future-you should be aware before adding any UI:

- **Tailwind classes must be verified against the prebuilt CSS.** Node/npm aren't installed, so `public/build/assets/app-*.css` is frozen. Any new Tailwind class not already referenced somewhere in the project's source CSS renders as nothing. Bad: `sm:max-w-md`, `bg-amber-600`, `hover:bg-amber-700`, `gap-1.5`, `py-2.5`, `space-y-0.5`, `pb-safe`, `min-w-40`. Good: `sm:max-w-sm`, `max-w-md`, `bg-brand-600 hover:bg-brand-700`, `gap-2`, `py-2`, `space-y-1`, `min-w-32`. **Check:** `grep -o "[.]CLASSNAME" public/build/assets/app-*.css`.
- **Settings pages use hardcoded section blades.** `resources/views/settings/sections/<group>.blade.php` must be edited when adding/removing keys from `SettingService::definitions()`. Adding a key is not enough on its own.
- **JS in checkin/index.blade.php uses `appUrl(path)` for all fetches.** Don't reintroduce raw `fetch('/checkin/...')` — breaks under subdirectory deployment. The `event-day` and `monitor` blades likely have the same latent bug; fix when next touching those views.

### Coverage gaps and known issues (carry forward)

- **HTTP feature tests for event-day routes** (markExited, transition, EventDayController::reorder) deferred to Phase 5. Phase 2.1.c will add hooks here — may need closing if `markLoaded` changes break observable behavior.
- **Pre-existing quirk in monitor.blade.php**: loader column's `onEnd` calls `sendReorder()` reading `#scanner-list`, not `#loader-list`.
- **Monitor route is `auth`-only** (no `permission:` middleware).
- **(carried from 1.2.c)** `overview()` / `overviewTrend()` / `trends()` regression coverage gap: MySQL-only SQL doesn't run on sqlite test DB.
- **(carried from 1.3.d)** Browser-level coverage gap on the override modal: PHPUnit doesn't exercise the Alpine.js JS path. Future Phase 5 Dusk tests.
- **(carried from 1.3.c)** PII retention TODO on `checkin_overrides.reason`: Phase 4 audit-log viewer will need a retention policy + purge job.
- **A11y on Alpine modals**: missing `role="dialog"`, `aria-modal`, focus trap. Phase 5 a11y pass.
- **(from 2.1.a reviewer, M5)** No test for two real items where the second has insufficient stock → first rolled back. Deferred to 2.1.b when the resolver is live.

### Working rules carried across sessions
- **Thoroughness over speed.** Decompose any sub-task touching >4 files into smaller commits.
- **Migration safety.** `mysqldump` before destructive operations; every migration has working `down()`; skip-on-empty patterns for backfills.
- **Code-reviewer subagent before each commit.** Findings have been load-bearing in every phase — keep doing this.
- **Commit messages reference `AUDIT_REPORT.md` Part/Phase.** ADRs for non-obvious decisions; Deviations log in LOG.md for everything that diverges from spec.
- **Subagent delegation for read-only research** to keep main context lean.
- **Stage Phase paths explicitly** — never `git add .`. Lots of unrelated uncommitted work in the tree.
- **Plain-English orientation before each step**: explain what's about to happen and why, framed in food-bank-operational terms, before tool calls.

### Context budget at handoff

Session 4: read all docs for context, implemented Phase 2.1.a (InsufficientStockException + DistributionPostingService skeleton + 4 tests) and Phase 2.1.b (allocation_ruleset_components table + live resolver + 6 tests). Code-review on 2.1.b fixed inventory_item_id FK from cascadeOnDelete → restrictOnDelete. MySQL 64-char index-name limit discovered on first migration run (fixed with explicit name). 83/83.

Recent commits on main (since Phase 1.3 merge):
- `79531ee` — feat(inventory): Phase 2.1.b — bag-composition resolver from AllocationRuleset
- `14e1fd7` — feat(inventory): Phase 2.1.a — DistributionPostingService skeleton + unit tests
- `bdefe07` — feat(auth): EventDayOrAuth middleware lets public intake call /checkin/*
- `3402051` — fix(register): treat same-day events as still-current, not past
- `a3264d3` — fix(event-day): make all 4 role pages subdir-deployment-aware
- `4237f5d` — docs(remediation): record post-1.3 polish + walkthrough constraints in HANDOFF

The "family tag" pattern (Alpine x-data scoped popover, member count + 3 colored dots for children/adults/seniors with pluralization) lives in `resources/views/checkin/index.blade.php` at 4 sites. User calls it the **family tag**.
