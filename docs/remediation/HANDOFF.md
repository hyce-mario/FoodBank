# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-30 (Session 4, **Phase 2.1.a closed**)

### Where we are

**Phase 1 is fully complete** (tagged). **Phase 2.1.a is done** — `DistributionPostingService` skeleton + `InsufficientStockException` committed to `main` (14e1fd7). Suite is green at 77/77.

The next call is **Phase 2.1.b — bag-composition resolver**. This is where the stub `resolveBagComposition()` gets replaced with real data. It requires a design decision (see open question below) that must be surfaced to the user before writing a line of code.

### Active branch

`main` — 2.1.a just committed directly to main. No feature branch yet for 2.1.b.

> **Note on branching:** HANDOFF previously said to cut `phase-2.1/distribution-posting-service` off main before starting. Since 2.1.a landed directly on main in this session, confirm with the user whether to cut a branch for 2.1.b–f or continue landing sub-tasks on main.

### Tags on main (pushed to origin)

- `phase-1.1-complete` (queue race conditions + reorder hardening)
- `phase-1.2-complete` (visit-households snapshot demographics)
- `phase-1.3-complete` (one-visit-per-event guard + override flow + auth_code_length fix)

*(No phase-2.x tag yet — 2.1 is in-progress.)*

### What's done in Phase 2

- ✅ **2.1.a** — `DistributionPostingService` skeleton + `InsufficientStockException` + 4 unit tests (14e1fd7)

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

**Phase 2.1.b — bag-composition resolver from `AllocationRuleset`.**

Before writing a single line, surface the open question below to the user and get a decision. Then:

1. Implement the chosen schema (migration + model, or JSON extension).
2. Fill in `DistributionPostingService::resolveBagComposition(Visit $visit): array` to query the real composition.
3. Add tests that use the real resolver path (not the anonymous-subclass injection trick from 2.1.a) — including the deferred M5 test: two real items where the second has insufficient stock → both movements rolled back.

**Critical open question — must be answered before 2.1.b:**

The `AllocationRuleset` model today only has `getBagsFor(int $size): int` which returns a *count of bags* for a household size. There is no schema for *what's in a bag* — which inventory items, how many of each. Two options:

- **Option A: new `allocation_ruleset_components` table** — one row per item per ruleset, with `qty_per_bag` (integer). Clean relational model; easy to query; requires a migration and a new model.
- **Option B: extend `AllocationRuleset.rules` JSON** — embed item composition inside the existing JSON alongside the min/max/bags rules, e.g. `{"min":1,"max":1,"bags":1,"components":[{"inventory_item_id":3,"qty_per_bag":2}]}`. No new table; denser; harder to query and validate.

**Do not choose unilaterally. Ask the user.**

### Phase 2 sub-task status
- ✅ **2.1.a** Service skeleton + unit tests (14e1fd7)
- ⬜ **2.1.b** Bag-composition resolver from `AllocationRuleset` — **BLOCKED on schema decision**
- ⬜ **2.1.c** Hook into `markLoaded` happy path
- ⬜ **2.1.d** Hook into supervisor override path (`VisitMonitorController`)
- ⬜ **2.1.e** `InsufficientStockException` UX (modal with skip/substitute/cancel)
- ⬜ **2.1.f** Backfill + reconciliation artisan command (`inventory:reconcile {event}`)
- ⬜ **2.2** Nightly reconciliation schedule

### Key implementation details from 2.1.a (carry into 2.1.b–f)

- `postForVisit(Visit $visit): void` is the single public entry point.
- Inside `DB::transaction`, iterates `resolveBagComposition()` result: each component is `['inventory_item_id' => int, 'quantity' => int]` where `quantity` is the **total for this visit** (not per-household — the resolver handles multiplication).
- `InventoryItem::lockForUpdate()->findOrFail($itemId)` serialises concurrent calls for the same item.
- `InsufficientStockException` is thrown *before* any movement is written — the transaction has no partial state to roll back.
- `EventInventoryAllocation::where(...)->increment(...)` is a delta SQL UPDATE (no row lock needed; immune to phantom reads because it is not a SELECT-then-UPDATE).
- `inventory_movements` schema: no `visit_id` column. Movement is linked to event only.
- `resolveBagComposition()` is `protected` — anonymous subclass injection in tests is the approved pattern for this service.

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

- **2.1.b is blocked** on the bag-composition schema decision. Surface to user at start of next step.
- **2.1.f backfill scope**: historical exited visits — backfill `event_distributed` movements for them, or leave history alone and only post forward? Audit says "**only if** ops confirms historical data was zeroed elsewhere." Confirm with user before 2.1.f.

### User's pre-existing uncommitted work

Many files remain modified/untracked from before Phase 0 began. Phase 2 will likely pull in for the first time:
- `app/Http/Controllers/EventDayController.php` (untracked; 2.1.c hooks `markLoaded` here)
- `app/Http/Controllers/VisitMonitorController.php` (untracked; 2.1.d hooks the supervisor override path)
- Possibly `app/Models/EventInventoryAllocation.php`, `app/Models/InventoryItem.php`, `app/Models/InventoryMovement.php`, `app/Models/AllocationRuleset.php`

**Stage explicitly via `git add <path>`** — never `git add .`.

### Open questions for the user
- **Bag composition schema** (2.1.b prerequisite, BLOCKER): new `allocation_ruleset_components` table (Option A), or extend `AllocationRuleset.rules` JSON (Option B)? Ask before doing anything.
- **Backfill scope** (2.1.f): historical exited visits — forward-only or include history?

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

Session 4 was focused: read all docs for context, then implemented Phase 2.1.a (InsufficientStockException + DistributionPostingService skeleton + 4 tests). Code-review pass confirmed M1–M4 were non-issues; M5 deferred. Committed 14e1fd7.

Recent commits on main (since Phase 1.3 merge):
- `14e1fd7` — feat(inventory): Phase 2.1.a — DistributionPostingService skeleton + unit tests
- `bdefe07` — feat(auth): EventDayOrAuth middleware lets public intake call /checkin/*
- `3402051` — fix(register): treat same-day events as still-current, not past
- `a3264d3` — fix(event-day): make all 4 role pages subdir-deployment-aware
- `4237f5d` — docs(remediation): record post-1.3 polish + walkthrough constraints in HANDOFF

The "family tag" pattern (Alpine x-data scoped popover, member count + 3 colored dots for children/adults/seniors with pluralization) lives in `resources/views/checkin/index.blade.php` at 4 sites. User calls it the **family tag**.
