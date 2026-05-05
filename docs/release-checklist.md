# Release Checklist

A short walk-through to run **before tagging any release** (`git tag phase-X-complete`, `v1.0.0`, etc.). The automated test suite catches a lot, but two known gaps require manual verification:

1. **MySQL-only SQL** isn't covered by the SQLite test suite — see [README §Testing](../README.md#testing) for the why.
2. **Browser-print rendering** for reports isn't testable in PHPUnit (Chart.js renders only in a real browser).

This checklist is intentionally short. If it ever exceeds 30 minutes to run, rebalance: write more automation.

---

## Pre-tag smoke test (~15 minutes)

### 1. Run the full suite on SQLite

```bash
php artisan test
```

Expected: all green. **Do not tag with any failures.**

### 2. Hit every MySQL-only endpoint manually

Connect to a dev MySQL database (NOT prod) populated with realistic data (one current event, one past event, at least 5 households, at least 1 finance transaction, at least 1 volunteer check-in). The simplest way to seed this is:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class="Database\Seeders\KioskTestDataSeeder"
```

Then load each of these URLs and confirm the page renders without a 500 / SQL error:

| Endpoint | Watches for |
|---|---|
| `/reports/overview` | `TIMESTAMPDIFF`, period grouping (`YEARWEEK` / `DATE_FORMAT`) |
| `/reports/trends` | period grouping (`YEARWEEK` / `DATE_FORMAT`) |
| `/reports/lanes` | `TIMESTAMPDIFF` for stage timings |
| `/reports/queue-flow` | `TIMESTAMPDIFF`, `HOUR()` for hour-of-day buckets |
| `/reports/first-timers` | search uses `CONCAT(first_name, ' ', last_name)` — type a partial name in the search box |
| `/reports/download?type=visits` | `TIMESTAMPDIFF`, `CONCAT` |
| `/reports/download?type=households` | `CONCAT` |
| `/reports/download?type=first-timers` | three-subquery first-event lookup |
| `/finance` | `DATE_FORMAT(transaction_date, '%Y-%m')` chart |
| `/checkin?event=…` (any active check-in page) | `CONCAT` in household search |

**Also flip the period filter** on each report page through `Today`, `Last 7 Days`, `Last 30 Days`, `This Month`, `This Year`, `Custom Range` — different periods exercise different `groupingExpressions()` paths (day vs week vs month buckets).

If any page throws a SQL error, the test suite cannot reproduce it. **File a tracking item, fix on a branch, do not tag.**

### 3. Browser-print spot check (~5 min)

Reports use Chart.js (browser-rendered, not dompdf). The `@media print` CSS in `resources/views/reports/_filter.blade.php` hides the sidebar/topbar/buttons during print.

Open Chrome / Edge → load any report → click the **Print** button (right side of the period filter bar) → confirm the print preview shows:

- Org name + period in the printed header
- All KPI cards with their colour fills preserved
- All Chart.js charts rendered (donut, bars, lines)
- No sidebar, no app topbar, no Print/Export buttons

Spot-check at least: `/reports/overview`, `/reports/demographics`, `/reports/inventory`. Other reports follow the same shared partial — if those three look right, the rest are fine.

### 4. Production-config sanity

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Should complete without warnings. If anything errors, your view templates have a problem that would manifest only after `view:cache`.

Then:

```bash
php artisan route:list | grep -E "(reports|finance|inventory|households|volunteers)"
```

Spot-check that critical permission middleware is attached (`permission:reports.view`, `permission:settings.view`, etc.). It's easy to push a route change that drops a middleware by accident.

---

## Tag

If everything above is green:

```bash
git tag -a phase-X-complete -m "Phase X — <one-line summary>"
git push origin phase-X-complete
```

---

## Post-tag (production)

These run on the prod host, not the dev box:

1. `git fetch && git checkout phase-X-complete`
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force` (note: `--force` is required — without it, migrations refuse to run when `APP_ENV=production`)
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache`
5. Tail `storage/logs/laravel.log` for the next 5 minutes — first-time errors after a deploy are how silent breakage shows itself.

If anything in step 5 yells, roll back:

```bash
git checkout phase-(X-1)-complete
php artisan migrate:rollback   # only if step 3 actually applied a migration
php artisan config:cache && php artisan view:cache
```

---

## When this checklist itself needs updating

Add a row to the table above whenever a new MySQL-only function is introduced (most likely in a new analytics / report service). Drop the row when the corresponding query is migrated to portable Eloquent / PHP-side aggregation.
