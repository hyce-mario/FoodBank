# Session Handoff

> Rewritten at the end of every session, or whenever context approaches the yellow flag (~70% used). A new agent reading **only this file plus AUDIT_REPORT.md Part 13** should be able to resume cleanly.

---

## Current state — 2026-05-10 (Session 13 mid-session — **Phase 6.5.d household merge shipped (uncommitted)**)

### TL;DR for the next agent

**FoodBank is LIVE in production at `https://ngo.heyjaytechnologies.com`.** Session 13 picked up an open carry-forward item that had fallen off the HANDOFF rewrite during Session 12 — the **Phase 6.5.d atomic household merge tool**. It is now implemented and tested but **uncommitted**. The work is the household analogue of the Phase 5.8 volunteer merge: pick keeper + duplicate from the household Show page → atomic transfer of all visits / pre-regs / pledges / represented households / check-in overrides (incl. JSON `household_ids` rewrite) / `events_attended_count` recompute → duplicate deleted. Three conflict cases handled: open-visit-same-event refused, representative-cycle refused, confirmed-preg-same-event auto-cancelled (more forgiving than the volunteer pattern — a stale pre-reg is benign). Lock ordering by ID prevents deadlock under concurrent merges; all JSON manipulation kept in PHP for sqlite test parity; portable subquery for the count recompute. 19 new tests; suite 716 → 735. **No schema migration required.**

Session 12 (2026-05-07) closed the prior wave: RBAC hardening + permanent guardrails + bot defense — see prior session block below.

1. UI-side fix for the original report (sidebar + dashboard widget gating per permission).
2. Bot defense on every public-write endpoint (registration, review, volunteer signup) via honeypot + HMAC-signed time trap.
3. Settings-write defense-in-depth (route middleware + controller guard + UI hide-the-button) closing the "Sys Admin can save settings" report — almost certainly a stale `route:cache` on production.
4. **Two permanent automated guardrails** (`RbacRouteAuditTest` + `RbacNoPermissionSmokeTest`) that fail CI when any new authenticated route is added without a gate, OR when any zero-permission user accesses an admin page. Means "I created a role and now this leaks" is a CI failure going forward, not a user report.
5. Catalog-validate role permissions (`Rule::in(...)`) so typo'd permission strings can't be saved.
6. Defense-in-depth route-middleware split on households/events/volunteers/volunteer-groups resources (matches inventory + purchase_orders pattern). `php artisan route:list` now shows the gate explicitly for every action.
7. `DEPLOY.md` clear-then-cache flow + `docs/10-rbac.md` contributor checklist + catalog/default-roles tables synced to source of truth.

Working tree clean. 711 feature tests passing.

### Where we are

| Area | Status |
|---|---|
| `main` branch | 7 new commits ahead of origin (last known push: `ef5369e`); local `origin/main` ref shows up-to-date but user has not run `git push` since session start, so verify on next push |
| Live site | ✅ `https://ngo.heyjaytechnologies.com` — running pre-Session-12 code (`ef5369e`) until pushed |
| Suite | **735 feature tests passing** (was 716 at end of Session 12 + dismissAttendee + public-registration fixes; +19 from Phase 6.5.d HouseholdMergeTest) |
| Working tree | **Phase 6.5.d files unstaged** — see "What's next" §A |
| Git identity | Local repo: `user.name=YTobby`, `user.email=digienergy0@gmail.com` |

### What landed in Session 12 (7 commits)

1. **`89d3f8c`** `fix(rbac): gate sidebar + dashboard widgets per permission`
   - Sidebar in `layouts/app.blade.php`: every `<li>` now wrapped in `@can('<perm>')` (or `@can('viewAny', Model::class)` for policy-backed resources). ADMIN's `*` wildcard passes via the `Gate::before` bridge. Roles see only items their perms cover.
   - Dashboard data computation in `DashboardController::index()` is now per-permission. Only widgets the user can see compute their data; others get zero/empty defaults. Per-widget `@if($canX)` in the blade hides them. Empty-state card shown when no widgets qualify. Closes the original report (the `xyz` test role with only `households.create` was seeing "everything" in the sidebar).

2. **`139bcc7`** `chore(dashboard): tweak stat card labels`
   - "Food Bundles Served" → "Food Pack Served" (capitalisation), "People Served" → "Family Members Served" (semantically aligned with the underlying SUM(household_size)).

3. **`622a79d`** `feat(security): bot defense on public registration + review forms`
   - New `BotDefense` middleware (`app/Http/Middleware/BotDefense.php`) — two layers:
     - Honeypot field `website_url` (CSS-hidden, ARIA-hidden, tabindex=-1, autocomplete=off). Naive scrapers autofill it; if filled, request is silently dropped.
     - HMAC-signed time trap `_form_ts` — `<unix_ts>.<hmac>` keyed on APP_KEY. Verifies signature + enforces 3s minimum between render and submit.
   - `<x-bot-defense />` blade component (`resources/views/components/bot-defense.blade.php`) emits the two hidden fields. Drop into any public form inside the `<form>` tag.
   - Wired onto `POST /register/{event}` and `POST /review`. Throttle middleware still in place (5/min/IP).
   - 9 new feature tests in `BotDefenseTest`.

4. **`e23da52`** `fix(settings): defense-in-depth on settings.update + hide save UI for non-grantees`
   - Three layers now block a settings.view-only user from saving settings:
     - Route middleware (`permission:settings.update`) — primary, unchanged.
     - Controller `abort_unless()` in `SettingsController::{update, uploadBrandingAsset, deleteBrandingAsset}` — guards against stale route cache.
     - UI gating in `settings/show.blade.php` (read-only banner + `<fieldset disabled>` + save bar `@can('settings.update')`) and `branding_above.blade.php` (whole upload card hidden via `@can`).
   - 4 new feature tests in `SettingsAuthorizationTest`.
   - Most likely root cause of the original "Ben can save settings" report: stale `php artisan route:cache` on production from before the Tier 2 RBAC pass. Fixed by `DEPLOY.md` change in commit 8427ad6.

5. **`8427ad6`** `feat(security): permanent RBAC guardrails — audit test, smoke test, catalog validation, deploy hardening`
   - **`tests/Feature/RbacRouteAuditTest.php`** — static analysis via `ReflectionMethod`. Walks every authenticated route; classifies as GATED (route middleware) / POLICY (controller `$this->authorize()` or typed FormRequest containing `hasPermission(`). Anything else must be in `ALLOWLIST`. New ungated routes fail CI with a fix-suggestion message.
   - **`tests/Feature/RbacNoPermissionSmokeTest.php`** — behavioural complement. Zero-permission user hits every parameterless GET admin route; asserts not 200. Catches "policy exists but checks the wrong permission" class of bug.
   - **`scripts/rbac-audit.php`** — standalone CLI for ad-hoc spot checks: `php artisan route:list --json | php scripts/rbac-audit.php`.
   - **`Store/UpdateRoleRequest`** validate `permissions[]` against `RolePermissionService::allPermissions() + ['*']` via `Rule::in(...)`. Typo'd permission strings rejected on save.
   - **`DEPLOY.md`** update flow now `route:clear / config:clear / view:clear / event:clear` BEFORE `*:cache`. The clear is load-bearing — production Sys-Admin-can-save-settings report was almost certainly a stale route cache snapshot.

6. **`654de45`** `docs(rbac): contributor checklist for adding permissions/routes/UI + automated-guardrail reference`
   - Appends three sections to `docs/10-rbac.md`: automated guardrails, catalog validation, contributor checklists for new permissions / new routes / new admin UI elements.

7. **`07a798b`** `fix(rbac): defense-in-depth route middleware on resource routes + JSON-aware bot defense + docs sync`
   - **Resource route splits** — households / events / volunteers / volunteer-groups now split per action (matches inventory + purchase_orders pattern):
     - index/show → `<resource>.view`
     - create/store → `<resource>.create`
     - edit/update → `<resource>.edit`
     - destroy → `<resource>.delete`
   - Sibling routes (exports, regenerate-qr, attach/detach, service-history, member editor, attendees, summary, event-report) grouped under read or write middleware.
   - Why split-by-action and not resource-wide: a role with ONLY `households.create` (the original `xyz` test case) needs to reach `/households/create` without holding `households.view`. The split lets that work.
   - Controllers still call `$this->authorize()` so policies remain a defense-in-depth backup.
   - `route:list` now shows GATED middleware explicitly for every action — easier ops/audit.
   - **JSON-aware BotDefense** — middleware returns 422 JSON (instead of `back()` redirect) when `$request->expectsJson()`. Wired bot-defense onto `POST /volunteer-checkin/signup`. Other kiosk endpoints (check-in, check-out, search) require a valid volunteer_id and stay throttle-only.
   - `docs/10-rbac.md` catalog + default-roles tables synced to source of truth (was missing FINANCE / WAREHOUSE / purchase_orders / audit_logs / finance_reports).

### Tags on origin

No new tags this session. The work isn't a single phase — it's a permission-system hardening pass driven by user reports.

---

## What's next — start here on resume

### A. Commit + push the Phase 6.5.d household merge work

The Phase 6.5.d files are unstaged on the local working tree. Before committing, the next agent should re-run `php artisan test --filter=HouseholdMergeTest` (and ideally the full suite) to confirm no regression on the resume machine. Files added/modified:

- `app/Exceptions/HouseholdMergeConflictException.php` (new)
- `app/Services/HouseholdMergeService.php` (new)
- `app/Http/Controllers/HouseholdController.php` (added `merge()` method, `$mergeCandidates` in `show()`, updated constructor)
- `routes/web.php` (added `households.merge` POST under `permission:households.edit` group)
- `resources/views/households/show.blade.php` (Merge button + modal + Alpine state)
- `tests/Feature/HouseholdMergeTest.php` (new, 19 tests)
- `docs/remediation/LOG.md` (new Phase 6.5.d row)
- `docs/remediation/HANDOFF.md` (this file)

Suggested commit message (single commit; the work is one cohesive feature):

```
feat(households): Phase 6.5.d — atomic household merge tool

Drains the legacy-duplicate household backlog from before Phase 6.5.c added
fuzzy duplicate detection at create time. Mirrors the Phase 5.8 volunteer
merge shape; this version is heavier because households have more incoming
FKs (visits, pre-registrations × 2, pledges, check-in overrides FK + JSON
column, self-FK for representative chain) and a denormalised
events_attended_count cache that needs recomputing post-merge.

[full body — see HouseholdMergeService docblock for the contract]
```

After commit, push and apply the standard production deploy cycle from `DEPLOY.md`:

```bash
git push origin main
ssh heyjayte@ngo.heyjaytechnologies.com
cd ~/ngo.heyjaytechnologies.com && git pull && \
  php artisan route:clear  && php artisan config:clear && \
  php artisan view:clear   && php artisan event:clear  && \
  php artisan route:cache  && php artisan config:cache && \
  php artisan view:cache   && php artisan event:cache
```

No `npm run build` needed — pure PHP + blade changes. No new Tailwind classes (the Merge button reuses `bg-orange-600 / hover:bg-orange-700` and the modal uses `bg-amber-100 / text-amber-600` already present in the prebuilt bundle from the volunteer-merge work). No `public/build/` rebuild or scp required.

**No schema migration required** — every FK that the service writes to already exists in production. The only data manipulations are UPDATEs/DELETEs against existing columns, plus rewriting the JSON `household_ids` array on `checkin_overrides` rows in PHP via Eloquent's `array` cast.

### B. Push the Session-12 commits to production (still pending from prior handoff)

The seven Session-12 commits (89d3f8c → 07a798b) plus the two Session-12-tail bug fixes (fa3c068, 34995fd) plus this Phase 6.5.d work are all still unpushed. The deploy cycle in §A covers all of them in one push.

### C. Open work the user has signalled they may pick up

- **Notifications system** — placeholder dropdown in topbar still hardcoded. Not load-bearing. Build when asked.

### C. Phase 7.4 follow-ups (small, designed-in)

- Pledge payment plan (additive `pledge_payments` sibling table)
- Functional-classification allocation table for true IRS-990 fidelity
- Aging buckets configurable via `pledge_aging_buckets` setting

---

## Architecture notes carried forward (still load-bearing)

### Production deploy procedure

`DEPLOY.md` (project root) is the source of truth. Update flow on `main`:

1. **Local:** make changes, commit, `git push origin main`.
2. **Local:** if any frontend changes → `npm run build` (regenerates `public/build/`).
3. **Local:** if frontend changed → `scp -r public/build heyjayte@ngo.heyjaytechnologies.com:~/ngo.heyjaytechnologies.com/public/`.
4. **Server (SSH):** `cd ~/ngo.heyjaytechnologies.com && git pull && php artisan {route,config,view,event}:clear && php artisan {route,config,view,event}:cache`. **Always clear before cache** — see Session 12 commit `8427ad6` and the rationale in `docs/10-rbac.md`.
5. Verify: hit the site, force-refresh with Ctrl+Shift+R.

`public/build/` is gitignored. Don't try to commit it.

### Permission system — current shape (after Session 12)

**Catalog** (`RolePermissionService::permissionGroups()`) has 14 resource groups:

```
households       view create edit delete
events           view create edit delete
volunteers       view create edit delete
checkin          view scan
inventory        view edit
purchase_orders  view create edit receive cancel
finance          view create edit delete
reports          view export
finance_reports  view export
reviews          view moderate
audit_logs       view
users            view create edit delete
roles            view create edit delete
settings         view update
```

**Three layers of enforcement** (every gate should ideally hit ≥ 2):

1. **Route middleware** — `permission:<perm>`. Visible in `php artisan route:list`. Now applied to every action on every resource.
2. **Controller `$this->authorize()`** — routes through Policies. Still in place for households/events/volunteers/volunteer-groups (defense-in-depth).
3. **FormRequest `authorize()`** — for forms with side effects. Used by `Store/UpdateRoleRequest`, `Store/UpdateUserRequest`, `Store/UpdatePurchaseOrderRequest`, etc.

**Two automated guardrails** (run on every test pass):

- `RbacRouteAuditTest` — static. Adding a new auth route without a gate breaks CI.
- `RbacNoPermissionSmokeTest` — behavioural. Zero-permission user hits every parameterless GET admin route, asserts not 200.

**UI gating** (the original leak class):

- Sidebar nav items wrapped in `@can` per item.
- Dashboard widgets gated per permission.
- Settings save bar hidden via `@can('settings.update')` + `<fieldset disabled>` + read-only banner.
- Topbar Add-New dropdown items wrapped in `@can`.
- Quick Actions on dashboard wrapped in `@can`.

### Bot defense (Session 12)

- `BotDefense` middleware = honeypot field `website_url` + HMAC-signed `_form_ts` (≥3s min). Logs blocked attempts via `Log::warning('bot-defense.blocked', …)` — grep for spam-pattern visibility.
- `<x-bot-defense />` blade component renders the two hidden fields. Drop into any public form inside the `<form>` tag.
- Returns 422 JSON when `$request->expectsJson()`, else `back()`. Both shapes look unremarkable; bots don't get a "you tripped the trap" signal.
- Currently applied to: `POST /register/{event}`, `POST /review`, `POST /volunteer-checkin/signup`. Other kiosk endpoints require a valid volunteer_id and are throttle-only.
- **If real spam still gets through**: the next escalation is Cloudflare Turnstile (free, lighter than reCAPTCHA, no `.env` keys).

### Tailwind prebuilt CSS — Node available

Node 22.x + npm 10.x installed locally at `C:\Program Files\nodejs\`. `npm run build` works. PowerShell execution policy `RemoteSigned` (CurrentUser scope) — needed for `.ps1` scripts.

`public/build/` is gitignored. Build runs locally, then `scp` to server. Server has no Node.

### Recent UI patterns

- **Topbar dropdowns** — `x-data="{ open: false }"`, `@click.outside="open = false"`, standard `x-transition`. Zero new JS deps. Copy this pattern for any new dropdown.
- **Section-picker modal** for Event Summary uses object-shaped Alpine state with bool-per-section + helpers.
- **Top-level error summary** — `@if($errors->any())` block at the top of every form. Should be the default — silent 302-back is an awful UX trap.
- **Read-only form pattern** — see `settings/show.blade.php`: read-only banner + `<fieldset disabled>` + submit hidden via `@can`. Use for any form whose write route is gated.

### Coverage gaps (carry forward)

- `EventSummaryService` has zero PHPUnit coverage. Heuristic Evaluation rules + finance breakdown logic are pure functions that would test cleanly.
- Override modal + insufficient-stock modal — no browser-level tests (Phase 5 Dusk).
- PII retention on `checkin_overrides.reason` and `audit_logs` — Phase 5/6 retention policy.
- `overview()` / `overviewTrend()` / `trends()` use MySQL-only SQL (`TIMESTAMPDIFF`, `DATE_FORMAT`, `YEARWEEK`); not covered on sqlite.

### Working rules (carry forward)

- Thoroughness over speed; sub-tasks touching >4 files split into smaller commits — UNLESS user explicitly bundles.
- `mysqldump` before any schema migration; every migration has working `down()` AND is portable to SQLite.
- Plain-English orientation before each step; user confirms before destructive actions.
- Commit messages reference `AUDIT_REPORT.md` Part/Phase OR feature area.
- Stage explicitly — never `git add .` or `git add -A`.
- Production-grade: full migrations, FormRequests for new endpoints, HTTP feature tests for new actions, defensive guards.
- Bug-fix workflow: read `storage/logs/laravel.log` and re-run the failing command before guessing.
- When a form silently 302s back, check `$errors` first (not the form code). Add a top-level `@if($errors->any())` summary if there isn't one. Most "form not saving" reports are silent validation rejects.
- Blade `@php use ... as Foo;` doesn't work in compiled views — use a closure or fully-qualified call.
- **New (S12)**: when adding a new admin route, prefer route middleware (`->middleware('permission:<perm>')`) over controller-only `$this->authorize()`. The audit test catches both, but route middleware is visible in `route:list` and survives controller refactors.
- **New (S12)**: when adding a new admin UI element (button, link, modal, form), wrap in `@can` so users without the permission don't see it. For forms whose POST is gated, also disable inputs and hide the submit button.
- **New (S12)**: every production deploy must `clear` before `cache` for routes/config/views/events. Stale `route:cache` is a real failure mode that masks middleware additions.

### Constraints (carry forward)

- **Settings section blades are hardcoded** — edit blade AND definitions array when adding a key.
- **JS fetch paths need `appUrl()`** — raw paths break subdirectory deployment.
- **MySQL is required for `php artisan serve`** but not for tests (sqlite). If user reports "app not reachable", check MySQL first.
- **Production HostGator MySQL is 5.7** (NOT 8.x). Migrations must be MySQL-5.7-safe — no JSON DEFAULTs with non-NULL values, etc. See `DEPLOY.md` "Gotchas" section.

### Environment state

- PHP 8.2.12 via XAMPP locally, `c:\xampp\htdocs\Foodbank`.
- **Live production: `https://ngo.heyjaytechnologies.com`** on HostGator shared. PHP 8.2.30 server-side. MySQL 5.7.44.
- Server SSH: `ssh heyjayte@ngo.heyjaytechnologies.com` → `~/ngo.heyjaytechnologies.com/`.
- Git identity (this repo only): `user.name=YTobby`, `user.email=digienergy0@gmail.com`.
- Node 22.x + npm 10.x at `C:\Program Files\nodejs\`. PowerShell execution policy `RemoteSigned` (CurrentUser scope).
- Current `public/build/assets/app-*.css` hash on server: **`app-DXYd_5F0.css`** (S11 rebuild — unchanged in S12; no new Tailwind classes).
- mysqldump path on local Windows: `c:/xampp/mysql/bin/mysqldump.exe`.
- Tests use sqlite `:memory:`. Last full run end of S12: **711 tests passing**.
- Windows scheduled task `FoodBank Schedule Runner` runs `php artisan schedule:run` every minute.
- Production cron: `* * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home3/heyjayte/ngo.heyjaytechnologies.com/artisan schedule:run >> /dev/null 2>&1`

### ADR index

- ADR-001 — AUDIT_REPORT.md Part 13 is the spec
- ADR-002 — UserController is admin-only (with Tier 3b refinement: defense-in-depth on role assignment)
- ADR-003 — checkin_overrides stays separate from audit_logs

### Active branch

`main` — all commits land directly on main in this project. Do not create feature branches.

### Recent commits (Session 12 + last few from S11)

```
07a798b fix(rbac): defense-in-depth route middleware on resource routes + JSON-aware bot defense + docs sync
654de45 docs(rbac): contributor checklist for adding permissions/routes/UI + automated-guardrail reference
8427ad6 feat(security): permanent RBAC guardrails — audit test, smoke test, catalog validation, deploy hardening
e23da52 fix(settings): defense-in-depth on settings.update + hide save UI for non-grantees
622a79d feat(security): bot defense on public registration + review forms
139bcc7 chore(dashboard): tweak stat card labels
89d3f8c fix(rbac): gate sidebar + dashboard widgets per permission
ef5369e docs(remediation): close Session 11 — production deploy + Event Summary + UI polish
f5d92f2 fix(roles): role creation silently fails due to ConvertEmptyStringsToNull
f9b9342 feat(events): event report exports + event summary report
9625b3e feat(ui): topbar dropdowns + public-page branding + review submit fix
367929b docs(deploy): refresh HostGator runbook with real-world gotchas
```
