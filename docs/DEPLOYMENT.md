# Deployment and recovery plan

This is a deployment guide for the existing application. A public domain, hosting account, TLS certificate, and production credentials still need to be supplied and configured. The local Windows installation is not an online deployment.

## 1. Prepare one central application

Use an always-on PHP/Laravel host and a central MySQL/MariaDB database reachable by that host. Both desktop and mobile clients must use the same HTTPS domain. Device-local `localhost` URLs and separate databases cannot provide cross-device login or shared records.

Serve only the project's `public/` directory. Keep `.env`, database dumps, private storage, source files, and keys outside the web root. Grant the web/worker account access only to required files and database permissions; do not use the XAMPP root database account in production.

## 2. Configure production secrets and sessions

Configure secrets through the host's protected environment or a nonpublic `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
WORKTRACK_REMINDER_TIMEZONE=Asia/Manila
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Set real `DB_*` and `MAIL_*` values without committing or sharing passwords. The `sessions` table must exist through migrations. Database/shared session storage must be persistent across restarts and shared across application instances. Configure trusted proxy handling correctly if TLS terminates at a reverse proxy, so secure requests/cookies and signed links use the public HTTPS origin.

Use the same persistent `APP_KEY` for all app instances and workers. When migrating the local data, preserve its key: encrypted backups and legacy token HMAC ownership depend on it. Generate a key only for a genuinely fresh installation. Keep a protected copy separately from encrypted backups; routine deploys must not rotate it.

## 3. Install and migrate

From the deployment directory, with the production environment configured:

```sh
composer install --no-dev --optimize-autoloader
php artisan down
php artisan worktrack:backup
php artisan migrate --force
php artisan optimize
php artisan up
```

Before migrating an existing database, take a usable encrypted snapshot and retain the matching `APP_KEY`. On a fresh empty installation, the backup step can be omitted. Give `storage/` and `bootstrap/cache/` appropriate writable permissions. Keep private storage persistent between releases.

The hourly-rate snapshot migration freezes existing entries at each profile's rate at migration time. Older rates were not previously stored, so earlier historical rates cannot be reconstructed. Verify a representative month and cutoff against the pre-migration backup before releasing updated totals.

Bring original browser-only records into linked access through **Link Email in the original browser**. Do not silently merge profiles when the email is already linked. New devices use the email OTP login, then load that same profile from the central database.

## 4. Keep scheduling alive

Run Laravel's persistent worker under the host's service manager or process supervisor:

```sh
php /absolute/project/path/artisan schedule:work
```

Alternatively, configure the host's scheduler to invoke `schedule:run` every minute. The reminder's subminute schedule stays active through the current minute; the host must permit that runtime and prevent competing launches from being killed prematurely. A supervised persistent worker is preferable when the host supports it.

```sh
php /absolute/project/path/artisan schedule:list
php /absolute/project/path/artisan schedule:interrupt
```

After deploying new code, restart the supervised worker. `schedule:interrupt` stops an in-progress subminute runner so the next runner uses new code. Use one operational scheduling strategy per environment. Database occurrence claims still protect against duplicate competing attempts.

The worker dispatches against Philippine scheduled times, independently of the browser. Keep system clocks synchronized. An unavailable/sleeping host or broken database/SMTP connection cannot meet the trigger time; monitor service health and delivery failures. Sent means SMTP acceptance, not Gmail arrival or a read receipt.

For local Windows, use `scripts/install-scheduler.ps1 -StartNow` instead of installing a Linux cron/service. Its task uses the signed-in user's account; the PC must remain awake and connected.

## 5. Backups

The scheduler runs an encrypted backup at 02:15 Philippine time. Default retention is 14 snapshots in `storage/app/private/worktrack-backups`. Configure host-appropriate paths for `WORKTRACK_MYSQLDUMP_BINARY` and `WORKTRACK_MYSQL_BINARY`, plus `WORKTRACK_BACKUP_RETENTION` if needed.

```sh
php artisan worktrack:backup
```

Copy encrypted snapshots to protected off-server storage. Protect their contents and the separate `APP_KEY`: backups contain personal data, work records, account links, and reminder history. A database backup alone does not preserve application code, `.env`, or other host configuration; protect those separately. Verify backup age and rehearse recovery before relying on the plan.

## 6. Recovery rehearsal and production restore

1. Provision an isolated database server for the rehearsal. The generated dump includes its original database name and full replacement statements. Merely changing `DB_DATABASE` is not enough to redirect that dump safely; the restore command deliberately validates the configured database name.
2. Copy the application version, encrypted snapshot, and matching `APP_KEY` into that isolated environment. Disable SMTP/scheduling there to prevent test reminders from reaching real users.
3. Configure the expected database name and private backup path, then restore:

   ```sh
   php artisan worktrack:restore worktrack-YYYYMMDD-HHMMSS-1234abcd.sql.enc
   ```

4. Type the configured database name at the confirmation prompt. The operation replaces that entire database. Use `--force` only in a reviewed, controlled recovery script.
5. Check profile ownership, linked email login with a controlled test recipient, entries, reminder schedules/history, and expected schema. Record the tested snapshot/date and recovery result.
6. For an actual production restore, take the site down, stop its scheduler first, save a current snapshot if possible, confirm the intended host/database, then repeat the restore with the matching key. Resume the app/worker after reviewing which occurrences are still due. Historical Sent/Failed rows prevent automatic retries of ambiguous deliveries.

## 7. Release checks

Confirm HTTPS/session/CSRF behavior, email registration/login on two devices, original-browser Link Email, logout, rejection of conflicting linked addresses, reminder Scheduled→Sent/Failed history, recurring next occurrence, and successful backup/recovery rehearsal. Keep failure alerts and logs free of SMTP passwords and OTP values. These operational checks must run against the actual hosting environment before calling the site live.
