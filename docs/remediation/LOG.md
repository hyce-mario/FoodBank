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
| 2026-04-29 | 2 | 1.1.c.1 | queue_position nullable + null-on-exit | ✅ | a353b4c | Migration makes queue_position nullable + nulls all exited rows (26 rows in dev DB). markDone, markExited, transition all set queue_position=null on exit. 8 service tests all pass; verified MAX skips NULLs so new check-ins reuse low positions. Code-review caught 2 nits (fixed). **Deviation from spec:** spec didn't anticipate active-vs-exited position collision; this turned 1.1.c into a 2-commit split (1.1.c.1 schema fix, 1.1.c.2 reorder). |
| 2026-04-29 | 3 | 1.1.c.2 | EventDayController::reorder transaction + version check | ✅ | 57de2ca | New `VisitReorderService` wraps the batch in `DB::transaction` + `lockForUpdate`, applies a NULL-stage on `queue_position` to allow same-lane swaps without tripping the unique index, and verifies an optimistic `updated_at` token per move (returns 409 on mismatch, 422 on cross-event id). Controller validates `updated_at` as `date`. Scanner/loader blades carry `data-updated-at`, send it per move, and gate the 8s poll on a `pendingReorder` flag so an in-flight POST can't be clobbered. Service catches SQLSTATE 23000 (concurrent insert tripping the unique index) and rethrows as version mismatch so the client refetches instead of 500ing. 8 new service tests; full suite at 30/30. Code-review caught the client poll race + validator scope leak + Carbon parse-on-garbage path; all fixed. |
| 2026-04-29 | 3 | 1.1.c.3 | VisitMonitorController::reorder swap to VisitReorderService | ✅ | 381c080 | Mechanical port of the 1.1.c.2 pattern to the admin monitor endpoint: reuses `VisitReorderService`, keeps the existing `event_queue.allow_queue_reorder` setting check (returns 403 when off), drops the foreach-update loop, validates `updated_at` as `date`, translates exceptions to 409/422, echoes back fresh `updated_at` per visit. `data()` `$buildRow` now emits `updated_at`. Monitor blade adds `data-updated-at` to scanner+loader cards, ships it per move, gates `load()` + the 10s poll on a `pendingReorder` flag, refreshes both DOM tokens **and** the cached `allLanes` entries on success (reviewer caught: cache could leak stale tokens via switchLane re-render before next poll). 7 new HTTP feature tests (admin auth, swap, response shape, stale 409, cross-event 422, malformed-date 422, allow_queue_reorder=false 403). Full suite at 37/37. **Phase 1.1 closed.** |
| 2026-04-29 | 3 | post-1.1 | Merge phase-1/data-integrity-foundations → main + tag + cut Phase 1.2 branch | ✅ | 802e955 (merge) | Merged via `--no-ff`. Tag `phase-1.1-complete` pushed to origin; new branch `phase-1.2/visit-households-snapshots` cut from the merge commit. |
| 2026-04-29 | 3 | 1.2.a | Migration: snapshot columns on visit_households | ✅ | 33d73e2 | Adds `household_size` (tinyint unsigned), `children_count`/`adults_count`/`seniors_count` (smallint unsigned), `vehicle_make` (varchar 100), `vehicle_color` (varchar 50) — all nullable. Backfill via correlated subquery (portable across MySQL 8 + SQLite 3.33+); skip-on-empty for fresh installs. **Applied to MySQL dev DB**: all 108 pivot rows backfilled, NULL count = 0; spot-check matched live `households` source exactly. Pre-migration mysqldump saved to `backups/foodbank-pre-phase-1.2-20260429-134049.sql` (140KB). Reviewer caught a blocker: pivot columns not exposed through Eloquent without `withPivot()` — fixed by adding the declaration to both `Visit::households()` and `Household::visits()`. 4 tests pin column existence, attach-without-pivot still works, backfill SQL portability, and pivot read-through-Eloquent. Full suite at 41/41. |
| 2026-04-29 | 3 | 1.2.b | EventCheckInService writes snapshot at attach + flip columns to NOT NULL | ✅ | _pending_ | `EventCheckInService::checkIn` now passes `$household->toVisitPivotSnapshot()` to both `attach()` calls (representative + represented). Bulk-loads represented households to avoid N+1; explicit existence check throws `RuntimeException` (with `Log::warning`) if any submitted id is missing — preserves the original FK-violation rollback contract. New shared helper `Household::toVisitPivotSnapshot()` is the single source of truth for the snapshot field set, called from both the service and `DemoSeeder`. **Migration tightens demographic columns to NOT NULL** (vehicle stays nullable, source is nullable); defensive re-backfill block at the top of `up()` patches any NULL row from `households.*` with COALESCE(...,0) safety net. **Closes the carry-forward COALESCE-in-1.2.c requirement entirely** — reports can SUM directly without defensive null handling. Applied to MySQL dev DB: `SHOW COLUMNS` confirms household_size + 3 counts are `NO`, vehicle stays `YES`; 108 rows survived intact. 4 new service tests including the headline "edit household after check-in does not change pivot snapshot". 1.2.a backfill-SQL test deleted (premise depended on bare attach); 1.2.a nullability test transformed into "NOT NULL rejects bare attach". Full suite at 44/44. |

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
| 1.1.c.1 queue_position nullable + null-on-exit (precondition for safe reorder) | ✅ | a353b4c | ✅ Position is now meaningful only for active visits; exited rows hold NULL; unique index naturally allows multiple NULLs |
| 1.1.c.2 `EventDayController::reorder` transaction + version check | ✅ | 57de2ca | ✅ Reorder runs inside `DB::transaction` + `lockForUpdate`; NULL-stage allows two-row swaps; per-move `updated_at` enforces optimistic versioning; concurrent reorders that lose the version race get 409 + auto-refetch in the client |
| 1.1.c.3 `VisitMonitorController::reorder` swap to `VisitReorderService` | ✅ | 381c080 | ✅ Same race fix applied to admin monitor endpoint via shared service; HTTP feature tests cover all branches incl. `allow_queue_reorder=false → 403` |
| 1.2.a Migration: snapshot columns on `visit_households` | ✅ | 33d73e2 | ✅ Columns exist; 108 historical rows backfilled in dev DB; NULL count = 0; pivot columns exposed via `withPivot()` on both relationships |
| 1.2.b Snapshot at attach time in `EventCheckInService` + NOT NULL flip | ✅ | _pending_ | ✅ `Household::toVisitPivotSnapshot()` shared by service + seeder; demographic columns are NOT NULL; "edit household post-checkin doesn't change pivot snapshot" test pins the temporal-stability contract |
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
| 2026-04-29 | 1.1.c.2 | Spec covered `EventDayController::reorder` only | `VisitMonitorController::reorder` carries the same race bug but was **not** fixed in this commit | Audit Part 13 §1.1 names only the event-day endpoint. Monitor reorder has the same shape and should swap to `VisitReorderService` once we have admin-side coverage. Tracked as follow-up sub-task **1.1.c.3** below. Keeping the commit atomic: one service, one consumer, then ramp. |
| 2026-04-29 | 1.1.c.2 | (not in spec) | Validator dropped `exists:visits,id` on `moves.*.id` | Replaced by an authoritative scope check inside the service's `lockForUpdate` transaction. `exists` runs N extra SELECTs per request and races with the lock anyway — the service path is single-query and consistent with the row lock. |
| 2026-04-29 | 1.1.c.2 | (not in spec) | Reviewer flagged a client-side race: poll could clobber the local `visits[]` between drag-end and POST-resolve | Added a `pendingReorder` JS flag in scanner + loader; `fetchData()` early-returns while a reorder POST is in flight. Without this, two operators dragging at once on shaky Wi-Fi would see spurious 409s. |
| 2026-04-29 | 1.2.a | Spec said schema + backfill | Also added `withPivot()` to `Visit::households()` and `Household::visits()` | Reviewer caught that without `withPivot()`, the new pivot columns are silently dropped on Eloquent reads even when populated — a footgun for any future code that reaches for `$pivot->household_size`. Added the declaration in 1.2.a alongside the schema since it's fundamentally describing "these columns exist on the pivot." |
| 2026-04-29 | 1.2.a | (not in spec) | Snapshot columns are nullable, not NOT NULL DEFAULT 0 | Reviewer endorsed: NULL fails loud at read time if a row is created without a snapshot, while 0 silently corrupts SUMs. **Open requirement carried into 1.2.c**: report queries must use `COALESCE(vh.household_size, 0)` and log a warning on NULL pivot rows; otherwise a single bad row poisons the whole report total. **Closed in 1.2.b** by flipping the demographic columns to NOT NULL once the service guarantees population — reports can SUM directly without defensive COALESCE. Vehicle columns remain nullable (source is nullable). |
| 2026-04-29 | 1.2.b | Spec said "snapshot at attach time" only | Also flipped demographic columns to NOT NULL in the same commit | Once the service writes the snapshot reliably and existing rows are backfilled, leaving columns nullable is just a future footgun. Tightening to NOT NULL turns the constraint into the test: any code path that bypasses `Household::toVisitPivotSnapshot()` fails loud at insert time instead of silently corrupting reports later. |
| 2026-04-29 | 1.2.b | Pre-1.2.b passed missing represented IDs straight to attach() and got an FK violation | Bulk-load + explicit existence check throws `RuntimeException` with `Log::warning` | The bulk-load (needed for snapshot batching) would otherwise silently drop missing IDs, leaving the visit attached only to the primary. Explicit check preserves the rollback contract that the existing `test_failed_attach_rolls_back_visit_creation` test pins. |
