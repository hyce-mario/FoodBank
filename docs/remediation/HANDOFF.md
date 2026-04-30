# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-29 (mid-Session 3, **Phase 1.2 closed**)

### Where we are
**Phase 1.2 is fully complete.** All three sub-tasks (1.2.a schema, 1.2.b service write + NOT NULL, 1.2.c report read switch) committed on `phase-1.2/visit-households-snapshots`. Suite is green at 48/48.

The next call is **a branch decision**:

- **Option A (recommended):** merge `phase-1.2/visit-households-snapshots` to `main` now (`git merge --no-ff`), tag as `phase-1.2-complete`, then start a fresh `phase-1.3/...` branch for the one-visit-per-household guard. Atomic per major sub-phase.
- **Option B:** stay on the same branch and do 1.3 too, then merge once.

User's call. Default to A unless preferring B.

### Active branch
`phase-1.2/visit-households-snapshots` — 5 commits ahead of `main`.

### Commits on this branch (oldest → newest)
On `main` baseline (already pushed):
- `802e955` (merge) — Phase 1.1, tagged `phase-1.1-complete`

On `phase-1.2/visit-households-snapshots`:
- `33d73e2` — Phase 1.2.a: snapshot columns migration + `withPivot()` + 4 tests
- `11dc65d` — docs: 1.2.a SHA backfill
- `42e58b3` — Phase 1.2.b: snapshot at attach + NOT NULL flip + shared helper + 4 service tests
- `7272c23` — docs: 1.2.b SHA backfill
- `7ca9728` — docs: fix 1.1.a/1.1.b SHA placeholders that sed swept
- `f37dc03` — Phase 1.2.c: ReportAnalyticsService snapshot reads + 4 temporal-stability tests

### What's done in Phase 1.2
- ✅ **1.2.a** — Schema + backfill on `visit_households` (108 dev-DB rows backfilled). Pivot columns exposed via `withPivot()`.
- ✅ **1.2.b** — Service writes snapshot at attach via shared `Household::toVisitPivotSnapshot()`. Demographic columns NOT NULL. Bulk-load + existence check.
- ✅ **1.2.c** — `ReportAnalyticsService` reads demographics from `vh.*`; vehicle from `vh.vehicle_make`. Non-snapshotted fields (zip, city, names, household_number) stay live. `exportHouseholds()` deliberately stays live (current-roster semantic). 4 regression tests pin temporal stability.

### What's next — start here on resume

**Phase 1.3** — One-visit-per-household-per-event guard with explicit `force` override.

Spec: AUDIT_REPORT.md Part 13 §1.3 (lines ~406-412):
1. **In `EventCheckInService`**, before attaching: check `Visit::where('event_id', $event->id)->whereHas('households', fn($q) => $q->whereIn('households.id', $allIds))->exists()`. If true and `$force === false`, throw a `HouseholdAlreadyServedException`.
2. **Surface in the check-in UI**: a "this family was already served today — override?" modal.
3. **Log every override** with user id + reason. Phase 4 will formalize `audit_logs`; for now `Log::warning` is sufficient.

**Concrete plan for 1.3:**

1. Add `force` parameter (default false) to `EventCheckInService::checkIn()`. Most existing `checkIn` callers won't pass it.
2. New `App\Exceptions\HouseholdAlreadyServedException` (extends RuntimeException). Carries the offending household IDs and event ID for the controller to render.
3. The check happens **inside** the existing `DB::transaction` wrapping `checkIn` — same lockForUpdate window so no race between "already served" check and insert.
4. Existing `EventCheckInServiceTest::test_already_active_check_in_throws_and_does_not_create_a_second_visit` already pins one variant of this (already-active). Extend to:
   - same household, same event, AFTER exit (currently allowed per `test_re_check_in_after_exit_succeeds_with_next_position`) → now blocks unless force
   - representative pickup where one of the represented IDs already has a visit in the event → blocks
   - `force=true` allows the second check-in and logs the override
5. Update [CheckInController.php](app/Http/Controllers/CheckInController.php) (`checkin.store`) to catch `HouseholdAlreadyServedException` and either return a 422 with the conflict info (for the UI modal) or accept a `force=1` request parameter.
6. UI modal in the check-in flow — flag if Node-blocked (it's a server-rendered blade, no Vite needed).

**Caveat**: The existing `test_re_check_in_after_exit_succeeds_with_next_position` test will need to be updated. It currently asserts that re-check-in after exit succeeds — under 1.3's contract, it succeeds only with `force=true`. That's a behavior change.

### Phase 1.2 sub-task status
- ✅ **1.2.a / 1.2.b / 1.2.c** — all committed and tested. Phase 1.2 closed.

### Branch / merge guidance
After 1.2.c commits, recommend `git merge --no-ff phase-1.2/visit-households-snapshots` to `main`, tag `phase-1.2-complete`, push. Cut `phase-1.3/one-visit-per-event-guard` off the new main.

### Environment state
- PHP 8.2.12 via XAMPP, working directory `c:\xampp\htdocs\Foodbank`.
- MySQL DB `foodbank` is the dev DB. **Migrations applied to MySQL so far in Phase 1**: 1.1.a, 1.1.c.1, 1.2.a, 1.2.b. Demographic columns on `visit_households` are NOT NULL; vehicle stays nullable. Pre-1.2 mysqldump at `backups/foodbank-pre-phase-1.2-20260429-134049.sql`.
- Tests use sqlite `:memory:`. **48 tests passing**.
- Node/npm not installed. Phase 1.3 has a UI modal — server-rendered blade is fine, but a Vue/SPA component would need Node. Confirm shape with user before building.
- Windows scheduled task `FoodBank Schedule Runner` is live (every 1 min).
- Git identity per-command: `-c user.name="Tobby" -c user.email="digienergy0@gmail.com"`.

### In-flight files / unfinished work
None — 1.2.c will commit cleanly.

### Blockers
None for 1.3. Phase 5 UI work will need Node when we get there.

### User's pre-existing uncommitted work
Many controllers, views, services remain modified/untracked. Phase 1 commits have organically pulled in `EventCheckInService.php`, `EventDayController.php`, `VisitMonitorController.php`, `scanner.blade.php`, `loader.blade.php`, `monitor.blade.php`, `Visit.php`, `Household.php`, `DemoSeeder.php`, `ReportAnalyticsService.php`. Phase 1.3 will likely pull in `CheckInController.php` for the first time. Stage explicitly via `git add <path>`.

### Open questions for the user
- **Branch decision before starting 1.3**: merge to `main` first (recommended, atomic per phase) or continue on the same branch?

### ADR index
- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only
- (no new ADRs in 1.2 — all reviewer findings logged as Deviations)

### Coverage gaps and known issues (carry forward)
- **HTTP feature tests for event-day routes** (markExited, transition, EventDayController::reorder) deferred to Phase 5 due to session auth-code scaffolding cost.
- **Pre-existing quirk in monitor.blade.php**: loader column's `onEnd` calls `sendReorder()` reading `#scanner-list`, not `#loader-list`.
- **Monitor route is `auth`-only** (no `permission:` middleware).
- **(NEW) `overview()` / `overviewTrend()` / `trends()` regression coverage gap**: their MySQL-only SQL (`TIMESTAMPDIFF`, `DATE_FORMAT`, `YEARWEEK`) doesn't run on the in-memory sqlite test DB. The 1.2.c snapshot-side changes in those methods are visually identical to the `demographics()` and `eventPerformance()` tests that DO run. Future cleanup: factor pivot-SUM into a small private helper and add a sqlite-portable unit test, or add MySQL CI environment.
- **(retired)** ~~1.2.c COALESCE requirement~~ — closed by 1.2.b NOT NULL flip.

### Working rules carried across sessions
- **Thoroughness over speed.** Decompose any sub-task touching >4 files into smaller commits.
- **Migration safety.** `mysqldump` before destructive operations; every migration has working `down()`; skip-on-empty patterns.
- **Code-reviewer subagent** before each commit. Findings caught: DELETE gap (P0); redundant `default(null)` + overflow risk (1.1.c.1); client poll race + validator scope leak + Carbon parse-on-garbage + unique-violation 500 vs 409 (1.1.c.2); missing 403 test + `allLanes` cache leak (1.1.c.3); missing `withPivot()` (1.2.a); shared snapshot helper + Log::warning + COALESCE safety net (1.2.b); doc comments on dead-weight join + roster-vs-analytical semantic (1.2.c).
- **Commit messages** reference `AUDIT_REPORT.md` Part/Phase. ADRs for non-obvious decisions; Deviations log in LOG.md.
- **Subagent delegation** for read-only research to keep main context lean.
- **Stage Phase paths explicitly** — never `git add .`.

### Context budget at handoff
~80%+ used. Red. Phase 1.3 introduces an exception class + service-flow change + controller catch + UI modal — moderate complexity. **Strongly recommend `/clear` and resume from this HANDOFF before starting 1.3.**
