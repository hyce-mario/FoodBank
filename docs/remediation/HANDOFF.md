# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-04-29 (end of Session 2, mid-Phase-1)

### Where we are
**Phase 1.1.a, 1.1.b, and 1.1.c.1 are complete and committed.** Phase 1.1.c.2 (the actual reorder transaction + version check) is the next discrete piece. After 1.1.c.2 closes, Phase 1.1 is done and we move to 1.2 (visit_households snapshots).

### Active branch
`phase-1/data-integrity-foundations` — 3 commits ahead of `main`. **Not yet merged.**

### Commits on the branch (oldest → newest)
On `main` baseline:
- `ef039fe` (merge), `4ed29fa`, `fa9abc0` — Phase 0 merged + post-0 setup

On `phase-1/data-integrity-foundations`:
- `4b42f8c` — Phase 1.1.a: unique index on (event_id, lane, queue_position) + 4 tests
- `2681c50` — Phase 1.1.b: EventCheckInService::checkIn transaction + lockForUpdate + 5 tests
- `a353b4c` — Phase 1.1.c.1: queue_position nullable + null-on-exit + 3 tests

22 PHPUnit tests pass total.

### What's done in Phase 1 so far
- ✅ **1.1.a** — DB-level unique index. Found and fixed 3 pre-existing duplicate-position groups in dev DB (real race-induced corruption). Migration is idempotent (defensive ROW_NUMBER renumber before applying constraint).
- ✅ **1.1.b** — `checkIn` is now wrapped in `DB::transaction` with `lockForUpdate` on the position SELECT. `loadMissing` correctly stays outside the transaction. Rollback proven via FK-violation test.
- ✅ **1.1.c.1** — `queue_position` is nullable; exited visits release their position (NULL). Three exit paths updated: `EventCheckInService::markDone`, `EventDayController::markExited`, `VisitMonitorController::transition` (supervisor override). Migration nulled 26 exited rows in dev DB; 81 active kept positions.
- 📋 **Deviation logged** in LOG.md: 1.1.c was split into two commits because the spec didn't anticipate the active-vs-exited position collision.

### What's next — start here on resume

**Phase 1.1.c.2** — `EventDayController::reorder` transaction + version check.

The current method ([EventDayController.php:180-206](app/Http/Controllers/EventDayController.php#L180-L206)) does individual `UPDATE` statements in a foreach loop with **no transaction wrapping**. Two concurrent reorder POSTs (e.g., scanner and loader both dragging) silently overwrite each other.

**Concrete plan for 1.1.c.2:**

1. **Server change** — wrap the foreach in `DB::transaction`. Add optimistic version check: each `move` payload should include the visit's `updated_at` (or a dedicated `version` field) the client last saw. Server `SELECT ... FOR UPDATE` each row, compare `updated_at`, return 409 if any row has been touched since.

2. **Position-collision avoidance** — even within one transaction, applying `[{id:1, pos:2}, {id:2, pos:1}]` sequentially trips the unique index at the intermediate `pos:2` step. Two workable approaches:
   - **Two-phase update**: phase 1 sets each affected row's `lane = 100 + lane` (offsets out of valid range, since lanes are 1-4, this temporarily moves rows to "phantom" lanes that don't conflict). Phase 2 sets final lane + position. Wrap both in the same transaction.
   - **NULL-stage**: phase 1 sets each affected row's `queue_position = NULL`. Phase 2 sets final position. Cleaner since we already made queue_position nullable in 1.1.c.1.
   The NULL-stage approach is preferable.

3. **Client change** — JS in [scanner.blade.php](resources/views/event-day/scanner.blade.php) and [loader.blade.php](resources/views/event-day/loader.blade.php) needs to send the `updated_at` per move. The `data()` JSON response already returns visits but check whether `updated_at` is included; if not, add it.

4. **Tests:**
   - Reorder updates positions correctly within one user's call.
   - Reorder swaps `[{id:1,pos:2},{id:2,pos:1}]` — currently impossible because of unique index, must work after the NULL-stage fix.
   - Stale version check: if you call reorder with an old `updated_at`, get 409.
   - Reorder is rejected if any move's id doesn't belong to the event.

5. **Caveat to flag** — UI changes in scanner/loader blade templates will need Vite rebuild for the JS to hot-reload, but **Node is not installed on host**. The blade `.blade.php` files are server-rendered without Vite, so JS edits there are picked up on page reload. No build needed — flag this in the commit message.

### Branch / merge guidance
After 1.1.c.2 closes, Phase 1.1 is done. Recommend a merge of `phase-1/data-integrity-foundations` to `main` between 1.1 and 1.2 to keep merges atomic per major sub-phase. Or finish all of Phase 1 before merging — user's call.

### Environment state
- PHP 8.2.12 via XAMPP, working directory `c:\xampp\htdocs\Foodbank`.
- MySQL DB `foodbank` is the dev DB. **Migrations 1.1.a and 1.1.c.1 have been applied to MySQL** (in addition to running cleanly on sqlite test DB). Dev DB now has unique index live and 26 exited rows with NULL position.
- Tests use sqlite `:memory:`. 22 tests passing.
- Node/npm still not installed on host. UI work that needs Vite rebuild is blocked.
- Windows scheduled task `FoodBank Schedule Runner` is live (every 1 min).
- Git identity per-command: `-c user.name="Tobby" -c user.email="digienergy0@gmail.com"`.
- `/backups/` is in `.gitignore`.

### In-flight files / unfinished work
None. All staged/committed cleanly.

### Blockers
None for 1.1.c.2. Phase 5 UI work will need Node when we get there.

### User's pre-existing uncommitted work
Still substantial — many controllers, views, services remain modified/untracked on `phase-1/...` (carried over from `main`). Phase 1 commits have organically pulled in `EventCheckInService.php`, `EventDayController.php`, `VisitMonitorController.php` (those came in as "new files" since they were untracked before). Stage explicitly via `git add <path>` — never `git add .`.

### Open questions for the user
None. Next session can resume Phase 1.1.c.2 directly.

### ADR index
- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only
- (no new ADRs in this session — the 1.1.c spec deviation is logged in LOG.md "Deviations" rather than as an ADR because the architectural shape didn't change, just the commit decomposition)

### Working rules carried across sessions
- **Thoroughness over speed.** Decompose any sub-task touching >4 files into smaller commits.
- **Migration safety.** `mysqldump` before destructive operations; every migration has a working `down()`; skip-on-empty patterns for MySQL-specific backfills.
- **Code-reviewer subagent** before each commit (Session 1's caught DELETE gap; Session 2's caught redundant `default(null)` and overflow risk).
- **Commit messages** reference `AUDIT_REPORT.md` Part/Phase. ADRs for non-obvious decisions; Deviations log in LOG.md for spec divergences.
- **Subagent delegation** for read-only research (Explore agent) to keep main context lean.
- **Stage Phase paths explicitly** — never `git add .`, it pulls user's pre-existing uncommitted work.
- **HTTP feature tests for event-day routes (markExited, transition, reorder)** are deferred to Phase 5 because they need session auth-code scaffolding. Service-layer unit tests cover the underlying logic.

### Context budget at handoff
~70-80% used. Approaching red. Phase 1.1.c.2 is moderately complex (server change + JS change + multi-test coverage). **Recommend `/clear` and resume from this HANDOFF before attempting 1.1.c.2.**
