# WORKTRACK API

Local base URL: `http://127.0.0.1:8000/api/worktrack`.

## Sessions, ownership, and requests

The web app uses an HttpOnly cookie session. Send same-origin cookies with API requests and the current CSRF token with mutating requests, using the `X-CSRF-TOKEN` header. Fetch `/access/status` to establish or inspect the session and obtain its token. Use the new token returned after verification because session authentication rotates the session. HTTPS with secure cookies is required in production.

Authenticated profile ownership is resolved on the server. A profile ID, email address, or URL parameter does not authorize access. `browser_id` is a legacy migration identifier for an existing unlinked browser profile; it is HMAC-hashed at rest and is not a substitute for cross-device login. Legacy clients may include it while migrating; authenticated requests use the session's owned profile.

## Passwordless access

### Session status

```http
GET /api/worktrack/access/status
```

Returns `authenticated`, pending challenge metadata when applicable, and `csrf_token`. Pending metadata contains no OTP value.

### Send a verification code

```http
POST /api/worktrack/access/send
Content-Type: application/json
X-CSRF-TOKEN: {currentToken}
```

Registration example:

```json
{
  "purpose": "register",
  "email": "user@example.com",
  "profile": {
    "nickname": "LJ",
    "work_type": "Crew",
    "employee_status": "part_time",
    "hourly_rate": 94.53,
    "default_regular_hours": 6
  }
}
```

| Purpose | Use |
| --- | --- |
| `register` | Create a new profile from the `profile` object when the email is unused. If the email already belongs to an enabled profile, the server changes this request to `login` and requires OTP instead. |
| `login` | Verify access to an existing linked profile on this or another device. |
| `link` | From the original browser, attach an email to the existing unlinked profile; include its legacy `browser_id`. |
| `email_change` | From an authenticated session, verify the replacement access email. |

`profile` keys are `nickname`, `work_type`, `employee_status`, `hourly_rate`, and optional `default_regular_hours`. The server generates the permanent profile identity; the setup form does not ask for a username or password. The `register` purpose is an internal API name for first-time Profile Setup, not a separate conventional sign-up page. Legacy clients may still send an optional `user_id`, but it does not establish ownership or replace the permanent identity. A new profile response is `{ "created": true, "profile_id": "...", "redirect": "/worktrack/calendar", "csrf_token": "..." }`; no email is sent for that first setup. Returning access, linking, and email changes use the shared OTP UI.

When a challenge is required, the response includes `challenge_id`, `purpose`, `email`, `expires_at`, and `resend_at`. Codes contain six digits, are stored hashed, expire after 5 minutes, permit 5 verification attempts, and enforce a 60-second resend cooldown. A resend invalidates the previous code. OTPs and SMTP credentials are never returned or logged.

Each linked `access_email` is unique. An email already owned by another profile cannot be attached or merged. Linking must preserve the original profile ID, entries, settings, and reminder history. A previously verified reminder recipient does not on its own establish an account login.

### Verify code

```http
POST /api/worktrack/access/verify
Content-Type: application/json
X-CSRF-TOKEN: {currentToken}
```

```json
{
  "challenge_id": "challenge returned by send",
  "code": "six digits received by email"
}
```

A successful response includes `verified`, a refreshed `csrf_token`, the permanent public `profile_id`, and `redirect`. A valid challenge is tied to its purpose and requesting session and can be consumed only once. Login requires the address's linked profile; link/email-change also enforce current ownership. Following a signed reminder URL does not authenticate a profile; the matching profile must log in first.

### Logout

```http
POST /api/worktrack/access/logout
X-CSRF-TOKEN: {currentToken}
```

Ends this browser session. It does not delete work data, end another device's session, or disable a saved reminder. Refresh the CSRF token before the next mutation.

## App state and profile

```http
GET /api/worktrack/health
GET /api/worktrack/state
POST /api/worktrack/profile
```

Health returns service status and server time. State returns the owned profile, work entries, and `reminder_notifications`; absent access returns no owned data and the UI asks for access/setup. The profile endpoint saves the current owned profile's editable fields; it does not change identity by trusting a posted profile ID.

Profile fields use the same names as registration. Omitting the legacy `user_id` on an update preserves its existing value. Allowed `employee_status` values are `full_time`, `part_time`, `rotational`, and `custom`. Profile responses omit browser tokens, token hashes, and OTP secrets.

## Reminder recipient verification

```http
POST /api/worktrack/reminder/email/verify/send
POST /api/worktrack/reminder/email/verify
```

The send payload contains `email` (plus `browser_id` for a supported legacy profile); verify additionally contains `code`. Codes are bound to the owned profile and recipient, hashed at rest, expire after 5 minutes, permit 5 attempts, and have a 60-second resend cooldown. Resending invalidates the previous code. A changed recipient disables its previous verification and requires verification again. The UI uses the shared OTP component; a reminder recipient verification does not grant access to a different account.

## Save or disable a reminder

```http
POST /api/worktrack/reminder
Content-Type: application/json
```

```json
{
  "email": "user@example.com",
  "enabled": true,
  "reminder_time": "18:00",
  "reminder_start_date": "2026-10-26",
  "reminder_details": "Dalhin ang requirements bukas.",
  "schedule_mode": "recurring",
  "days": [1, 2, 3, 4, 5]
}
```

Days use ISO weekdays: 1 Monday to 7 Sunday. All seven days mean Daily. For a custom date use `schedule_mode: "once"`, `days: []`, and a future start date/time. Times use `Asia/Manila`. Enabling requires a verified recipient belonging to the owned profile. Disable with `enabled: false`.

Notes, up to 200 characters, are the primary email content. General reminders follow their schedule regardless of completed work logs or rest days. Saving cancels previous unsent schedules and creates the next occurrence. The response includes `profile` and `reminder_notifications`.

The persistent scheduler checks due occurrences every second. Atomic claims and a unique profile/scheduled-time key prevent duplicate attempts. **Sent** is recorded only after transport acceptance; it does not establish inbox placement or reading. Failed or interrupted attempts remain in history without automatic retries because an ambiguous failure may already have delivered the message. Recurring reminders continue with the next selected date. Prior-date schedules missed while the sender was stopped become Failed.

## Reminder notifications

```http
GET /api/worktrack/reminder/notifications
```

Returns only the session's owned profile and reminder state:

- `profile`: current recipient verification and settings.
- `reminder_notifications.next_occurrence`: Scheduled notes, schedule, `scheduled_at`, and `sending`; null if no upcoming occurrence.
- `reminder_notifications.history`: latest 20 Sent/Failed occurrences, each with `scheduled_at`, `sent_at` or `failed_at`, and safe `failure_message`.
- Timezone: `Asia/Manila`; timestamps use ISO 8601 with `+08:00`.

The visible page polls every 5 seconds and when opened or resumed. Native browser notification permission is optional and does not control in-system updates or server email delivery. Delivery continues with the browser closed while the host, database, SMTP connection, and scheduler are available.

## Work entries

```http
POST /api/worktrack/entries
Content-Type: application/json
```

```json
{
  "work_date": "2026-10-26",
  "time_in": "07:00",
  "time_out": "16:00",
  "rate_type": "normal",
  "modifier": "holiday",
  "status": "work",
  "note": "Covered holiday shift"
}
```

Allowed values: `rate_type`: `normal`/`night`; `modifier`: `none`/`holiday`/`double`; `status`: `work`/`rest`/`absent`. The backend computes regular, extended, and total hours plus daily pay using the owned profile's settings. Manual `normal_hours` and `extended_hours` are also accepted. If both manual values are null, both work times are used. A single work time, equal work times, zero work hours, or manual hours greater than the time span are rejected; fewer manual hours can represent unpaid time. Entries snapshot the hourly rate when saved. Editing and saving an entry applies the then-current rate.

```http
DELETE /api/worktrack/entries/{YYYY-MM-DD}
DELETE /api/worktrack/reset
```

Entry deletion affects one owned saved work date. Reset is destructive for the currently owned profile and its records; use the UI confirmation flow. Neither operation accepts another profile's public ID as authority.

## Security and operation

- Validate all incoming fields and enforce profile ownership server-side.
- Use same-origin cookie sessions and CSRF protection for mutations, plus API/OTP throttles.
- Use HTTPS, secure HttpOnly cookies, and persistent session storage in production.
- Keep `APP_KEY`, SMTP credentials, OTP hashes, and private backup files out of public responses/source control.
- Configure a real SMTP transport; `log` and `array` mailers cannot deliver reminders.
- Cross-device access requires one shared hosted app/database; separate local installations do not synchronize.
- [Deployment and recovery plan](DEPLOYMENT.md) covers scheduler supervision, keys, sessions, and backups. Hosting is not automatically provisioned by the application.
