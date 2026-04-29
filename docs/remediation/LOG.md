# Remediation Log

> Append-only journal of every sub-task. The spec is [AUDIT_REPORT.md](../../AUDIT_REPORT.md) Part 13. Every entry references a Phase + sub-task and (once committed) a commit SHA.
>
> **Status legend:** ⬜ todo · 🟡 in-progress · ✅ done · 🔴 blocked · ⚪ skipped (with reason)

---

## Session log

| Date | Session | Phase | Sub-task | Status | Commit | Notes |
|---|---|---|---|---|---|---|
| 2026-04-29 | 0 | — | Scaffolding (`docs/remediation/`) | ✅ | d257731 | Created LOG, HANDOFF, ADR template + ADR-001. |
| 2026-04-29 | 1 | 0.1 | Lock down UserController (admin-only) | ✅ | b1ad1d7 | StoreUserRequest + UpdateUserRequest + UserController.update + UserController.destroy. 8 regression tests pass. ADR-002. Code-review caught DELETE gap. |
| 2026-04-29 | 1 | 0.2 | Schedule SyncEventStatuses + README setup docs | ✅ | b9143fc | routes/console.php gains `withoutOverlapping()`. README adds Linux cron + Windows Task Scheduler instructions. Verified via `schedule:list` and a manual run that synced 1→current, 5→past against dev DB. |
| 2026-04-29 | 1 | post-0 | Merge Phase 0 → main + DB backup + register Win Task Scheduler entry | ✅ | ef039fe (merge), 4ed29fa (gitignore) | Phase 0 merged via `--no-ff`. mysqldump saved to `backups/foodbank-pre-phase-1-20260429-114638.sql` (140KB, 31 tables). `backups/` added to .gitignore. Windows scheduled task `FoodBank Schedule Runner` registered (every 1 min, hidden, 10-year duration); test fire returned exit 0. |
| 2026-04-29 | 2 | 1.1.a | Unique index `(event_id, lane, queue_position)` on visits | ✅ | 4b42f8c | Migration adds defensive ROW_NUMBER renumber + unique index. **Found and fixed 3 duplicate-position groups in dev DB** (real race-induced corruption). 4 regression tests pass: insert, duplicate rejection, different-lane OK, different-event OK. |
| 2026-04-29 | 2 | 1.1.b | EventCheckInService::checkIn transaction + lockForUpdate | ✅ | 2681c50 | Wraps active-check + position read + Visit::create + 2 attach() calls in DB::transaction; `lockForUpdate` on the position SELECT serializes concurrent (event_id, lane) inserts. 5 regression tests including a rollback-proof for FK violation. Code-review pass: zero issues found. |
| 2026-04-29 | 2 | 1.1.c.1 | queue_position nullable + null-on-exit | ✅ | _pending_ | Migration makes queue_position nullable + nulls all exited rows (26 rows in dev DB). markDone, markExited, transition all set queue_position=null on exit. 8 service tests all pass; verified MAX skips NULLs so new check-ins reuse low positions. Code-review caught 2 nits (fixed). **Deviation from spec:** spec didn't anticipate active-vs-exited position collision; this turned 1.1.c into a 2-commit split (1.1.c.1 schema fix, 1.1.c.2 reorder). |

---

## Phase 0 — Stop-the-bleeding

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 0.1 Lock down `UpdateUserRequest` / `StoreUserRequest` authorize() | ✅ | b1ad1d7 | ✅ Non-admin POST/PUT/DELETE /users → 403; admin still works (8 tests) |
| 0.2 Schedule `SyncEventStatuses` + Windows Task Scheduler entry | ✅ | b9143fc | ✅ Schedule registered (`schedule:list`); README has Linux cron + Windows Task Scheduler setup; manual run synced events correctly |

## Phase 1 — Data integrity foundations

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 1.1.a Migration: unique index `(event_id, lane, queue_position)` | ✅ | _pending_ | ✅ DB-level constraint exists; duplicate insert raises QueryException; renumbered 3 pre-existing duplicate groups in dev DB |
| 1.1.b `EventCheckInService::checkIn` transaction + lockForUpdate | ✅ | _pending_ | ✅ Position read+insert serialized via DB::transaction + lockForUpdate; 5 tests incl. FK-rollback proof. Real concurrent test guarded by 1.1.a unique index. |
| 1.1.c.1 queue_position nullable + null-on-exit (precondition for safe reorder) | ✅ | _pending_ | ✅ Position is now meaningful only for active visits; exited rows hold NULL; unique index naturally allows multiple NULLs |
| 1.1.c.2 `EventDayController::reorder` transaction + version check | ⬜ | — | Two concurrent reorders never lose a move |
| 1.2.a Migration: snapshot columns on `visit_households` | ⬜ | — | Columns exist; backfilled for historical visits |
| 1.2.b Snapshot at attach time in `EventCheckInService` | ⬜ | — | New visits write demographics + vehicle to junction |
| 1.2.c Switch `ReportAnalyticsService` to read from snapshot | ⬜ | — | Editing a household post-visit doesn't change historical reports |
| 1.3 One-visit-per-household-per-event guard | ⬜ | — | Second check-in same event rejected unless `force=true` |

## Phase 2 — Reporting truth

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 2.1.a `DistributionPostingService` skeleton + unit tests | ⬜ | — | Service class exists with passing unit tests |
| 2.1.b Bag-composition resolver from `AllocationRuleset` | ⬜ | — | Composition is data-driven, not hardcoded |
| 2.1.c Hook into `markLoaded` happy path | ⬜ | — | Visit loaded → InventoryMovement(event_distributed) created |
| 2.1.d Hook into supervisor override path | ⬜ | — | Override-to-loaded also posts inventory |
| 2.1.e `InsufficientStockException` UX | ⬜ | — | Modal with skip/substitute/cancel; no 500s |
| 2.1.f Backfill + reconciliation artisan command | ⬜ | — | `inventory:reconcile {event}` produces 0 delta on clean event |
| 2.2 Nightly reconciliation schedule | ⬜ | — | Delta > threshold emails admins |

## Phase 3 — Public-surface hardening

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 3.1 Rate limits on public POST endpoints | ⬜ | — | 6th attempt within 1 min returns 429 |
| 3.2.a Migration: hashed code columns | ⬜ | — | New columns exist; old plaintext kept temporarily |
| 3.2.b Code generation → 6 alphanumeric, hashed | ⬜ | — | Codes are 36⁶ space, stored hashed |
| 3.2.c Verification with `Hash::check` + constant-time | ⬜ | — | Code submission uses hash check |
| 3.2.d Migration: drop plaintext columns | ⬜ | — | Plaintext columns gone after grace period |
| 3.3 Mass-assignment cleanup (UserController, EventReview) | ⬜ | — | Public POST `/review` with `is_visible=1` is ignored |

## Phase 4 — Authorization & audit

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 4.1.a HouseholdPolicy + register | ⬜ | — | INTAKE-only routes 403 for SCANNER role |
| 4.1.b VisitPolicy / EventPolicy / ReviewPolicy / VolunteerPolicy | ⬜ | — | Each resource has authorize() coverage |
| 4.1.c Replace bare `find` with policy-checked `findOrFail` + `authorize` | ⬜ | — | No unguarded resource fetches |
| 4.2.a Migration: `audit_logs` table | ⬜ | — | Table created |
| 4.2.b `Auditable` trait + apply to User/Role/AppSetting/Household/Visit | ⬜ | — | Every change has an audit row |
| 4.2.c Admin `/audit-logs` page with filters | ⬜ | — | Admin can query who/when/what |

## Phase 5 — Workflow & UX quality

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 5.1 Bag composition on loader card | ⬜ | — | Loader sees items + counts, not just total |
| 5.2.a Pre-reg reconciliation controller method | ⬜ | — | Endpoint surfaces potential matches |
| 5.2.b Reconciliation UI + confirm/reject actions | ⬜ | — | Every potential_match resolves before EOD |
| 5.3.a Volunteer auto-checkout artisan command | ⬜ | — | Open check-ins close at event-end + 1h |
| 5.3.b Public "Check Out" button | ⬜ | — | Volunteer can self-checkout from same QR page |
| 5.3.c `hours_served` accessor + report | ⬜ | — | Reports show non-zero, computed hours |
| 5.4 Zero-stock UX modal | ⬜ | — | InsufficientStockException → modal, not 500 |

## Phase 6 — Backlog

Tracked separately; pulled in only when capacity allows. See AUDIT_REPORT.md Part 13 §6.

---

## Decisions (cross-reference to ADRs)

| ADR | Title | Date | Status |
|---|---|---|---|
| 001 | [AUDIT_REPORT.md Part 13 is the spec](adr/001-audit-report-is-spec.md) | 2026-04-29 | accepted |
| 002 | [UserController is admin-only](adr/002-usercontroller-admin-only.md) | 2026-04-29 | accepted |

---

## Deviations from spec

When the implementation diverges from `AUDIT_REPORT.md` Part 13, log it here with the reason.

| Date | Phase / Sub-task | Spec said | We did | Why |
|---|---|---|---|---|
| 2026-04-29 | 0.1 | Fix `authorize()` on Store/Update | Also fixed `UserController::destroy()` admin guard | Code-review caught that non-admin could DELETE any user (DoS-tier bug). Logical extension of the same fix; closing it now is cheaper than re-opening Phase 0 later. |
| 2026-04-29 | 0.1 | (not in spec) | Made 3 migrations sqlite-compatible (sessions dup, queue_position MySQL user vars, events CURDATE backfill) + uncommented sqlite-in-memory in phpunit.xml | Required to make Phase 0.1 tests run safely in isolation without clobbering the dev MySQL DB. These are real portability bugs that would also bite a fresh deploy. |
| 2026-04-29 | 0.1 | (not in spec) | Updated `tests/Feature/ExampleTest.php` (Laravel stub) to tolerate auth-redirect | Was strictly asserting 200 on `/`, but `/` redirects to `/login` (auth middleware). Pre-existing failure, surfaced once sqlite test isolation was enabled. |
| 2026-04-29 | 1.1.c | Spec said "wrap reorder in transaction + version check" (single sub-task) | Split into 1.1.c.1 (schema: nullable queue_position + null-on-exit) and 1.1.c.2 (reorder hardening) | The Phase 1.1.a renumber assigned 1..N positions to ALL visits incl. exited. The scanner JS sends 1..N for active visits on reorder, which would collide with positions still held by exited rows. Fix: scope queue_position to active visits only. Discovered during 1.1.c implementation; documented and split into manageable commits. |
| 2026-04-29 | 1.1.c.1 | (not in spec) | HTTP feature tests for `EventDayController::markExited` and `VisitMonitorController::transition` not added | Service-layer test (`EventCheckInServiceTest::test_mark_done_clears_queue_position`) covers the same code path. Adding HTTP tests for those routes requires session auth-code scaffolding (Phase 5 scope). Logged as a coverage gap to address when we touch event-day routes for UX work in Phase 5. |
