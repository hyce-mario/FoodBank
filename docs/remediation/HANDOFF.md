# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-30 (end of Session 3, **Phase 1 fully closed**)

### Where we are

**Phase 1 is fully complete.** All three sub-phases (1.1 race conditions, 1.2 snapshot demographics, 1.3 one-visit-per-event guard) are merged to `main` and tagged. Suite is green at 67/67 on main.

The next call is **start Phase 2 — Reporting Truth.** This is the biggest single lift in the audit (~3 days estimated; AUDIT_REPORT.md §2). It introduces a brand-new service class (`DistributionPostingService`) and rewires the visit-completion flow to auto-decrement inventory. The primary acceptance criterion is "running a 50-visit event causes `InventoryItem.quantity_on_hand` to decrease by exactly the rule-derived quantity." Everything in Phase 2 hangs off that.

### Active branch

`main` — Phase 1.3 just merged. No active feature branch yet.

### Tags on main (pushed to origin)

- `phase-1.1-complete` (queue race conditions + reorder hardening)
- `phase-1.2-complete` (visit-households snapshot demographics)
- `phase-1.3-complete` (one-visit-per-event guard + override flow + auth_code_length fix)

### What's done in Phase 1
- ✅ **1.1.a** — Unique index `(event_id, lane, queue_position)` on visits
- ✅ **1.1.b** — `EventCheckInService::checkIn` transaction + `lockForUpdate`
- ✅ **1.1.c.1** — `queue_position` nullable + null-on-exit (precondition for safe reorder)
- ✅ **1.1.c.2** — `EventDayController::reorder` + new `VisitReorderService` with optimistic versioning
- ✅ **1.1.c.3** — `VisitMonitorController::reorder` swap to shared service
- ✅ **1.2.a** — Snapshot columns on `visit_households` + `withPivot()`
- ✅ **1.2.b** — Snapshot at attach time + NOT NULL flip + shared `Household::toVisitPivotSnapshot()`
- ✅ **1.2.c** — `ReportAnalyticsService` switched to pivot-snapshot reads
- ✅ **1.3.a** — Re-check-in policy setting + `HouseholdAlreadyServedException` (3-mode user extension)
- ✅ **1.3.b** — `CheckInController::store` catch + 422 override-modal payload
- ✅ **1.3.c** — `checkin_overrides` table + `CheckInOverride` model (replaces `Log::warning`)
- ✅ **1.3.d** — Override modal in `checkin/index.blade.php` (Alpine.js)
- ✅ **drive-by fix** — Removed configurable `auth_code_length` setting; pinned to `Event::AUTH_CODE_LENGTH = 4`

### What's next — start here on resume

**Phase 2.1.a — `DistributionPostingService` skeleton + unit tests.**

Spec: AUDIT_REPORT.md Part 13 §2.1 (lines ~420-432):
1. **Create `app/Services/DistributionPostingService.php`** with a single method `postForVisit(Visit $visit): void`.
2. **Inside a `DB::transaction`:**
   - Resolve event's `AllocationRuleset` and bag composition (define a `bag_composition` schema if not already explicit — see open question below).
   - For each component: `(item_id, qty_per_household × household_count)`.
   - Verify `InventoryItem::lockForUpdate()->find($itemId)->quantity_on_hand >= needed`. If insufficient, throw `InsufficientStockException` and **do not** post the movement.
   - Create `InventoryMovement::create([... 'movement_type' => 'event_distributed' ...])`.
   - Update `EventInventoryAllocation::distributed_quantity += needed` and `InventoryItem::quantity_on_hand -= needed`.

**Concrete plan for 2.1.a (skeleton + unit tests):**

1. Create `app/Exceptions/InsufficientStockException.php` (extends `RuntimeException`). Carries `eventId`, `inventoryItemId`, `needed`, `available` for the controller to render a "skip / substitute / cancel" modal in 2.1.e.
2. Create `app/Services/DistributionPostingService.php` with the `postForVisit(Visit $visit): void` skeleton. Implementation can stub the bag-composition resolver in 2.1.a (return hardcoded `[]` or throw NotImplemented) and fill it in 2.1.b — split per the established sub-task pattern.
3. Service tests: empty-allocation (no posting, no error), happy-path (one item, correct movement created), insufficient stock (throws + rolls back), transaction rollback proof (FK violation on InventoryMovement → no allocation update).

**Critical open question for the user before 2.1.b:**
- The audit spec says "Resolve event's `AllocationRuleset` and bag composition" but the `AllocationRuleset` model today only has a `getBagsFor(int $size)` method returning a number of bags. There's no schema for *what's in a bag* (which inventory items, how many of each). The audit explicitly says **"define a `bag_composition` schema if not already explicit"**. So 2.1.b will require either:
  - A new `allocation_ruleset_components` table (one row per item-per-ruleset, with qty per household), OR
  - Embedding bag composition in the existing `AllocationRuleset.rules` JSON column (denser but harder to query).
- This is a meaningful design decision that should not be made unilaterally. **Surface this question early in 2.1.b.**

### Phase 2 sub-task status
- ⬜ **2.1.a** Service skeleton + unit tests
- ⬜ **2.1.b** Bag-composition resolver from `AllocationRuleset`
- ⬜ **2.1.c** Hook into `markLoaded` happy path
- ⬜ **2.1.d** Hook into supervisor override path (`VisitMonitorController`)
- ⬜ **2.1.e** `InsufficientStockException` UX (modal with skip/substitute/cancel)
- ⬜ **2.1.f** Backfill + reconciliation artisan command (`inventory:reconcile {event}`)
- ⬜ **2.2** Nightly reconciliation schedule

### Branch / merge guidance

Cut `phase-2.1/distribution-posting-service` off the new main. Sub-task commits land on this branch; merge `--no-ff` to main + tag `phase-2.1-complete` once 2.1.a–f close. 2.2 is a separate sub-phase, separate branch.

Per the established convention:
- Per-phase branch name: `phase-N.M/short-descriptive-name`
- `--no-ff` merge with title `Merge Phase N.M (short description)`
- Tag `phase-N.M-complete` on the merge commit, push tag

### Environment state

- PHP 8.2.12 via XAMPP, working directory `c:\xampp\htdocs\Foodbank`.
- MySQL DB `foodbank` is the dev DB. **Migrations applied to MySQL through Phase 1**: 1.1.a, 1.1.c.1, 1.2.a, 1.2.b, 1.3.c (`checkin_overrides`), drive-by `remove_auth_code_length_setting_row`. Phase 2 will need its own backups before any new schema work.
- Tests use sqlite `:memory:`. **67 tests passing** on main.
- Node/npm not installed. Phase 2.1.e has a UI modal — server-rendered Alpine.js Blade is fine, no Vite needed (same pattern used for the Phase 1.3.d override modal).
- Windows scheduled task `FoodBank Schedule Runner` runs `php artisan schedule:run` every minute, hidden (LogonType=S4U as of 2026-04-30).
- Git identity per-command: `-c user.name="Tobby" -c user.email="digienergy0@gmail.com"`.
- Pre-Phase-2 mysqldump backups will be taken before any schema-altering work begins.

### In-flight files / unfinished work

None. Phase 1 is closed; main is clean.

### Blockers

None for 2.1.a. The bag-composition schema decision (see open question above) blocks 2.1.b but not the skeleton.

### User's pre-existing uncommitted work

Many files remain modified/untracked from before Phase 0 began. Phase 1 commits have organically pulled in over a dozen of these (Visit.php, EventCheckInService.php, ReportAnalyticsService.php, DemoSeeder.php, SettingService.php, CheckInController.php, CheckInRequest.php, checkin/index.blade.php, settings/sections/public_access.blade.php, Event.php, etc.) as the work touched them.

Phase 2 will likely pull in for the first time:
- `app/Services/InventoryService.php` (untracked; if 2.1.a integrates with it)
- `app/Http/Controllers/EventDayController.php` (untracked; 2.1.c hooks `markLoaded` here)
- `app/Http/Controllers/VisitMonitorController.php` (untracked; 2.1.d hooks the supervisor override path)
- Possibly `app/Models/EventInventoryAllocation.php`, `app/Models/InventoryItem.php`, `app/Models/InventoryMovement.php`, `app/Models/AllocationRuleset.php`

**Stage explicitly via `git add <path>`** — never `git add .`.

### Open questions for the user
- **Bag composition schema** (2.1.b prerequisite): new `allocation_ruleset_components` table, or extend the existing `AllocationRuleset.rules` JSON? Surface this before starting 2.1.b.
- **Backfill scope** (2.1.f): historical exited visits — backfill `event_distributed` movements for them, or leave history alone and only post forward? Audit says "**only if** ops confirms historical data was zeroed elsewhere." Need to confirm with the user what's been zeroed.
- **(at session start)** verify the override modal manual walkthrough was successful (HANDOFF assumed it was, but if you find a UI bug, fix BEFORE Phase 2).

### ADR index
- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only

(No new ADRs in Phase 1.3 — all reviewer findings logged as Deviations.)

### Coverage gaps and known issues (carry forward)

- **HTTP feature tests for event-day routes** (markExited, transition, EventDayController::reorder) deferred to Phase 5 due to session auth-code scaffolding cost. Phase 2.1.c will add hooks here, so this gap may need closing if changing markLoaded breaks observable behavior in untested ways.
- **Pre-existing quirk in monitor.blade.php**: loader column's `onEnd` calls `sendReorder()` reading `#scanner-list`, not `#loader-list`.
- **Monitor route is `auth`-only** (no `permission:` middleware).
- **(carried from 1.2.c)** `overview()` / `overviewTrend()` / `trends()` regression coverage gap: their MySQL-only SQL doesn't run on the in-memory sqlite test DB. Phase 2 won't directly touch these but should be aware.
- **(carried from 1.3.d)** **Browser-level coverage gap on the override modal**: the JS reading the 422 payload is not exercised by PHPUnit. Future Phase 5 could add Laravel Dusk tests for the check-in flow if browser-level coverage becomes a priority. Manual test plan in commit message of 360e406.
- **(carried from 1.3.c)** PII retention TODO on the `checkin_overrides.reason` column: supervisor free-text may contain household member names. Phase 4's broader `audit_logs` viewer will need a retention policy + purge job.
- **A11y on Alpine modals** (createPanel, override modal): missing `role="dialog"`, `aria-modal`, focus trap. Project-wide gap; address in a Phase 5 a11y pass.

### Working rules carried across sessions
- **Thoroughness over speed.** Decompose any sub-task touching >4 files into smaller commits.
- **Migration safety.** `mysqldump` before destructive operations; every migration has working `down()`; skip-on-empty patterns for backfills.
- **Code-reviewer subagent before each commit.** Findings have been load-bearing in every Phase 1 sub-task — keep doing this.
- **Commit messages reference `AUDIT_REPORT.md` Part/Phase.** ADRs for non-obvious decisions; Deviations log in LOG.md for everything that diverges from spec.
- **Subagent delegation for read-only research** to keep main context lean.
- **Stage Phase paths explicitly** — never `git add .`. Lots of unrelated uncommitted work in the tree.
- **Plain-English orientation before each step** (per user feedback memory `feedback_explain_before_doing.md`): explain what's about to happen and why, framed in food-bank-operational terms, before tool calls. Apply to non-trivial reads too.

### Context budget at handoff

Session 3 ran long: from Phase 1.2 close through all of Phase 1.3 (a, b, c, d) + the auth_code_length drive-by fix + Phase 1.3 merge + this HANDOFF rewrite. Recommend `/clear` and resume from this HANDOFF before starting Phase 2.
