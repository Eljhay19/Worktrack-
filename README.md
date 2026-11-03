# WORKTRACK

WORKTRACK is a Laravel + MySQL application for work calendar entries, rendered hours, cutoff earnings, and scheduled email reminders.

## Stack and local setup

- Frontend: Blade, CSS, JavaScript.
- Backend: Laravel / PHP; database: MySQL or MariaDB.
- Workspace: `C:\xampp\htdocs\WorkTrack`.
- Requirements: PHP, Composer, MySQL, and a modern browser. XAMPP provides the local PHP/MySQL services.

For a new installation:

```powershell
composer install
copy .env.example .env
php artisan key:generate
.\scripts\setup-database.bat
.\scripts\start-worktrack.bat
```

Configure your own database and SMTP values in `.env`. Preserve an existing installation's `APP_KEY`; do not regenerate it during an update or restore. Open `http://127.0.0.1:8000`. With XAMPP Apache, the local URL is `http://localhost/WorkTrack/public/`.

## Profile access across devices

WORKTRACK uses passwordless email access for existing profiles and cross-device access. A brand-new Profile Setup is saved directly without an OTP; a six-digit code is used when the entered email already belongs to an existing profile, or when linking an old profile or changing an email. Codes are stored hashed, expire after 5 minutes, allow 5 verification attempts, and have a 60-second resend cooldown. A resend invalidates the previous code. Passwords, credentials, and OTP values are not returned in API responses or application logs.

1. **New profile:** enter an unused email and profile details; Profile Setup creates the profile and starts a session immediately. There is no password, OTP screen, or user-entered ID for this first setup.
2. **Existing profile on another device:** choose Access Profile, enter the linked email, and verify its code. The session loads that profile's existing calendar, settings, and reminders from the server.
3. **Old browser-only profile:** open the original browser that holds those records and use **Link Email**. Verify the chosen address to attach it to the same profile and retain its records. A reminder address previously verified for sending does not by itself transfer ownership to another browser.
4. **Email change:** verify the replacement address through the same OTP component. An address already linked to another profile is rejected; profiles and records are never automatically merged.
5. **Logout:** ends the current browser's authenticated session. It does not delete the profile, log out other devices, or stop a saved server reminder. A later login requires email verification again.

The unique `access_email` identifies a linked profile; the permanent public profile ID stays with its records. The legacy browser token is retained only for safely linking an existing local profile, not as cross-device login. Do not clear the original browser's storage before linking an old profile.

Cross-device access requires both devices to reach the **same hosted application and central database**. Each computer's `127.0.0.1` points to that computer. Hosting, domain configuration, and deployment have not been performed by these code changes; see [Deployment](docs/DEPLOYMENT.md).

## Features

- Monthly calendar; time in/out; regular and extended hours; earnings and cutoff totals.
- Rest day and excused absence labels, attendance notes, saved-entry edit/delete.
- Daily Records list linked to the date-entry editor.
- Profile settings and passwordless email access across devices.
- Personal work-hours and estimated-earnings summaries in print-to-PDF, Excel, and image formats; these are not payroll documents.
- Reset of the currently owned profile's records through the confirmation flow.
- Email reminders, delivery status history, and encrypted database backups.
- Named section URLs restore the requested section on refresh. Opening a URL does not grant access to its data.

Saved entries retain the hourly rate used when they were last saved. Changing the profile rate affects new entries and entries deliberately edited and saved again, not historical totals. Exports use the visible calendar month and, when selected for that month, the Daily Records cutoff or full-month filter.

## Daily Reminder flow

Open **Settings → Daily Reminder**. Enter the recipient address and verify its code in the separate OTP popup before saving the schedule. Changing the recipient requires verification again. The backend checks the session's profile ownership and verified recipient.

Choose a Philippine time, reminder notes, and a schedule: daily, selected weekdays, or a one-time custom date. The notes are the primary email content. Work logs and rest days do not suppress these general reminders.

Saving creates a **Scheduled** occurrence. Notifications show its notes, schedule, next Philippine date/time, and the latest 20 **Sent/Failed** occurrences. The visible page checks updates every 5 seconds, including on opening or resuming the page; manual refresh and native browser notification permission are not required.

The server checks due occurrences every second in `Asia/Manila`. Atomic claims and a unique profile/scheduled-time key prevent duplicate attempts for the same occurrence. After a recurring send, its history is retained and the next selected date is scheduled. **Sent** means the email transport accepted the message, not that it reached the inbox or was read. **Failed** is retained when acceptance cannot be confirmed. Failures are not automatically retried because an ambiguous timeout might already have delivered the email. Prior-date schedules missed while the sender was stopped become Failed; interrupted attempts become Failed after 10 minutes.

Reminder links open the intended section, but still require the matching profile's authenticated session or OTP login. An email link alone does not grant profile access. The signed turn-off link displays confirmation before disabling email reminders.

## Automatic local sending

Install or update the local Windows task:

```powershell
.\scripts\install-scheduler.ps1 -StartNow
```

`WORKTRACK Scheduler` keeps `php artisan schedule:work` running in a hidden process with second-level dispatch. Its minute trigger ignores additional launches while the worker is running. It uses the current Windows account with limited privileges and the configured `.env`, and also runs encrypted backups. If Windows denies task registration, use an administrator PowerShell window.

Keep this PC awake, the Windows account signed in, MySQL running, and SMTP/network connectivity available. The browser and Laravel development server can be closed. A stopped or sleeping host cannot send on time; use an always-on host for continuous delivery. Server load and email transport can introduce delay after the scheduled trigger.

```powershell
Get-ScheduledTask -TaskName 'WORKTRACK Scheduler'
Get-ScheduledTaskInfo -TaskName 'WORKTRACK Scheduler'
php artisan schedule:list
```

A running task normally has result `267009`; `0` means a process exited successfully. Occurrence acceptance and failure are tracked separately in `work_reminder_deliveries`.

## Encrypted backups and recovery

```powershell
php artisan worktrack:backup
php artisan worktrack:restore worktrack-YYYYMMDD-HHMMSS-1234abcd.sql.enc
```

The scheduler backs up at 02:15 Philippine time and retains 14 snapshots by default. Files are encrypted using `APP_KEY` and stored in `storage/app/private/worktrack-backups`, outside the public directory. Keep a separate protected copy of the key and copy encrypted backups off the server. Without the matching key, the backup cannot be decrypted.

Set `WORKTRACK_BACKUP_RETENTION`, `WORKTRACK_MYSQLDUMP_BINARY`, and `WORKTRACK_MYSQL_BINARY` for the host. Restore replaces the full configured database and asks for its name; use `--force` only in a controlled recovery script. The snapshot contains a database-name-specific full replacement dump: recovery testing must use an isolated database server with the expected database name, or a carefully reviewed offline import. See the recovery plan in [Deployment](docs/DEPLOYMENT.md).

## Code map

| Area | Files |
| --- | --- |
| Web sections and API | `routes/web.php`, `routes/api.php` |
| Controllers and ownership | `app/Http/Controllers/` |
| Profile and calendar records | `app/Models/WorkProfile.php`, `app/Models/WorkEntry.php` |
| Reminder scheduling and sending | `app/Services/ReminderService.php`, `routes/console.php` |
| Views and email templates | `resources/views/worktrack/`, `resources/views/emails/` |
| UI behavior and theme | `public/assets/js/worktrack.js`, `public/assets/css/worktrack.css` |
| Migrations | `database/migrations/` |
| Local helper scripts | `scripts/` |
| API contract and hosting plan | [API](docs/API.md), [Deployment](docs/DEPLOYMENT.md) |

No admin dashboard, payroll-system integration, or tax calculation is included. Export files are generated from the signed-in profile's loaded records.
