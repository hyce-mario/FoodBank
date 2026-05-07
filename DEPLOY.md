# Deploying FoodBank to HostGator (`ngo.heyjaytechnologies.com`)

A step-by-step runbook for moving this Laravel 11 app from local XAMPP to HostGator
shared hosting. Every step has a "What this does" paragraph — read it before
running the commands so you understand *why*, and can adapt if HostGator's UI
has shifted by the time you read this.

This runbook reflects the **actual deploy of this app on 2026-05-06**, including
the things that broke and how we fixed them. Don't skim the "Gotchas" — they're
the difference between a 30-minute deploy and a 4-hour deploy.

---

## Gotchas you should know up front

These bit us during the first deploy. Knowing them saves time.

1. **HostGator subdomains live at `~/<subdomain>/`, NOT under `public_html/`.**
   Older HostGator docs (and most blog posts) assume subdomain folders are nested
   inside `public_html/`. On modern HostGator accounts they're created at the
   home directory level — e.g. `/home3/heyjayte/ngo.heyjaytechnologies.com/`.
   Plan your paths accordingly.

2. **The cPanel doc-root save is silently fragile.** When you change a subdomain's
   document root in cPanel, sometimes the change doesn't persist after save.
   Symptom: you set it to `.../public`, save, and Apache keeps serving from the
   project root. Always **re-open the Subdomains panel after saving** to confirm
   the path actually has `/public` at the end.

3. **HostGator MySQL is 5.7, not 8.x.** This rejects `DEFAULT '...'` clauses on
   `JSON`/`TEXT`/`BLOB`/`GEOMETRY` columns. MariaDB-style dumps from XAMPP fail
   at import. We handle this with a Python regex (Step 6).

4. **`composer install` runs `package:discover` automatically, which boots Laravel,
   which (in this app) queries `app_settings` during boot.** If the DB isn't
   wired up yet, that boot fails and the install errors out. Fix: install with
   `--no-scripts` first, then run `package:discover` manually after the DB is
   populated.

5. **Adding a MySQL user to a database is a separate cPanel step from creating
   the user.** Easy to miss. Symptom: "Access denied for user 'X'@'localhost' to
   database 'X'" even though both exist. Fix: cPanel → MySQL Databases → "Add
   User To Database" panel → tick ALL PRIVILEGES.

6. **A bash password with `(` or `)` will be subshell-interpreted** if written
   without quoting. Always single-quote it on the command line (`-p'pass(word)'`)
   and use `<<'EOF'` (single-quoted heredoc) when writing it to `.env`.

---

## 0. Before you start — checklist

- HostGator shared hosting account with cPanel access.
- Parent domain `heyjaytechnologies.com` exists in cPanel.
- Local XAMPP works: `http://localhost/Foodbank/public` shows the app, and the
  `foodbank` database is reachable in `http://localhost/phpmyadmin`.
- Project is pushed to GitHub at `https://github.com/hyce-mario/FoodBank.git`
  (or wherever) — much faster than zip uploads.

**SSH access is essentially required.** Without it, every step in 5–10 becomes
significantly harder. cPanel → Security → **SSH Access**. If it's not visible
on your account, open HostGator support chat: *"Please enable SSH access for my
account."* They'll do it within minutes.

---

## 1. Create the subdomain in cPanel

**What this does:** A subdomain on HostGator is just a folder with its own
document root. We point `ngo.heyjaytechnologies.com` at the Laravel `public/`
folder so visitors hit Laravel's front controller, never the project root.

1. cPanel → **Domains → Subdomains** (older skin) or **Domains → Domains →
   Create A New Domain** (newer skin).
2. Subdomain: `ngo`
3. Domain: `heyjaytechnologies.com`
4. Document Root: `home3/<your-cpanel-user>/ngo.heyjaytechnologies.com/public`
   ← **must end in `/public`**. Replace `<your-cpanel-user>` with your actual
   cPanel username (e.g., `heyjayte`).
5. Click **Create**.

**Important — verify the doc root actually saved.** Re-open the Subdomains panel
and look at the Document Root column for the row you just created. If it's
*just* `home3/.../ngo.heyjaytechnologies.com` without `/public`, click the
**edit pencil** next to that path, append `/public`, save, and verify *again*.
This is gotcha #2.

**Verify (browser):** `http://ngo.heyjaytechnologies.com/` — you'll get 403 or
404 for now. That's expected; the folder doesn't have an index file yet.

---

## 2. Set the PHP version

**What this does:** Laravel 11 requires PHP ≥ 8.2. cPanel's MultiPHP Manager
pins the version per domain.

1. cPanel → **Software → MultiPHP Manager**.
2. Tick `ngo.heyjaytechnologies.com`.
3. Dropdown → **PHP 8.2** or **8.3**.
4. Apply.

**Verify (after step 5 below):** `php -v` in SSH should print `PHP 8.2.x` or
`8.3.x`.

---

## 3. Create the production MySQL database

**What this does:** Provisions the empty database and an app-only user, and
attaches the user to the database with ALL PRIVILEGES.

1. cPanel → **Databases → MySQL Databases**.
2. **Create New Database:** name `foodbank` (or `ngo`). Real name becomes
   `<cpaneluser>_foodbank` (e.g., `heyjayte_ngo`).
3. **Create New User:** username `foodbank` (or `ngo`). Use the password generator,
   click **Use Password**. **Save the password immediately** — cPanel won't
   show it again.
4. **Add User To Database** *(separate panel, scroll down)*: select the user
   and database from the dropdowns → click **Add** → tick **ALL PRIVILEGES**
   on the next screen → **Make Changes**.

This third step is gotcha #5. If you forget it, the app will get "Access denied"
errors even though the credentials are correct.

**Final values (write these down):**
```
DB_DATABASE = <cpaneluser>_<dbname>     # e.g. heyjayte_ngo
DB_USERNAME = <cpaneluser>_<username>   # e.g. heyjayte_ngo
DB_PASSWORD = <generated password>
DB_HOST     = 127.0.0.1
DB_PORT     = 3306
```

---

## 4. Export your local database (XAMPP)

**What this does:** Snapshots your XAMPP `foodbank` database as a `.sql` file.

1. `http://localhost/phpmyadmin` → click `foodbank` in the sidebar.
2. **Export** tab → Method: Quick → Format: SQL → **Go**.
3. Save the downloaded `foodbank.sql` to a known location on your laptop.

**Sanity check:** open in a text editor. The first ~20 lines should be SQL
comments and `CREATE TABLE` statements. If it's a few KB, the export failed
silently — try **Custom** with "Add DROP TABLE / VIEW" + "Add IF NOT EXISTS"
ticked.

---

## 5. SSH in, clone the repo, install Composer dependencies

**What this does:** Pulls the Laravel project from GitHub into your subdomain
folder, then installs the ~80 PHP libraries it depends on. Note the
`--no-scripts` flag — that's gotcha #4.

```bash
# 1. SSH in
ssh <cpaneluser>@ngo.heyjaytechnologies.com

# 2. Diagnostics — confirm the environment
whoami                                         # your cpanel user
pwd                                            # ~/ngo.heyjaytechnologies.com
which php; php -v                              # 8.2.x or 8.3.x
which composer; composer --version             # 2.x
which mysql; which git

# 3. Move to the subdomain folder
cd ~/ngo.heyjaytechnologies.com

# 4. The folder may have a stub .htaccess or empty public/ from cPanel —
#    git clone . needs an empty directory, so clean first.
ls -la
rm -rf .htaccess public  # (only if those are the cPanel-created stubs)

# 5. Clone (note the trailing dot — clones into current folder, not a subfolder)
git clone https://github.com/hyce-mario/FoodBank.git .

# 6. Install Composer dependencies — WITH --no-scripts (gotcha #4)
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 7. Quick sanity check
ls vendor/ | head
ls vendor/laravel/framework | head
```

If `composer install` fails with a memory error, retry with `php -d memory_limit=-1
$(which composer) install --no-dev --optimize-autoloader --no-interaction --no-scripts`.

**Why `--no-scripts`:** Without it, Composer runs `php artisan package:discover`
after autoload generation. That command boots Laravel; the `AppServiceProvider`
queries the `app_settings` table during boot; the DB isn't ready yet → boot
fails → composer errors out. We'll run `package:discover` manually in Step 8.

---

## 6. Configure `.env` and generate APP_KEY

**What this does:** Writes a complete production `.env` file with real DB
credentials, generates a fresh encryption key, and confirms Laravel can connect
to MySQL.

```bash
cd ~/ngo.heyjaytechnologies.com

# 1. Write the .env file. Single-quoted heredoc terminator ('ENVEOF') is
#    intentional: it prevents bash from interpreting any $, (, ), etc. in
#    your password (gotcha #6).
cat > .env << 'ENVEOF'
APP_NAME="Food Bank"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=America/New_York
APP_URL=http://ngo.heyjaytechnologies.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=daily
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=heyjayte_ngo
DB_USERNAME=heyjayte_ngo
DB_PASSWORD=<<<PASTE_PASSWORD_HERE_FROM_STEP_3>>>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
CACHE_PREFIX=foodbank_

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=mail.heyjaytechnologies.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@heyjaytechnologies.com"
MAIL_FROM_NAME="${APP_NAME}"
ENVEOF

# 2. Edit the placeholder password line — open in nano and paste real password
nano .env   # or vim .env

# 3. Lock down the file permissions
chmod 600 .env

# 4. Generate APP_KEY (writes into .env automatically)
php artisan key:generate
grep '^APP_KEY=' .env   # should now show base64:...

# 5. Test DB connection
php artisan db:show
```

**Notes:**
- `APP_URL` and `SESSION_SECURE_COOKIE=false` are HTTP for now. We flip both
  to HTTPS in Step 11 once AutoSSL is verified.
- If `db:show` errors with **"Access denied for user X to database X"** →
  gotcha #5 — go back to cPanel and add the user to the database with ALL
  PRIVILEGES.
- If it errors with **"Connection refused"** → try `DB_HOST=localhost` instead
  of `127.0.0.1`.

---

## 7. Import your local database (with the MySQL 5.7 fix)

**What this does:** Loads your local data into the empty production database.
HostGator's MySQL 5.7 rejects MariaDB-style JSON defaults; we strip them with
Python before import (gotcha #3).

### 7.1 — Transfer the dump to the server

In **another** PowerShell window on your laptop (so SSH stays open):

```powershell
scp c:\xampp\htdocs\Foodbank\foodbank.sql <cpaneluser>@ngo.heyjaytechnologies.com:~/foodbank.sql
```

Verify on the server:
```bash
ls -lh ~/foodbank.sql
head -5 ~/foodbank.sql
```

### 7.2 — Strip JSON/TEXT/BLOB DEFAULT clauses

The MariaDB dump uses syntax like:
```sql
`rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`rules`)),
```

MySQL 5.7 rejects the `DEFAULT '[]'` because it's a non-NULL default on a
TEXT-family column. We strip those defaults — the application doesn't depend
on them (Laravel sets values from PHP code).

```bash
python3 << 'PYEOF'
import re, shutil, os

src = '/home3/<cpaneluser>/foodbank.sql'  # ← edit this path

if os.path.exists(src + '.bak'):
    shutil.copy(src + '.bak', src)
else:
    shutil.copy(src, src + '.bak')

with open(src, 'r', encoding='utf-8') as f:
    content = f.read()

pattern = re.compile(
    r"""(
        `\w+`\s+
        (?:json|text|blob|geometry|tinytext|mediumtext|longtext|tinyblob|mediumblob|longblob)
        (?:\s+CHARACTER\s+SET\s+\w+)?
        (?:\s+COLLATE\s+\w+)?
        (?:\s+(?:NOT\s+)?NULL)?
    )
    \s+DEFAULT\s+
    (?!NULL\b)
    (?:_\w+\s*)?
    '(?:[^'\\]|\\.|'')*'
    """,
    re.IGNORECASE | re.VERBOSE,
)

count = len(pattern.findall(content))
new_content = pattern.sub(r'\1', content)

with open(src, 'w', encoding='utf-8') as f:
    f.write(new_content)

print(f"Stripped {count} non-NULL DEFAULT clause(s)")
PYEOF
```

You should see `Stripped N ...` where N ≥ 1. The script also writes a backup
to `foodbank.sql.bak` so you can re-run if the regex misses something.

### 7.3 — Import

```bash
mysql -u <db_user> -p'<db_password>' <db_name> < ~/foodbank.sql
```

A clean import returns to the prompt silently. Verify:

```bash
mysql -u <db_user> -p'<db_password>' <db_name> -e "
SHOW TABLES;
SELECT COUNT(*) AS users FROM users;
SELECT COUNT(*) AS events FROM events;
SELECT COUNT(*) AS households FROM households;
"
```

If you get a different error than the JSON one (different table, different
column, or a collation issue like `utf8mb4_uca1400_ai_ci`), paste the error
and the surrounding lines (`sed -n 'LINE-5,LINE+5p' ~/foodbank.sql`) and
extend the Python regex to handle that case.

### 7.4 — Clean up

The dump contains every row of data including hashed passwords. Don't leave
it on the server.

```bash
mkdir -p ~/_backups
mv ~/foodbank.sql ~/_backups/foodbank-$(date +%Y%m%d).sql
chmod 600 ~/_backups/*.sql
```

---

## 8. Run the post-import artisan commands

**What this does:** Now that the DB has tables, every artisan command can boot
cleanly. We run the `package:discover` that we deferred in Step 5, plus the
caches and storage symlink.

```bash
cd ~/ngo.heyjaytechnologies.com

php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link

# Sanity check
php artisan about
```

The `about` table should show: Environment=production, Debug Mode=OFF,
Database driver=mysql, Database name=`heyjayte_ngo` (or yours), Cache=database.

---

## 9. Set file permissions

**What this does:** Laravel writes session/log/cache/view files at runtime to
`storage/` and `bootstrap/cache/`. On HostGator's shared hosting, your cPanel
user owns both PHP-FPM and the files, so 755/644 is enough.

```bash
cd ~/ngo.heyjaytechnologies.com
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 755 artisan
chmod 600 .env
chmod -R 755 storage bootstrap/cache
```

If you ever see `403 Forbidden` from Apache, run that whole block — HostGator's
suEXEC requires precisely 755/644 on PHP files.

---

## 10. Upload the `public/build/` Vite assets

**What this does:** The compiled CSS and JavaScript live in `public/build/`,
which is **gitignored** in this repo (so `git clone` didn't bring it). We
upload it from your laptop. Node/npm aren't available on HostGator shared
hosting, so we can't rebuild server-side.

In a PowerShell window on your laptop:

```powershell
scp -r c:\xampp\htdocs\Foodbank\public\build <cpaneluser>@ngo.heyjaytechnologies.com:~/ngo.heyjaytechnologies.com/public/
```

Verify on the server:
```bash
ls -la ~/ngo.heyjaytechnologies.com/public/build/
ls ~/ngo.heyjaytechnologies.com/public/build/assets/
cat ~/ngo.heyjaytechnologies.com/public/build/manifest.json | head -20
```

You should see `manifest.json` + an `assets/` folder with one `.css` and one
`.js` file. If `public/build/manifest.json` is missing, every page request
will throw "Vite manifest not found".

---

## 11. Enable HTTPS, switch APP_URL

**What this does:** HostGator's AutoSSL provisions a Let's Encrypt cert
automatically when you create a subdomain. Most of the time it's already done
by the time you finish the steps above. We confirm it works, then flip `.env`
to `https://` + secure cookies, then force HTTP→HTTPS at the `.htaccess` level.

```bash
# 1. In a browser, visit https://ngo.heyjaytechnologies.com/
#    - Padlock icon, no warning → AutoSSL is active. Continue.
#    - "Connection not secure" → go to cPanel → Security → SSL/TLS Status →
#      click "Run AutoSSL", wait 5–10 minutes, retry.

# 2. Switch .env to HTTPS
cd ~/ngo.heyjaytechnologies.com
sed -i 's|^APP_URL=.*|APP_URL=https://ngo.heyjaytechnologies.com|' .env
sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' .env
php artisan config:cache

# 3. Force HTTP → HTTPS via .htaccess
sed -i '/RewriteEngine On/a\
\
    # Force HTTPS\
    RewriteCond %{HTTPS} off\
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]' public/.htaccess

# 4. Verify the rules landed
sed -n '1,20p' public/.htaccess
```

Test from a fresh browser tab: `http://ngo.heyjaytechnologies.com/` should
redirect to `https://...`. Log in to confirm sessions still work over HTTPS.
You'll be logged out by the cookie security change — that's expected and
one-time.

---

## 12. Set up the cron job

**What this does:** Laravel's scheduler defines three recurring tasks in
`routes/console.php` (event status sync, inventory reconcile, volunteer auto-
checkout). We give HostGator's cron one minute-rate trigger that fires
`schedule:run`, and Laravel decides which (if any) of the three to actually
run that minute.

1. cPanel → **Advanced → Cron Jobs**.
2. Common Settings: **Once per minute (`* * * * *`)**.
3. Command (one line):

```
/opt/cpanel/ea-php82/root/usr/bin/php /home3/<cpaneluser>/ngo.heyjaytechnologies.com/artisan schedule:run >> /dev/null 2>&1
```

Replace `<cpaneluser>` with your username (e.g., `heyjayte`). If you set PHP
8.3 in Step 2, change `ea-php82` to `ea-php83`.

4. **Add New Cron Job**.

**Verify:**
```bash
php artisan schedule:list
# Should print 3 commands: events:sync-statuses, inventory:reconcile-nightly,
# volunteers:auto-checkout
```

---

## 13. Transfer existing media (if any)

**What this does:** Event photos and other uploads live in `public/event-media/`
and `storage/app/public/`. Both folders are gitignored, so anything you uploaded
locally hasn't reached the server.

On your laptop, check what you have:
```powershell
$em = Get-ChildItem c:\xampp\htdocs\Foodbank\public\event-media -Recurse -File -ErrorAction SilentlyContinue
"  public/event-media:  $($em.Count) files / $(($em | Measure-Object -Sum Length).Sum) bytes"

$sp = Get-ChildItem c:\xampp\htdocs\Foodbank\storage\app\public -Recurse -File -ErrorAction SilentlyContinue
"  storage/app/public: $($sp.Count) files / $(($sp | Measure-Object -Sum Length).Sum) bytes"
```

If counts are non-zero, transfer:
```powershell
scp -r c:\xampp\htdocs\Foodbank\public\event-media <cpaneluser>@ngo.heyjaytechnologies.com:~/ngo.heyjaytechnologies.com/public/
scp -r c:\xampp\htdocs\Foodbank\storage\app\public\* <cpaneluser>@ngo.heyjaytechnologies.com:~/ngo.heyjaytechnologies.com/storage/app/public/
```

On the server:
```bash
chmod -R 755 ~/ngo.heyjaytechnologies.com/storage/app/public
chmod -R 755 ~/ngo.heyjaytechnologies.com/public/event-media
```

**Verify a media URL** by opening one of the storage filenames in a browser:
```
https://ngo.heyjaytechnologies.com/storage/<filename>
```
Should display/download the file. That confirms the
`public/storage` → `storage/app/public` symlink is functioning through Apache.

---

## 14. Smoke test

Walk one full event-day flow on the live HTTPS site:

- [ ] Log in as admin
- [ ] Create a test event
- [ ] Upload an event photo → verify it renders on the event page
- [ ] Register a household for the event
- [ ] Open `/{role}` URL on a phone (or DevTools mobile mode), enter the role
      code, run a check-in
- [ ] Allocate inventory at a station, complete one distribution
- [ ] Logout → confirm you land back on the role page (the bookmark flow)
- [ ] Reports → Finance → confirm a financial report renders
- [ ] Wait until tomorrow morning (00:01 UTC + your timezone offset), then
      check `storage/logs/` to confirm the cron's `events:sync-statuses` fired

---

## Updating production (routine deploys)

The shape of a routine deploy after the initial one works:

```bash
ssh <cpaneluser>@ngo.heyjaytechnologies.com
cd ~/ngo.heyjaytechnologies.com

# Pull latest code
git pull

# Reinstall dependencies if composer.lock changed
composer install --no-dev --optimize-autoloader --no-interaction

# Run any new migrations
php artisan migrate --force

# Clear-then-rebuild caches. The clear step is load-bearing: a previous
# `route:cache` run may have written a stale snapshot to disk that Laravel
# keeps using if the rebuild fails silently. Symptom: new permission
# middleware (or any new middleware) added to routes/web.php silently
# doesn't fire on production. The clear forces Laravel to rebuild from
# current source.
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan event:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# If frontend assets changed, transfer the new public/build/ from local
# (in another window on your laptop):
#   scp -r c:\xampp\htdocs\Foodbank\public\build <user>@ngo...:~/ngo.../public/
```

If anything goes wrong, check `storage/logs/laravel-$(date +%Y-%m-%d).log` first.

---

## Troubleshooting

**500 error / "Whoops":** check `storage/logs/laravel-*.log`. Most common
causes: wrong DB password, missing `APP_KEY`, `storage/` not writable,
stale config cache (`php artisan config:clear && php artisan config:cache`).
For deeper debugging temporarily set `APP_DEBUG=true` in `.env` then
`config:cache`, but **always turn it back off** — it leaks stack traces and
config to anyone who hits an error.

**403 Forbidden on every page:** doc root isn't pointing at `public/`
(gotcha #2). Re-check cPanel → Subdomains → Document Root for the row, ensure
the path ends in `/public`, save again. If it does end in `/public`, run the
permissions reset from Step 9.

**404 on every page:** the doc root is set somewhere else entirely. Write a
marker file at the suspected doc root path (`echo test > ~/.../path/test.txt`)
and visit the URL — wherever it serves from is the actual doc root.

**"Access denied for user 'X'@'localhost' to database 'X'":** gotcha #5 — the
user wasn't attached to the database. Go to cPanel → MySQL Databases → "Add
User To Database" panel.

**"BLOB, TEXT, GEOMETRY or JSON column 'X' can't have a default value" during
import:** the Python regex in 7.2 missed a column. Inspect `sed -n 'LINE-5,LINE+5p'
~/foodbank.sql` to see the exact syntax, extend the regex.

**Login works but immediately logs out:** session cookie issue. If `APP_URL`
is `https://` then `SESSION_SECURE_COOKIE=true`; if `http://` then it must be
`false`. Verify `SESSION_DOMAIN=null` (no leading dot, no domain).

**Media images 404:** the `storage:link` symlink is missing or doesn't survive
a deploy. `ls -la public/storage` should show it pointing at
`../storage/app/public`. Re-run `php artisan storage:link` if missing.

**Cron not firing:** `php artisan schedule:list` should print three commands.
If it does but they're not running, check the PHP path in your cron command —
SSH and run `which php` to find the right binary. HostGator typically wants
`/opt/cpanel/ea-php82/root/usr/bin/php`, not `/usr/local/bin/php`.

**"Vite manifest not found":** `public/build/` didn't get uploaded (Step 10).
It's gitignored, so `git pull` will never bring it. Always re-scp it on deploys
where the frontend changed.

---

## Backups

Set up before you have your first incident, not after.

**Database backup (manual, anytime via SSH):**
```bash
mkdir -p ~/_backups
mysqldump -u <db_user> -p'<db_password>' <db_name> --single-transaction --routines --triggers \
  | gzip > ~/_backups/db-$(date +%Y%m%d-%H%M).sql.gz
chmod 600 ~/_backups/db-*.sql.gz
```

**Automated nightly DB backup** — add this as a second cron job (cPanel → Cron
Jobs), schedule `0 3 * * *` (3 AM nightly):
```
mysqldump -u <db_user> -p'<db_password>' <db_name> --single-transaction --routines --triggers | gzip > /home3/<cpaneluser>/_backups/db-$(date +\%Y\%m\%d).sql.gz && find /home3/<cpaneluser>/_backups -name 'db-*.sql.gz' -mtime +30 -delete
```

(Note: `%` characters need escaping in cron commands as `\%`.)

**Media backup:** `~/ngo.heyjaytechnologies.com/storage/app/public/` and
`~/ngo.heyjaytechnologies.com/public/event-media/`. Tar them weekly to `_backups/`
or sync down to your laptop.
