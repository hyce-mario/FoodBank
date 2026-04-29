# FOOD BANK MANAGEMENT SYSTEM — OPERATIONAL AUDIT

> Comprehensive code + real-world workflow review.
> Findings are grounded in code with file:line citations. Tone is deliberately blunt — real-world deployment of a buggy distribution system has consequences for actual hungry families.

---

## PART 1 — CURRENT SYSTEM UNDERSTANDING

### Household model
- `households` is a flat table holding identity (name, phone, email, address), demographics (`children_count`, `adults_count`, `seniors_count`, derived `household_size`), vehicle (`vehicle_make`, `vehicle_color`), QR token, and a self-referencing `representative_household_id` foreign key. See [app/Models/Household.php](app/Models/Household.php) and the refactor migration in [database/migrations/](database/migrations/).
- "Representative households" are modeled by a single FK on `households` itself, not a pivot. One household = one representative; a represented household cannot itself be a representative (enforced at write time in [app/Services/HouseholdService.php:203-206](app/Services/HouseholdService.php#L203-L206)).

### Check-in workflow
- One controller does it all: [app/Http/Controllers/CheckInController.php](app/Http/Controllers/CheckInController.php). Methods include `search`, `store`, `quickAdd` (create + check-in in one call), `quickCreate`, `createRepresented`, `attachRepresented`.
- Service layer is [app/Services/EventCheckInService.php](app/Services/EventCheckInService.php). It creates a `Visit`, attaches all involved households via the `visit_households` junction, and sets `queue_position` to `MAX+1` for the lane.
- "Quick check-in" exists in spirit (`quickAdd` ~7 fields), but there is no barcode-driven auto-advance flow.

### Queue system
- Queue state is persistent on `visits`: `lane` (tinyint), `queue_position` (smallint), `visit_status` (`checked_in → queued → loading/loaded → exited`).
- Status transitions live in [app/Http/Controllers/EventDayController.php:210-266](app/Http/Controllers/EventDayController.php#L210-L266) with simple "from-state" guards, plus an override path in [app/Http/Controllers/VisitMonitorController.php:152-195](app/Http/Controllers/VisitMonitorController.php#L152-L195).
- Drag-and-drop reordering uses SortableJS, persisted in batches via `POST /ed/{event}/reorder` ([EventDayController.php:180-206](app/Http/Controllers/EventDayController.php#L180-L206)).
- "Real-time" sync is **AJAX polling every 10 s** ([resources/views/events/.../monitor.blade.php:676](resources/views/events/monitor.blade.php#L676)). Configurable, min 5 s. No WebSockets.

### Volunteer flow
- [app/Http/Controllers/PublicVolunteerCheckInController.php](app/Http/Controllers/PublicVolunteerCheckInController.php) drives a public, unauthenticated check-in form. `updateOrCreate(['event_id', 'volunteer_id'])` enforces one check-in per event per volunteer.
- Only `checked_in_at` is recorded. **`checked_out_at` is never written by any controller method.** Volunteer hours are therefore not computed from data.

### Inventory
- `InventoryItem` tracks `quantity_on_hand`, `unit_type`, `expiry_date`, `manufacturing_date`. No batches, no lot codes.
- `InventoryMovement` exists with movement types `stock_in`, `stock_out`, `damaged`, `expired`, `event_allocated`, `event_returned`, **and** `event_distributed`. **The `event_distributed` constant is defined but never produced anywhere in the codebase.**
- `EventInventoryAllocation` tracks `allocated_quantity`, `distributed_quantity`, `returned_quantity` per event/item. `distributed_quantity` is a manually-edited integer.
- `AllocationRuleset` maps household size → bag count. Used for *display* only; never deducts inventory.

### Reporting
- Aggregations live in [app/Services/ReportAnalyticsService.php](app/Services/ReportAnalyticsService.php), [app/Services/EventAnalyticsService.php](app/Services/EventAnalyticsService.php), [app/Services/InventoryReportService.php](app/Services/InventoryReportService.php).
- "People served" sums **current** `households.household_size` ([ReportAnalyticsService.php:57-62](app/Services/ReportAnalyticsService.php#L57-L62)). No event-time snapshot.
- "Inventory distributed" reads from movements of type `event_distributed` ([InventoryReportService.php:26-27](app/Services/InventoryReportService.php#L26-L27)) — which never get written.

### Assumptions (please confirm/correct)
1. Events are typically 1 day; lanes are physical drive-through lanes.
2. Volunteers and intake staff are distinct people; "intake" is staff with auth, "volunteers" are tracked entities.
3. Bags are pre-assembled before the event and the loader's job is to put them into vehicles, not to assemble.

---

## PART 2 — REAL-LIFE EVENT SIMULATION (300 households, 3 lanes)

Walking the system through the day, step by step.

### 2.1 Pre-event (the night before)
- A staffer marks tomorrow's event status as `current` so codes activate. **Or hopes the cron-less [SyncEventStatuses](app/Console/Commands/SyncEventStatuses.php) command runs**, which it won't unless someone wired it into cron / Task Scheduler — it isn't registered in [routes/console.php](routes/console.php). **Risk: at 7am the codes are still inactive because status is still `upcoming`.**
- 80 of 300 families have pre-registered via the public form. The form has no email confirmation, no rate limiting, and no de-duplication beyond name match ([PublicEventController.php:77-80](app/Http/Controllers/PublicEventController.php#L77-L80)). 6 of them are duplicates submitted by anxious caregivers.

### 2.2 Gates open — first 60 minutes
- 4 staff load `/intake/{event}/auth` on tablets. Each types the 4-digit code. They stay logged in via session for the day. A volunteer writes the code on a sticky note at the table.
- **Cars start arriving before staff finishes setting up.** Lane 1 has a 12-car backlog within 8 minutes.
- An intake worker searches a name → 6 results because of pre-registration duplicates. They pick one. **No system-side reconciliation happens** between the chosen household and the orphaned pre-registrations ([EventPreRegistration.match_status](app/Models/EventPreRegistration.php) is set to `potential_match` and never resolved).
- A walk-in family appears. The staffer hits "quick add" and types 7 fields. **No duplicate-by-phone or duplicate-by-address check at create time** (StoreHouseholdRequest has no unique rules). They've created a parallel record for a household that pre-registered yesterday under a slightly different spelling. That household will now appear twice in reports forever.

### 2.3 Representative pickups
- A grandmother arrives picking up for 3 daughter families. Two of the three are pre-registered separately. The intake worker:
  1. Searches grandmother → finds her existing household.
  2. Adds 3 represented households via the `represented_ids` UI.
  3. Submits.
- The two daughters' separate pre-registrations are **not invalidated**. If a daughter shows up at gate 2 lane later, **she can be checked in again** — `EventCheckInService` only blocks active visits (no `end_time IS NULL`), and even that block is per-household, not per-event-day ([EventCheckInService.php:75-85](app/Services/EventCheckInService.php#L75-L85)).
- Each represented household becomes its own row in `visit_households`. **Reports will count this single visit as 4 households served and 4× household_size people served**, which is the intent for impact metrics — but a representative pickup is also counted as one *visit* for queue throughput, leading to inconsistent KPIs across reports.

### 2.4 Queue chaos — minute 45
- 3 cars arrive within 800 ms during a network hiccup. Two intake workers on Lane 2 both submit check-ins. Both query `MAX(queue_position)`, both get `12`, both write `13`. Now two cars share position 13. The poll-driven queue board shows them in arbitrary order ([EventCheckInService.php:87-89](app/Services/EventCheckInService.php#L87-L89), no unique constraint, no row lock).
- The scanner team drags a card to fix the order. Meanwhile, a different scanner on the second tablet drags the same card. The two `POST /ed/{event}/reorder` calls run in independent loops with **no transaction wrapping** ([EventDayController.php:196-202](app/Http/Controllers/EventDayController.php#L196-L202), no optimistic locking). Last write wins; one of the moves silently disappears.
- A car physically pulls out of line. Staff has 10 seconds before the polling refresh shows them as gone — actually they have 10 seconds *after* a manual status change is saved. Until someone marks the visit as exited or skipped, **the digital queue still thinks the car is there.**

### 2.5 Loader station
- Loader sees a card with `bags_needed: 3`, the `vehicle_label`, and household demographics — but **no household name** (correctly stripped at [EventDayController.php:153-160](app/Http/Controllers/EventDayController.php#L153-L160)).
- Loader hands out bags. **No system action ties those bags to inventory.** The visit stays open. Loader hits "Loading Complete" → `visit_status = loaded`.
- `served_bags` is set on the visit, but no `InventoryMovement` is created. `EventInventoryAllocation.distributed_quantity` is unchanged unless a separate person manually updates it from the inventory page.

### 2.6 Exit station
- Exit volunteer taps "Exit" → `visit_status = exited`, `exited_at` stamped. Nothing happens to inventory at this moment either.
- Throughout the day, 4 cars are accidentally marked exited from the *queued* state because the supervisor uses [VisitMonitorController.php:152-195](app/Http/Controllers/VisitMonitorController.php#L152-L195), which permits any of the listed transitions including effectively skipping the loader step. Their `served_bags` stays at 0. **Bag count for the day is now under by 4 × ~3 bags = 12 bags.** Inventory thinks none of it left.

### Summary of Part 2
The system *can* run an event, but every silent failure mode I just described requires zero malice — they are normal-looking interactions producing wrong data.

---

## PART 3 — CRITICAL FAILURE POINTS (BRUTAL)

| # | Failure | Where | What goes wrong on event day |
|---|---|---|---|
| 1 | **Race on queue_position** | [EventCheckInService.php:87-89](app/Services/EventCheckInService.php#L87-L89) | 2 concurrent check-ins on the same lane → duplicate position → unstable sort |
| 2 | **Drag-reorder has no transaction or version check** | [EventDayController.php:196-202](app/Http/Controllers/EventDayController.php#L196-L202) | Two staff dragging at once → silent overwrite → cars served out of order |
| 3 | **Inventory has zero automatic decrement** | [VisitMonitorController.php:182-189](app/Http/Controllers/VisitMonitorController.php#L182-L189), nowhere creates `event_distributed` movement | Stock-on-hand is wrong by end of day; can't tell if you actually have food for tomorrow |
| 4 | **Duplicate households at creation** | StoreHouseholdRequest, no unique rules; only name-match in pre-reg | Same family entered 2-3× across pre-reg + walk-in. Inflates served counts |
| 5 | **No "served once per event" rule** | [EventCheckInService.php:75-85](app/Services/EventCheckInService.php#L75-L85) — only blocks if `end_time IS NULL` | After exit, family can re-check-in same day. Double-dip undetected |
| 6 | **People-served metric uses live demographics** | [ReportAnalyticsService.php:57-62](app/Services/ReportAnalyticsService.php#L57-L62) | Edit a household next month → historical reports retroactively change |
| 7 | **4-digit auth codes, no rate limit** | [Event.php:49-54](app/Models/Event.php#L49-L54), [EventDayController.php:60-75](app/Http/Controllers/EventDayController.php#L60-L75) | 10,000 codes, no throttle. A motivated stranger can be on your loader screen in seconds |
| 8 | **`UpdateUserRequest::authorize()` returns true** | [app/Http/Requests/UpdateUserRequest.php:12-14](app/Http/Requests/UpdateUserRequest.php#L12-L14) | Any logged-in user can change anyone's role — including their own → ADMIN |
| 9 | **Polling lag 10 s** | [monitor.blade.php:676](resources/views/events/monitor.blade.php#L676) | Digital queue and physical line drift; manual rearrangement isn't reflected |
| 10 | **`SyncEventStatuses` not scheduled** | [app/Console/Commands/SyncEventStatuses.php](app/Console/Commands/SyncEventStatuses.php), absent from [routes/console.php](routes/console.php) | Codes never activate at midnight; event stuck `upcoming` |
| 11 | **Pre-reg potential matches never resolved** | [PublicEventController.php:77-80](app/Http/Controllers/PublicEventController.php#L77-L80) | Pre-reg → potential_match → never confirmed → orphaned phantoms in the data |
| 12 | **Loader role can read intake/scanner data** | [EventDayController.php:91-150](app/Http/Controllers/EventDayController.php#L91-L150) — same `data` endpoint, no role filter | Privacy stripping happens *only* in the loader/exit response shape, not enforced server-side per role |
| 13 | **Vehicle info on `households`, not `visits`** | migration `add_vehicle_info_to_households_table` | Family arrives in a friend's car → vehicle_make permanently overwritten |
| 14 | **No backward state protection on supervisor override** | [VisitMonitorController.php:152-195](app/Http/Controllers/VisitMonitorController.php#L152-L195) | "Whoops, undo" not supported; only forward jumps |
| 15 | **No audit trail anywhere** | No model events, no audit table | Cannot answer "who deleted that household at 2:14pm?" |

---

## PART 4 — DATA INTEGRITY & LOGIC REVIEW

### Household structure
- ✅ Self-referential FK is simple and works.
- ❌ **No protection against circular representation chains** (A→B, B→C, C→A). [HouseholdService::attach](app/Services/HouseholdService.php) checks self-reference but not cycles.
- ❌ **No cascading rules** when a representative is deleted: dependents get `nullOnDelete`, orphaning the families silently.

### Representative logic
- ❌ **Pre-registration of a represented household is not reconciled** with check-in via the representative. Two records, both look "served."
- ❌ Represented set is taken from the form `represented_ids`, *replacing* the persisted relationship — staff omission silently drops people from this visit.

### Visit tracking
- ❌ **Visits do not snapshot demographics**. `visit_households` has no `children_count_at_visit`, `adults_count_at_visit`, etc. Reporting reads live values. Every household edit retroactively rewrites history.
- ❌ Vehicle is on Household, not Visit, so a one-time different car overwrites what was there.

### First-timer detection
- ⚠ Computed by correlated subquery on each row of [HouseholdController index](app/Http/Controllers/HouseholdController.php) (lines 58-67). Correct in principle but **N+1 scale risk** with thousands of households, and pagination won't save you because the subquery runs per-row.
- ⚠ Definition is "exactly 1 distinct event in `visit_households`." If a family checks in twice in one event (because of bug #5), they're still "first-timer" — which is correct definitionally but proves the system depends on luck.

### Demographic tracking
- ✅ Three counters + computed total.
- ❌ Forced minimum of `household_size = 1` even if all counts are zero (HouseholdService::applyDemographics) — semantically wrong.

### Inventory vs actual distribution
- ❌ **Three different "distributed" numbers exist and none agree:**
  1. `Visit.served_bags` (set per visit, sometimes)
  2. `EventInventoryAllocation.distributed_quantity` (manually typed, often skipped)
  3. `InventoryMovement.event_distributed` (the constant exists; rows never get created)
- ❌ Reports pull from #3 → typically zero. Dashboards likely read #1 or #2 → different number.

### Finance vs actual cost
- ❌ Finance and Inventory are **fully siloed** — no purchase order, no COGS, no link from received goods to bills paid. "Cost per family served" is unanswerable.
- ⚠ FinanceTransaction has optional `event_id`; almost certainly underused in practice.

---

## PART 5 — UX / SPEED ANALYSIS

### Quick check-in: fast enough?
- 7 fields for `quickAdd` is acceptable for a walk-in. A barcode-only "I scanned a returning family's QR, just check them in" flow exists via `qr_token` search — but **no auto-submit on scan**. Staff still has to tap the row, then "Check In," then dismiss the modal. **Best case ~3 taps; with represented households, easily 8-12.**

### Click count for representative pickups
- For a 3-family pickup with one new represented household:
  1. Search representative → 1 tap.
  2. Open check-in modal → 1.
  3. "Add represented" → 1.
  4. Search/find each existing family → 2 each = 4.
  5. "Create represented" for the new family, fill 7 fields → ~10 keystrokes + 1 tap.
  6. Confirm visit → 1.
- That's **roughly 18 interactions per representative visit.** Under time pressure with frustrated drivers, errors are guaranteed.

### Loader card design
- Loader sees: `bags_needed`, demographics, vehicle. Reasonable. **But**: bags_needed is a number with no breakdown ("3 bags = 1 dry + 1 produce + 1 frozen?" — unknown). Loader has to remember the rules.

### Scanner / drag-drop reliability
- SortableJS is solid client-side. Server-side persistence is the broken part (Part 3 #2).
- On mobile/touch tablets: drag handles are present, animation OK. Latency at 10 s polling means the order another role sees is stale.

### Suggestions
- **Auto-submit on QR scan** for returning households with no representatives.
- Combine search + check-in into a single screen with implicit lane assignment by least-loaded lane.
- Show **bag composition breakdown** on the loader card.
- **Confirm-on-exit** modal showing bags and household count to catch loader errors before the car drives away.
- Add a **physical-line resync** button: "the car at front is now lane 2 position 1 — click here to renumber from this row." Currently the only fix is to drag everyone, which takes seconds the team doesn't have.

---

## PART 6 — QUEUE SYSTEM DEEP REVIEW

### Lane system
- Lane is a numeric column on `Visit`. Lanes are not first-class entities — there's no `lanes` table with capacity, status (`open`/`closed`), or assigned staff. Adding/closing a lane mid-event has no system support beyond changing what `lane` numbers the intake form offers.

### Drag-and-drop behavior
- Client UX: works. SortableJS handles cross-lane drags.
- Server: **per-row updates inside a `foreach`**, no transaction, no row lock, no version check ([EventDayController.php:196-202](app/Http/Controllers/EventDayController.php#L196-L202)).
- Cross-role consistency: the scanner reorders, the loader's next 10-s poll reflects it. If both roles drag concurrently, last-write-wins.

### Sync between roles
- Polling, not events. Means nothing changes in real time. A vehicle marked `loaded` on the loader's tablet only disappears from the scanner's view 0–10 s later. In a short panic window, the same vehicle can be acted on by two roles.

### Recovery from physical-line vs digital-queue mismatch
- **None automatic.** A staffer must manually drag rows to match the cars they see outside.
- **No "shift everyone up by one" macro**, no "swap with previous," no "this car is gone, renumber." All manual, all racy.

### Admin override
- Only via the supervisor monitor. Override is forward-only and has no audit log. There is **no rollback** if someone overrides incorrectly — the previous status is gone.

---

## PART 7 — REPORTING ACCURACY

| Metric | Source | Problem |
|---|---|---|
| Households served | `COUNT(DISTINCT visit_households.household_id)` over exited visits | A representative + 3 represented = 4 households served. Both this and "1 visit" are reported in different places. **Inconsistent denominators.** |
| People served | `SUM(households.household_size)` (live) | Edit any household later → past reports change. Funder report submitted in May ≠ regenerated in June. |
| Children / Adults / Seniors | Live demographics | Same drift problem. |
| First-timers | Correlated subquery (current state) | A household visiting once is "first-timer" until they visit a second event — fine, but slow on large tables. |
| Represented households | Implicit via `representative_household_id` | Reps and represented are both in `visit_households`; no clear "represented count" metric exists in `ReportAnalyticsService`. |
| Volunteer hours | Likely manual; `checked_out_at` never written | Whatever appears here is either zero or hand-typed. |
| Inventory used | `event_distributed` movements | Always 0 because no code path creates that movement type. The "distribution" report is fictional. |
| Finance | `FinanceTransaction` filtered by `event_id` | `event_id` rarely populated → cost-per-event is mostly null. |

**Bottom line: every operational metric has at least one credibility problem.** The system can produce numbers; it cannot stand behind them.

---

## PART 8 — VOLUNTEER FLOW REVIEW

- ✅ Public check-in URL is a smooth flow (search → check-in or self-signup).
- ✅ Walk-in onboarding is two fields (`first_name`, `last_name`) → instant check-in. Genuinely fast.
- ❌ **No checkout.** The `checked_out_at` field is dead. Hours can't be computed; the field exists as a tombstone.
- ❌ **No verification on signup.** Anyone with the URL can create a volunteer record. "Volunteers checked in: 200" might include 47 spam entries.
- ❌ Public search by name leaks volunteer names ([PublicVolunteerCheckInController.php:49-66](app/Http/Controllers/PublicVolunteerCheckInController.php#L49-L66)) to anyone who can guess the URL.
- ❌ Service history is recorded, but reports of "hours served" are unsupported by the data.

### Suggestions
- Add a "Check Out" tile on the same public page (or auto-checkout at event end + 1 hour).
- Require a phone or email at signup, send a one-time confirmation link before creating the record.
- Hash search results or require a one-time event PIN before exposing volunteer names.

---

## PART 9 — INVENTORY & DISTRIBUTION REALITY CHECK

This subsystem is the most operationally broken.

- **Inventory does not reflect distribution.** When a vehicle is loaded and marked `loaded`, no `InventoryMovement` is created and no `quantity_on_hand` is decremented ([VisitMonitorController.php:182-189](app/Http/Controllers/VisitMonitorController.php#L182-L189)).
- **Bags are not tracked per household.** `served_bags` is a count, not a composition.
- **Zero-stock mid-event has no protection at the load step.** The system will let you "load" a car for a bag composition you don't have stock for.
- **Expiry tracking is informational only.** Expired items aren't excluded from allocation; a loader can hand out expired food and the system won't know.

### Minimum viable fix
- Wire a service so that on `markLoaded`, a database transaction:
  1. Loads the AllocationRuleset bag composition.
  2. Iterates each component item, creates an `InventoryMovement` of type `event_distributed` with the right quantity.
  3. Decrements `EventInventoryAllocation.distributed_quantity`.
  4. Refuses to commit if any item would go negative — fall back to a manual override path.

Without this, "we served 350 households today" cannot be tied to any real food.

---

## PART 10 — SECURITY & CONTROL

### High-severity findings
- **Privilege escalation:** [UpdateUserRequest::authorize()](app/Http/Requests/UpdateUserRequest.php#L12-L14) returns `true`. Combined with `role_id` in `$fillable` on User, **any authenticated user can promote themselves or anyone else to ADMIN.** Add a check that requires `users.edit` and that role changes require ADMIN.
- **Public endpoints have no rate limit.** `/register/{event}`, `/review`, `/volunteer-checkin/*`, and `/{role}/{event}/auth` all accept unlimited POSTs from any IP. Brute-forcing 4-digit auth codes is straightforward.
- **Codes are stored plaintext** in `events`. A leaked DB snapshot exposes every event's codes for every role.
- **PII in JSON responses** ([CheckInController](app/Http/Controllers/CheckInController.php) `householdPayload`): phone, email, address, vehicle, demographics returned to anyone with `checkin.view`. No granularity below "any staff."
- **Resource-ownership checks missing.** Any authenticated user can `GET /households/{id}` for any id, or `PATCH /households/{id}`. There is no policy or per-org scoping.

### Medium-severity findings
- **No audit log** for settings changes, role changes, or household edits.
- **`StoreReviewRequest::authorize()` returns true**; combined with `is_visible` in `$fillable` on `EventReview`, public reviewers can self-publish, bypassing moderation when settings happen to allow.
- **Session has no IP/UA pinning** for public auth-code sessions; a stolen session lasts until status flips.
- **No CAPTCHA** on the auth-code form even after N failures.

### Low-severity findings
- 8-character minimum password (configurable up). No complexity by default.
- Volunteer name enumeration via public search is a soft privacy issue.

---

## PART 11 — PRIORITIZED IMPROVEMENTS

### CRITICAL (must fix before any real event)
1. **Fix `UpdateUserRequest::authorize()` and `StoreUserRequest::authorize()`** to require admin (or `users.edit`) and remove `role_id` from generic mass-assignment paths. *(One-line bug, biggest blast radius.)*
2. **Wire automatic inventory decrement on visit loaded** — create `event_distributed` movements in a transaction. Without this, every report from this system is fiction.
3. **Add a unique constraint `(event_id, lane, queue_position)` and use a `SELECT ... FOR UPDATE` in `EventCheckInService::checkIn`** to fix the race that produces duplicate positions.
4. **Wrap `reorder()` in a transaction with row locks**, or accept a single bulk reorder that recomputes positions from a full list rather than per-row updates.
5. **Rate-limit public POST endpoints**: `/register/{event}`, `/review`, `/volunteer-checkin/*`, and especially the auth-code submission. `throttle:5,1` is the floor.
6. **Lengthen and randomize event-day codes** to ≥6 alphanumeric (~2B possibilities), use constant-time comparison, and lock out after N failures per IP.
7. **Schedule `SyncEventStatuses`** in [routes/console.php](routes/console.php) (`Schedule::command('events:sync-statuses')->daily()`) and verify cron / Task Scheduler is running.

### HIGH
8. **Snapshot demographics on `visit_households`** at check-in time so historical reports stop drifting.
9. **One-visit-per-event-per-household constraint** at the service layer, with an explicit override flag for legitimate redos.
10. **Resolve pre-registration `match_status`**: surface `potential_match` rows in a check-in reconciliation panel and require a confirm/reject before the household is treated as served.
11. **Add resource-ownership / policy checks** on Household, Review, Visit endpoints.
12. **Audit log table** for role changes, settings changes, household edits, and supervisor status overrides.
13. **Zero-stock guard on mark-loaded** — refuse with a clear message if any allocated item would go negative.
14. **Reconcile `served_bags` with allocation movements** in a nightly check; alert on divergence.

### MEDIUM
15. **WebSocket / SSE** push for queue updates instead of 10s polling. Even a 1s polling interval would help; pusher/laravel-echo would be much better.
16. **Move vehicle from Household to Visit**, or at least add `vehicle_at_visit` snapshot fields, so a borrowed car doesn't overwrite history.
17. **Bag-composition display on loader card** (not just a count).
18. **Auto-checkout volunteers** at event end + 1 h, or surface a reminder pop-up.
19. **Cycle-prevention on representative chains** (A→B→C→A).
20. **Add `volunteers.signup` and review submissions behind a one-time email/SMS verification** to combat fake records.
21. **Index `households.first_name`, `last_name`, `phone`, `qr_token`** if not already, plus the correlated first-timer subquery — convert to a precomputed `last_visit_count` field updated by an event listener.

### LOW
22. Hash auth codes at rest.
23. CAPTCHA on auth-code page after 3 failures.
24. Better duplicate detection (email/phone fuzzy match) at household creation.
25. Add a `lanes` table for first-class lane management (open/closed, capacity, staff assigned).
26. Move PII out of generic JSON responses; introduce explicit Resource classes with role-based field exposure.

---

## PART 12 — FINAL RECOMMENDATIONS

### Overall real-world readiness
**4 / 10.** The data model is decent. The check-in UX is functional. Everything *between* check-in and reporting is broken: races, missing inventory wiring, missing audit, retroactive metrics, and trivial-to-bypass auth. This is "demo-ready," not "300-families-on-a-Saturday-ready."

### Biggest single risk if deployed today
**Inventory and reporting are dishonest.** Every grant report and every "how much did we distribute" question will be answered from data the system isn't actually maintaining. A funder asking pointed questions could find that "1,200 families served" cannot be reconciled to any inventory record. That is a reputational risk far worse than a bad event day.

### Must fix BEFORE first live event
1. `UpdateUserRequest` / `StoreUserRequest` authorize bug (5-minute fix, prevents privilege escalation).
2. Automatic inventory decrement on `markLoaded` (1–2 days, makes reports trustworthy).
3. Queue position race + reorder transaction (1 day, prevents queue corruption under load).
4. Rate-limit public endpoints + lengthen auth codes (half a day, prevents trivial abuse).
5. Schedule `SyncEventStatuses` (15 minutes, prevents stuck statuses).
6. Demographics snapshot on `visit_households` (half a day, makes historical reports stable).

### Can wait until later
- WebSocket real-time queue (a 5s poll is fine for v1).
- Audit logging (nice to have; logs can fill the gap short-term).
- Finance ↔ Inventory linkage (cost analysis is a feature, not a launch blocker).
- Cycle prevention on representative chains (rare in practice).
- Improved duplicate detection (warnings already exist; add hard rules later).

### One-line summary
**The codebase looks like a complete system but operates like a demo: fast paths exist, slow paths are silently broken, and the numbers you'd actually report to a board cannot be defended by the underlying data.** Fix the top 6 in the "must" list and you have something that can run a real event without lying about what it did.

---

## PART 13 — PHASED REMEDIATION PLAN

A concrete, ordered work plan with the **method** (technical approach) and **procedure** (step-by-step) for each phase. Each phase has a defined acceptance test so you know when it's actually done — not just merged.

> **Working rule for all phases:** every change ships with (a) a feature branch, (b) a regression smoke test against a 50-household seeded event, (c) a one-paragraph note in `CHANGELOG.md`. No exceptions.

---

### PHASE 0 — STOP-THE-BLEEDING (≤ 1 hour, do today)

**Goal:** kill the two blockers that make any further work risky — privilege escalation and stuck event statuses.

#### 0.1 Lock down `UpdateUserRequest` / `StoreUserRequest`
- **Method:** replace the unconditional `return true` with a permission check; remove `role_id` from default mass-assignment for non-admin updates.
- **Procedure:**
  1. In [app/Http/Requests/StoreUserRequest.php](app/Http/Requests/StoreUserRequest.php) and [app/Http/Requests/UpdateUserRequest.php](app/Http/Requests/UpdateUserRequest.php), change `authorize()` to:
     ```php
     return $this->user()?->hasPermission('users.create' /* or users.edit */) ?? false;
     ```
  2. In [app/Http/Controllers/UserController.php](app/Http/Controllers/UserController.php), wrap any `role_id` change with an explicit `if ($this->user()->isAdmin()) { ... }` guard.
  3. Confirm `users.create` / `users.edit` are only on the ADMIN role in [database/seeders/RoleSeeder.php](database/seeders/RoleSeeder.php).
- **Accept:** logged-in non-admin POSTs to `/users` and PUTs to `/users/{id}` return 403. Admin still works.

#### 0.2 Schedule `SyncEventStatuses`
- **Method:** wire the artisan command into Laravel's scheduler and confirm the host runs `schedule:run` every minute.
- **Procedure:**
  1. In [routes/console.php](routes/console.php) add:
     ```php
     Schedule::command('events:sync-statuses')->dailyAt('00:05')->withoutOverlapping();
     ```
  2. Add a Windows Task Scheduler entry (or cron on Linux) to run `php artisan schedule:run` every minute. Document the entry in `README.md`.
  3. Test by setting a test event's date to today and watching status flip on next run.
- **Accept:** an event with `date = today` and `status = upcoming` flips to `current` automatically; codes activate without manual intervention.

---

### PHASE 1 — DATA INTEGRITY FOUNDATIONS (Week 1, ~3 days)

**Goal:** stop the queue from corrupting itself and stop reports from being rewritten retroactively.

#### 1.1 Queue-position race and reorder transaction
- **Method:** add a database unique index on `(event_id, lane, queue_position)`, wrap `EventCheckInService::checkIn` in a `DB::transaction` with a `SELECT ... FOR UPDATE` on existing visits in the same lane, and wrap `EventDayController::reorder` in a transaction that recomputes positions from the full submitted list.
- **Procedure:**
  1. New migration: `add_unique_index_event_lane_position_to_visits.php` — `unique(['event_id','lane','queue_position'])`.
  2. Refactor [EventCheckInService.php:87-89](app/Services/EventCheckInService.php#L87-L89):
     ```php
     return DB::transaction(function () use ($event, $lane, ...) {
         $next = Visit::where('event_id', $event->id)
                      ->where('lane', $lane)
                      ->lockForUpdate()
                      ->max('queue_position') + 1;
         // ... create visit
     });
     ```
  3. Refactor [EventDayController.php:180-206](app/Http/Controllers/EventDayController.php#L180-L206) to receive the **full ordered list** for affected lanes and rewrite positions from 1..N inside `DB::transaction`. Reject the request if any visit doesn't belong to the event.
  4. Add a `version` (or `updated_at`) optimistic check: if the row has been touched since the client read it, return 409 and let the UI reload.
- **Accept:** running 20 concurrent `php artisan tinker` inserts on the same lane never produces duplicate positions. Two simultaneous reorders never lose a move.

#### 1.2 Demographics + vehicle snapshot on `visit_households`
- **Method:** add snapshot columns to the junction table; populate at attach time; switch reporting to read from the snapshot.
- **Procedure:**
  1. Migration: add `children_count`, `adults_count`, `seniors_count`, `household_size`, `vehicle_make`, `vehicle_color` to `visit_households`. Backfill with current household values for existing rows.
  2. In [EventCheckInService.php](app/Services/EventCheckInService.php), when calling `visit->households()->attach()`, pass the pivot payload with the snapshot fields.
  3. Update [ReportAnalyticsService.php:57-62](app/Services/ReportAnalyticsService.php#L57-L62) to `SUM(visit_households.household_size)` instead of joining live households.
  4. Update the same query for children/adults/seniors.
- **Accept:** edit a household's size after a visit; previous reports do not change. New visits use the new size.

#### 1.3 One-visit-per-household-per-event constraint
- **Method:** unique constraint on `(event_id, household_id)` in `visit_households` *via the visit*, plus a service-layer check with an `--override` flag for legitimate redos.
- **Procedure:**
  1. In [EventCheckInService.php](app/Services/EventCheckInService.php), before attaching: `if (Visit::where('event_id', $event->id)->whereHas('households', fn($q) => $q->whereIn('households.id', $allIds))->exists())` → throw a `HouseholdAlreadyServedException` unless `$force === true`.
  2. Surface a "this family was already served today — override?" modal in the check-in UI.
  3. Log every override with the user id and reason in an `audit_log` table (Phase 4 will formalize this; for now write to `Log::warning`).
- **Accept:** checking the same household into the same event twice without override is rejected with a clear error.

---

### PHASE 2 — REPORTING TRUTH (Week 1–2, ~3 days)

**Goal:** make every distribution number traceable to an inventory movement. After this phase, "households served" can be defended in a board meeting.

#### 2.1 Auto-decrement inventory on `markLoaded`
- **Method:** introduce a `DistributionPostingService` that, on visit transition to `loaded`, computes the bag composition from the active `AllocationRuleset`, creates one `InventoryMovement` of type `event_distributed` per component item, and decrements `EventInventoryAllocation.distributed_quantity` and `InventoryItem.quantity_on_hand` — all in one DB transaction.
- **Procedure:**
  1. Create `app/Services/DistributionPostingService.php` with a single method `postForVisit(Visit $visit): void`.
  2. Inside a `DB::transaction`:
     - Resolve event's `AllocationRuleset` and bag composition (define a `bag_composition` schema if not already explicit).
     - For each component: `(item_id, qty_per_household × household_count)`.
     - Verify `InventoryItem::lockForUpdate()->find($itemId)->quantity_on_hand >= needed`. If insufficient, throw `InsufficientStockException` and **do not** mark the visit as loaded — caller must show a manual override path.
     - Create `InventoryMovement::create([...'movement_type' => 'event_distributed'...])`.
     - Update `EventInventoryAllocation::distributed_quantity += needed` and `InventoryItem::quantity_on_hand -= needed`.
  3. Call this from [EventDayController::markLoaded](app/Http/Controllers/EventDayController.php#L225-L238) and the supervisor override path in [VisitMonitorController.php](app/Http/Controllers/VisitMonitorController.php).
  4. Backfill existing exited visits **only if** ops confirms historical data was zeroed elsewhere — otherwise leave history alone and add a one-off reconciliation report.
- **Accept:** running an event of 50 visits causes `InventoryItem.quantity_on_hand` to decrease by exactly the rule-derived quantity, with a corresponding `event_distributed` movement per item per visit. Reports now show non-zero distribution.

#### 2.2 Reconciliation report
- **Method:** new artisan command `inventory:reconcile {event}` that diffs `Visit.served_bags`, `EventInventoryAllocation.distributed_quantity`, and `SUM(InventoryMovement::event_distributed)` and prints/emails the deltas.
- **Procedure:**
  1. Create `app/Console/Commands/ReconcileInventory.php`.
  2. Schedule nightly via `routes/console.php`.
  3. Email or Slack delta > threshold to admins.
- **Accept:** running `php artisan inventory:reconcile {event}` produces a 0-row delta on a clean event.

---

### PHASE 3 — PUBLIC-SURFACE HARDENING (Week 2, ~2 days)

**Goal:** make the public endpoints survive contact with the open internet.

#### 3.1 Rate-limit public endpoints
- **Method:** Laravel's `throttle` middleware with per-IP limits. Strict on auth-code POSTs.
- **Procedure:**
  1. In [routes/web.php](routes/web.php):
     ```php
     Route::middleware('throttle:5,1')->group(function () {
         Route::post('/register/{event}', ...);
         Route::post('/review', ...);
         Route::post('/volunteer-checkin/{...}', ...);
     });
     Route::post('/{role}/{event}/auth', ...)->middleware('throttle:5,1');
     ```
  2. In `bootstrap/app.php`, register a custom limiter `auth-code` keyed by `IP + role + event_id` with `RateLimiter::for('auth-code', fn ($r) => Limit::perMinute(5)->by(...))` and apply via `throttle:auth-code`.
  3. After 5 failed code attempts, return 429 with `Retry-After`.
- **Accept:** scripted brute-force of `/intake/{event}/auth` is blocked at the 6th attempt within a minute.

#### 3.2 Lengthen and harden event-day codes
- **Method:** change code generation to ≥6 alphanumeric, store hashed, verify with `hash_equals`.
- **Procedure:**
  1. Migration: add `intake_auth_code_hash`, `scanner_auth_code_hash`, etc. (nullable while migrating).
  2. Update [Event.php:49-54](app/Models/Event.php#L49-L54) to generate `Str::upper(Str::random(6))` and store `Hash::make($code)`. Show plaintext **once** in the UI on generation/regeneration.
  3. Update [EventDayController.php:60-75](app/Http/Controllers/EventDayController.php#L60-L75) to compare with `Hash::check($input, $event->intake_auth_code_hash)`.
  4. Add a one-time migration script that regenerates codes for all `current` and `upcoming` events and emails the new codes to event managers.
  5. Drop the plaintext columns in a follow-up migration after confirming no caller reads them.
- **Accept:** codes are 6 chars, hashed at rest, brute-force needs ~36⁶ ≈ 2B attempts.

#### 3.3 Mass-assignment cleanup
- **Method:** explicit `$request->only([...])` in every controller create/update; remove sensitive fields (`is_visible`, `role_id`, `representative_household_id` when set by non-admin) from generic flows.
- **Procedure:**
  1. Audit each controller listed in Part 10. Replace `Model::create($request->validated())` with explicit allow-lists.
  2. For [EventReview.php](app/Models/EventReview.php), remove `is_visible` from `$fillable` and add a separate admin-only setter.
- **Accept:** a public POST to `/review` with `is_visible=1` ignores that field.

---

### PHASE 4 — AUTHORIZATION & AUDIT (Week 3, ~3 days)

**Goal:** policies for every resource, an audit log for every privileged action.

#### 4.1 Resource ownership / policies
- **Method:** generate Laravel Policies; register in `AuthServiceProvider`; call `$this->authorize()` in every controller method that touches a model by id.
- **Procedure:**
  1. `php artisan make:policy HouseholdPolicy --model=Household` (and Visit, Review, Event, Volunteer).
  2. Implement `viewAny`, `view`, `create`, `update`, `delete` per role.
  3. In each controller, replace bare `Model::find($id)` with `$this->authorize('view', $model)`.
  4. For routes still without granular perms, add `permission:` middleware in [routes/web.php](routes/web.php).
- **Accept:** an INTAKE-role user gets 403 trying to PUT `/households/{id}` for any household not in their assigned event scope.

#### 4.2 Audit log
- **Method:** new `audit_logs` table; a `Loggable` trait fired from model `saving`/`deleting` events; a middleware that captures admin API calls.
- **Procedure:**
  1. Migration: `audit_logs(id, user_id, action, target_type, target_id, before_json, after_json, ip, user_agent, created_at)`.
  2. Trait `app/Models/Concerns/Auditable.php` that attaches model events to write rows.
  3. Apply on `User`, `Role`, `AppSetting`, `Household` (sensitive edits), `Visit` (status overrides), `EventInventoryAllocation`.
  4. Admin-only `/audit-logs` page with filters.
- **Accept:** every role change, settings change, and visit-status override is queryable with `who/when/what`.

---

### PHASE 5 — WORKFLOW & UX QUALITY (Week 3–4, ~4 days)

**Goal:** reduce taps, increase clarity, and reconcile pre-registrations.

#### 5.1 Bag composition on loader card
- **Method:** loader endpoint returns `bag_breakdown: [{item, qty}, …]`; loader view renders chips.
- **Procedure:**
  1. Extend [EventDayController::data](app/Http/Controllers/EventDayController.php#L91-L150) to attach the resolved bag composition per visit.
  2. Update `loader.blade.php` to render a compact breakdown.
- **Accept:** loader sees the items + counts at a glance, not just a number.

#### 5.2 Pre-registration reconciliation panel
- **Method:** a new `/check-in/reconcile/{event}` page lists `EventPreRegistration.match_status = 'potential_match'` rows; staff confirms/rejects and the system links or removes.
- **Procedure:**
  1. Controller method on [CheckInController](app/Http/Controllers/CheckInController.php) `reconcile($event)`.
  2. View with two-column "pre-reg vs candidate household" diff and Confirm/Reject buttons.
  3. On confirm: set `match_status = 'matched'`, set `household_id` to the canonical record, soft-delete the duplicate.
- **Accept:** every pre-reg with `potential_match` either becomes `matched` or `rejected` before event-day end-of-day.

#### 5.3 Volunteer auto-checkout
- **Method:** scheduled job at event end + 1h sets `checked_out_at = event.end_time` for any volunteer still open; surfaces a banner on the public check-in page.
- **Procedure:**
  1. New artisan command `volunteers:auto-checkout` scheduled hourly.
  2. Add explicit "Check Out" button on the public volunteer check-in page.
  3. Compute `hours_served` as `(checked_out_at - checked_in_at)` in a model accessor.
- **Accept:** at event-end + 1h, every volunteer has a `checked_out_at`. Reports show non-zero hours.

#### 5.4 Zero-stock guard at mark-loaded (already partially in 2.1)
- **Method:** the `InsufficientStockException` from 2.1 surfaces as a user-facing modal with three options: "Skip item", "Substitute (manual)", "Cancel".
- **Procedure:**
  1. UI handler for 422 response from `markLoaded`.
  2. New endpoint `/visits/{id}/load-with-overrides` accepts an explicit substitution payload; logs to audit.
- **Accept:** running an event past the supply for one item shows the modal, not a 500.

---

### PHASE 6 — LONG-TERM POLISH (Backlog, schedule when capacity allows)

These are real wins, but they don't block a credible v1.

| # | Item | Method (sketch) |
|---|---|---|
| 6.1 | Replace polling with WebSockets | Laravel Reverb or Pusher; broadcast `VisitStatusChanged` events; subscribe queue board to channel `event.{id}` |
| 6.2 | First-class `lanes` table | Migration; `Lane belongsTo Event`; add `is_open`, `capacity`, `assigned_user_id`; surface lane open/close UI |
| 6.3 | Cycle prevention on representative chains | DFS check in `HouseholdService::attach`; reject if would create a cycle |
| 6.4 | CAPTCHA on auth-code form after N failures | hCaptcha or Cloudflare Turnstile; gate the auth-code POST after 3 failed attempts |
| 6.5 | Better duplicate detection at household creation | Soundex / metaphone + phone+address fuzzy match in `HouseholdService::create`; show candidates and require staff confirm |
| 6.6 | Finance ↔ Inventory link | New `purchase_orders` table; receive-goods workflow creates `InventoryMovement(stock_in)` + `FinanceTransaction(expense)`; enables real COGS |
| 6.7 | Replace correlated first-timer subquery with `households.lifetime_visit_count` | Maintained by a `VisitCreated`/`VisitDeleted` event listener; add Eloquent observer |
| 6.8 | Resource classes for all JSON responses | `php artisan make:resource HouseholdResource`; per-role field exposure (e.g., loader sees no name); migrate every controller `->json(...)` |
| 6.9 | Volunteer signup verification | One-time email or SMS confirmation before creating volunteer record |
| 6.10 | Granular settings audit + role permission UI | UI to grant/revoke individual permissions on roles, with audit |

---

### EXECUTION CHECKLIST (PRINTABLE)

- [ ] **Phase 0** complete — privilege escalation closed, scheduler running
- [ ] **Phase 1** complete — queue-position race fixed, demographics snapshotted, one-visit-per-event enforced
- [ ] **Phase 2** complete — every loaded visit creates inventory movements; nightly reconcile produces 0 delta
- [ ] **Phase 3** complete — public endpoints rate-limited; codes ≥6 chars, hashed; mass-assignment cleaned
- [ ] **Phase 4** complete — policies on all resources; audit log live for users/roles/settings/visit overrides
- [ ] **Phase 5** complete — loader bag breakdown, pre-reg reconciliation, volunteer auto-checkout, zero-stock UX
- [ ] **Phase 6** items prioritized in the backlog with owners and rough sizing

### TIMELINE AT A GLANCE

| Week | Focus | Outcome |
|---|---|---|
| Day 0 | Phase 0 | No more privilege escalation; statuses flip on schedule |
| Week 1 | Phase 1 + start Phase 2 | Queue is concurrency-safe; reports stop drifting; inventory wiring begins |
| Week 2 | Finish Phase 2 + Phase 3 | Distribution numbers are real and traceable; public surface is hardened |
| Week 3 | Phase 4 + start Phase 5 | Authorization is enforced; audit log live; loader UX upgraded |
| Week 4 | Finish Phase 5 | Pre-reg clean, volunteer hours real, zero-stock UX tested |
| Backlog | Phase 6 | WebSockets, COGS, lanes table, etc. as bandwidth allows |

**By end of Week 4, the system is genuinely event-ready for 300–500 households per event with defensible reporting.**
