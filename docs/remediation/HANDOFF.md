# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-29 (end of Session 3, Phase-1.1 nearly closed)

### Where we are
**Phase 1.1.a, 1.1.b, 1.1.c.1, and 1.1.c.2 are complete and committed.** The only remaining 1.1 sub-task is **1.1.c.3** — applying the same `VisitReorderService` fix to `VisitMonitorController::reorder` (admin monitor view). After 1.1.c.3, Phase 1.1 is done and we move to 1.2 (visit_households snapshots).

### Active branch
`phase-1/data-integrity-foundations` — 4 commits ahead of `main`. **Not yet merged.**

### Commits on the branch (oldest → newest)
On `main` baseline:
- `ef039fe` (merge), `4ed29fa`, `fa9abc0` — Phase 0 merged + post-0 setup

On `phase-1/data-integrity-foundations`:
- `4b42f8c` — Phase 1.1.a: unique index on (event_id, lane, queue_position) + 4 tests
- `2681c50` — Phase 1.1.b: EventCheckInService::checkIn transaction + lockForUpdate + 5 tests
- `a353b4c` — Phase 1.1.c.1: queue_position nullable + null-on-exit + 3 tests
- `<pending>` — Phase 1.1.c.2: VisitReorderService + EventDayController::reorder + scanner/loader JS + 8 tests

30 PHPUnit tests pass total.

### What's done in Phase 1 so far
- ✅ **1.1.a** — DB-level unique index. Found and fixed 3 pre-existing duplicate-position groups in dev DB.
- ✅ **1.1.b** — `checkIn` transaction + lockForUpdate. Rollback proven via FK-violation test.
- ✅ **1.1.c.1** — `queue_position` nullable; exited visits release their position. 26 exited rows nulled in dev DB.
- ✅ **1.1.c.2** — New `VisitReorderService` wraps the batch in `DB::transaction` + `lockForUpdate`, applies a NULL-stage so two-row swaps don't trip the unique index, and verifies an optimistic `updated_at` token per move (returns 409 on mismatch, 422 on cross-event id). Controller validates `updated_at` as `date` so garbage strings are rejected at the validator instead of crashing inside `Carbon::parse`. Service catches SQLSTATE 23000 (concurrent inserter tripping the unique index) and rethrows as version mismatch so the client refetches instead of 500ing. Scanner/loader blades carry `data-updated-at`, send it per move, and gate the 8s poll on a `pendingReorder` flag so an in-flight POST can't be clobbered. 8 service tests + reviewer pass (caught client poll race, validator scope leak, Carbon parse-on-garbage path — all fixed).

### What's next — start here on resume

**Phase 1.1.c.3** — apply the same fix to `VisitMonitorController::reorder` ([VisitMonitorController.php:201-224](app/Http/Controllers/VisitMonitorController.php#L201-L224)).

The monitor endpoint has the same loop-of-individual-UPDATEs shape and the same race. Fix is **mostly mechanical**: inject `VisitReorderService`, replace the `foreach`-update block with a single `$service->reorder()` call, translate the `RuntimeException` to 409/422 the same way `EventDayController::reorder` does. Then update the monitor blade JS (find it under `resources/views/monitor/`) to include `data-updated-at`, send it per move, and add the `pendingReorder` flag.

Notes:
- `VisitMonitorController::reorder` carries an extra `SettingService::get('event_queue.allow_queue_reorder', true)` guard at the top — keep it.
- Auth is via `web` middleware (admin login), not the per-event session-code system, so HTTP feature tests are *not* deferred here in the same way; could add a test if cheap.
- No new service tests needed — `VisitReorderServiceTest` already covers the service. A small regression test for the monitor controller's exception-mapping is nice-to-have but not blocking.
- Monitor blade for reorder UI: `git ls-files resources/views/monitor/` once on resume to find the right template (it's untracked pre-existing user work, same caveat as scanner/loader).

After 1.1.c.3, **Phase 1.1 is closed**. Suggest merging `phase-1/data-integrity-foundations` to `main` at that boundary before starting 1.2.

### Branch / merge guidance
After 1.1.c.3 closes, recommend `git merge --no-ff phase-1/data-integrity-foundations` to `main` and tag as `phase-1.1-complete` (or similar). 1.2 starts a fresh branch off the new main. Or push all of Phase 1 first — user's call.

### Environment state
- PHP 8.2.12 via XAMPP, working directory `c:\xampp\htdocs\Foodbank`.
- MySQL DB `foodbank` is the dev DB. **Migrations 1.1.a and 1.1.c.1 have been applied to MySQL.** No new migration in 1.1.c.2 (pure code change).
- Tests use sqlite `:memory:`. 30 tests passing.
- Node/npm still not installed on host. **Blade JS edits are server-rendered and don't need Vite** — they're picked up on page reload.
- Windows scheduled task `FoodBank Schedule Runner` is live (every 1 min).
- Git identity per-command: `-c user.name="Tobby" -c user.email="digienergy0@gmail.com"`.
- `/backups/` is in `.gitignore`.

### In-flight files / unfinished work
None. All staged/committed cleanly.

### Blockers
None for 1.1.c.3. Phase 5 UI work will need Node when we get there.

### User's pre-existing uncommitted work
Still substantial — many controllers, views, services remain modified/untracked on `phase-1/...`. Phase 1 commits have organically pulled in `EventCheckInService.php`, `EventDayController.php`, `VisitMonitorController.php`, `scanner.blade.php`, `loader.blade.php` (those came in as "new files" since they were untracked before). Stage explicitly via `git add <path>` — never `git add .`.

### Open questions for the user
None. Next session can resume Phase 1.1.c.3 directly.

### ADR index
- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only
- (no new ADRs in this session — the 1.1.c.2 reviewer fixes (validator tightening, client poll-race flag, unique-index-violation as version-mismatch) are all logged as Deviations in LOG.md rather than ADRs because the architectural shape didn't change)

### Working rules carried across sessions
- **Thoroughness over speed.** Decompose any sub-task touching >4 files into smaller commits.
- **Migration safety.** `mysqldump` before destructive operations; every migration has a working `down()`; skip-on-empty patterns for MySQL-specific backfills.
- **Code-reviewer subagent** before each commit (Session 1 caught DELETE gap; Session 2 caught redundant `default(null)` and overflow risk; Session 3 caught client poll race + validator scope leak + Carbon parse-on-garbage path).
- **Commit messages** reference `AUDIT_REPORT.md` Part/Phase. ADRs for non-obvious decisions; Deviations log in LOG.md for spec divergences.
- **Subagent delegation** for read-only research (Explore agent) to keep main context lean.
- **Stage Phase paths explicitly** — never `git add .`, it pulls user's pre-existing uncommitted work.
- **HTTP feature tests for event-day routes (markExited, transition, reorder)** are deferred to Phase 5 because they need session auth-code scaffolding. Service-layer unit tests cover the underlying logic. Monitor routes use admin auth and so are *not* subject to this deferral.

### Context budget at handoff
~50-60% used. Comfortably below yellow. Phase 1.1.c.3 is mechanical and small — should fit cleanly without `/clear`.
