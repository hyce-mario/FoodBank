# FoodBank

A production-grade Laravel 11 application for managing food bank distribution events: household intake, queue management, volunteer check-in, inventory allocation, finance tracking, and reporting.

## Requirements

- **PHP** 8.2 or newer
- **MySQL** 8.0+ for production (the test suite uses SQLite in-memory)
- **Composer** 2.x
- **PHP extensions**: `pdo_mysql`, `gd` (PDF rendering via dompdf), `zip`, `xml`, `fileinfo`, `mbstring`, `bcmath`, `intl`

## Local development setup

Tailwind is shipped as a frozen prebuilt bundle (in `public/build/`) so npm is not required. If you ever need to rebuild the CSS, install Node 18+ and run `npm install && npm run build`.

```bash
git clone <repo-url> foodbank
cd foodbank
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

The default seeded admin login is `admin@foodbank.local` / `password` for local dev only — do **not** run `AdminUserSeeder` in production without first setting `ADMIN_SEED_PASSWORD` (the seeder will refuse to run otherwise).

## Production deployment

### 1. Environment

Copy [.env.production.example](.env.production.example) to `.env` on the production host and fill in the marked values. Critical settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example.com
LOG_LEVEL=warning
DB_CONNECTION=mysql
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true     # requires TLS on APP_URL
ADMIN_SEED_PASSWORD=...        # required if running AdminUserSeeder
```

Generate the application key once and never rotate without re-encrypting sessions:

```bash
php artisan key:generate
```

### 2. Database + seeders

```bash
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\RoleSeeder"
php artisan db:seed --class="Database\Seeders\SettingsSeeder"
# Optional: create the first admin user. Requires ADMIN_SEED_PASSWORD env.
php artisan db:seed --class="Database\Seeders\AdminUserSeeder"
```

The `RoleSeeder` is required (creates the `ADMIN`, `INTAKE`, `SCANNER`, `LOADER`, `REPORTS`, `VOL_MANAGER` roles + their permission sets). `SettingsSeeder` populates the in-DB settings used by branding, public access, and event-queue policies.

### 3. Storage symlink + cache warm-up

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 4. File permissions

The web user (typically `www-data` on Debian/Ubuntu, `apache` on RHEL) needs write access to:

```bash
chgrp -R www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

### 5. Composer install for production

```bash
composer install --no-dev --optimize-autoloader
```

### 6. Reverse proxy / TLS

If the app sits behind a load balancer or reverse proxy, set `TRUSTED_PROXIES` in `.env` so Laravel honours `X-Forwarded-*` headers. TLS is required for `SESSION_SECURE_COOKIE=true` to work — without HTTPS, session cookies will not be sent and users won't stay logged in.

### 7. Queue worker (optional)

`QUEUE_CONNECTION=database` is set; the schedule commands all run synchronously today, so a queue worker is only needed if you start using queued jobs. When you do, run under systemd or supervisor:

```ini
# /etc/supervisor/conf.d/foodbank-worker.conf
[program:foodbank-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/foodbank/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
stopwaitsecs=3600
```

## Scheduled tasks

Some operational behaviour depends on Laravel's task scheduler running. The most important is `events:sync-statuses`, which transitions events from `upcoming` → `current` on the morning of an event so that day-of role auth codes activate. **If the scheduler is not running, codes will not auto-activate and the event will be stuck in `upcoming` state.**

Defined schedules live in [routes/console.php](routes/console.php). Run `php artisan schedule:list` to inspect them.

### Linux / macOS (cron)

Add this to the deploy user's crontab (`crontab -e`):

```
* * * * * cd /var/www/foodbank && php artisan schedule:run >> /dev/null 2>&1
```

### Windows (Task Scheduler)

Create a task that runs every minute:

1. Open **Task Scheduler** → **Create Task...**
2. **General** tab: Name `FoodBank Schedule Runner`, *Run whether user is logged on or not*, *Run with highest privileges*.
3. **Triggers**: New → *Daily*, *Repeat task every 1 minute* for *Indefinitely*.
4. **Actions**: New → Program/script: `C:\xampp\php\php.exe` (or your PHP path). Arguments: `artisan schedule:run`. Start in: `C:\xampp\htdocs\Foodbank` (or your project root).
5. **Settings**: enable *Run task as soon as possible after a scheduled start is missed* and *If the running task does not end when requested, force it to stop*.

### Manually triggering once

You can also run `php artisan events:sync-statuses` directly to verify behaviour.

### Verifying it's running

Run `php artisan schedule:list` to see all registered tasks and their next-run times. After the scheduler has run at least once, `php artisan schedule:test` lets you pick a task and execute it interactively.

## Testing

```bash
php artisan test
```

The suite uses SQLite in-memory and does not require any production data. **Note:** some MySQL-only SQL in `ReportAnalyticsService` (`TIMESTAMPDIFF`, `DATE_FORMAT`, `YEARWEEK`) is not exercised by the SQLite suite — verify those report endpoints manually against a MySQL dev database before tagging a release.

## Documentation

In-repo reference documentation lives in [docs/](docs/):

- `01-overview.md` — high-level architecture
- `02-schema.md` — database schema
- `03-models.md` — Eloquent models
- `04-controllers.md` — controllers + responsibilities
- `05-services.md` — service layer
- `06-routes.md` — route map
- `07-middleware.md` — middleware
- `08-views.md` — Blade view structure
- `09-seeders.md` — database seeders
- `10-rbac.md` — roles and permissions
- `11-settings-module.md` — runtime settings
- `prompt.md` — context primer for AI-assisted development

Remediation history (the audit-driven phase work that brought this codebase to production grade) is in [docs/remediation/](docs/remediation/). [docs/remediation/AUDIT_REPORT.md](docs/remediation/AUDIT_REPORT.md) Part 13 is the spec; [HANDOFF.md](docs/remediation/HANDOFF.md) is the live state-of-play; [LOG.md](docs/remediation/LOG.md) is the per-phase journal.

## License

This project is released under the MIT license. See [LICENSE](LICENSE) if present, or the standard MIT terms.
