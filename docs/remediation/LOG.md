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

---

## Phase 0 — Stop-the-bleeding

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 0.1 Lock down `UpdateUserRequest` / `StoreUserRequest` authorize() | ✅ | b1ad1d7 | ✅ Non-admin POST/PUT/DELETE /users → 403; admin still works (8 tests) |
| 0.2 Schedule `SyncEventStatuses` + Windows Task Scheduler entry | ✅ | b9143fc | ✅ Schedule registered (`schedule:list`); README has Linux cron + Windows Task Scheduler setup; manual run synced events correctly |

## Phase 1 — Data integrity foundations

| Sub-task | Status | Commit | Acceptance |
|---|---|---|---|
| 1.1.a Migration: unique index `(event_id, lane, queue_position)` | ⬜ | — | DB-level constraint exists |
| 1.1.b `EventCheckInService::checkIn` transaction + lockForUpdate | ⬜ | — | 20 concurrent inserts on same lane never duplicate position |
| 1.1.c `EventDayController::reorder` transaction + version check | ⬜ | — | Two concurrent reorders never lose a move |
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
