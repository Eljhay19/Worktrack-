<?php

namespace App\Http\Controllers;

use App\Mail\WorkTrackEmailVerificationCode;
use App\Models\WorkEntry;
use App\Models\WorkProfile;
use App\Services\ProfileAccessService;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkTrackApiController extends Controller
{
    public function health(): JsonResponse
    {
        DB::select('select 1');

        return response()->json([
            'status' => 'ok',
            'service' => 'WORKTRACK API',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $profile = app(ProfileAccessService::class)->current($request, true);
        $intended = $request->session()->get('worktrack_intended_profile');
        if ($intended && (! $profile || $profile->id !== (int) $intended || ! $profile->access_enabled)) {
            return response()->json(['profile' => null, 'entries' => [], 'access_required' => true, 'switch_required' => (bool) $profile, 'csrf_token' => $request->session()->token()]);
        }

        if (! $profile) {
            return response()->json([
                'profile' => null,
                'entries' => [],
                'csrf_token' => $request->session()->token(),
            ]);
        }

        $profile->forceFill(['last_seen_at' => now()])->saveQuietly();

        $entries = $profile->entries()
            ->orderBy('work_date')
            ->get()
            ->map(fn (WorkEntry $entry) => $this->transformEntry($entry, (float) $profile->hourly_rate))
            ->values();

        return response()->json([
            'profile' => $this->transformProfile($profile),
            'entries' => $entries,
            'csrf_token' => $request->session()->token(),
            'reminder_notifications' => app(ReminderService::class)->notifications($profile),
        ]);
    }

    public function saveProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
            'user_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'nickname' => ['required', 'string', 'max:120'],
            'work_type' => ['required', 'string', 'max:120'],
            'employee_status' => ['sometimes', Rule::in(['full_time', 'part_time', 'rotational', 'custom'])],
            'default_regular_hours' => ['sometimes', 'numeric', 'min:1', 'max:24'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);

        $profile = $this->currentProfile();
        abort_unless($profile, 401, 'Verify your email to create or access a profile.');
        $employeeStatus = $data['employee_status'] ?? $profile->employee_status ?? 'full_time';
        $defaultRegularHours = $data['default_regular_hours'] ?? $profile->default_regular_hours ?? $this->defaultRegularHoursFor($employeeStatus);
        $profile->fill([
            'user_id' => $data['user_id'] ?? $profile->user_id,
            'nickname' => $data['nickname'],
            'work_type' => $data['work_type'],
            'employee_status' => $employeeStatus,
            'default_regular_hours' => $defaultRegularHours,
            'hourly_rate' => $data['hourly_rate'],
            'last_seen_at' => now(),
        ]);
        $profile->save();

        return response()->json(['profile' => $this->transformProfile($profile)]);
    }

    public function sendReminderVerification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
            'email' => ['required', 'email', 'max:254'],
        ]);
        $profile = $this->currentProfileOrFail();
        $email = Str::lower(trim($data['email']));

        return DB::transaction(function () use ($profile, $email): JsonResponse {
            // Serialize requests for this profile so simultaneous clicks cannot send competing codes.
            $profile = WorkProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if ($profile->email === $email && $profile->email_verified_at) {
                return response()->json(['verified' => true, 'message' => 'This email is already verified.']);
            }
            if ($profile->access_enabled) {
                throw ValidationException::withMessages(['email' => 'Manage your verified email in profile settings.']);
            }
            if (in_array(config('mail.default'), ['log', 'array'], true)) {
                return response()->json([
                    'message' => 'Email delivery is not configured yet. Set up a real mail transport before requesting a verification code.',
                ], 503);
            }

            $challenge = DB::table('work_reminder_email_verifications')->where('profile_id', $profile->id)->first();
            if ($challenge && Carbon::parse($challenge->last_sent_at)->addSeconds(60)->isAfter(now())) {
                return response()->json(['message' => 'Please wait one minute before requesting another code.'], 429);
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            try {
                Mail::to($email)->send(new WorkTrackEmailVerificationCode($code));
            } catch (\Throwable) {
                // Keep the previous email and challenge intact if SMTP delivery fails.
                return response()->json([
                    'message' => 'The verification email could not be sent. Please try again shortly.',
                ], 503);
            }

            $now = now();
            $expiresAt = $now->copy()->addMinutes(5);
            $profile->forceFill([
                'email' => $email,
                'email_verified_at' => null,
                'reminder_enabled' => false,
                'last_email_reminded_on' => null,
            ])->save();
            DB::table('work_reminder_email_verifications')->updateOrInsert(
                ['profile_id' => $profile->id],
                [
                    'email' => $email,
                    'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
                    'expires_at' => $expiresAt,
                    'last_sent_at' => $now,
                    'attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            return response()->json([
                'sent' => true,
                'verified' => false,
                'expires_at' => $expiresAt->toIso8601String(),
                'resend_at' => $now->copy()->addSeconds(60)->toIso8601String(),
                'message' => 'Verification code sent.',
            ]);
        });
    }

    public function verifyReminderEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
            'email' => ['required', 'email', 'max:254'],
            'code' => ['required', 'digits:6'],
        ]);
        $profile = $this->currentProfileOrFail();
        $email = Str::lower(trim($data['email']));

        return DB::transaction(function () use ($profile, $data, $email): JsonResponse {
            $profile = WorkProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $challenge = DB::table('work_reminder_email_verifications')
                ->where('profile_id', $profile->id)
                ->lockForUpdate()
                ->first();

            if (! $challenge || $challenge->email !== $email || $challenge->email !== $profile->email
                || now()->greaterThanOrEqualTo(Carbon::parse($challenge->expires_at))) {
                return response()->json(['message' => 'This code expired or is no longer valid. Request a new code.'], 422);
            }
            if ((int) $challenge->attempts >= 5) {
                return response()->json(['message' => 'Too many incorrect codes. Request a new code.'], 429);
            }

            $candidate = hash_hmac('sha256', $data['code'], (string) config('app.key'));
            if (! hash_equals($challenge->code_hash, $candidate)) {
                DB::table('work_reminder_email_verifications')
                    ->where('profile_id', $profile->id)
                    ->update(['attempts' => (int) $challenge->attempts + 1, 'updated_at' => now()]);

                return response()->json(['message' => 'That verification code is incorrect.'], 422);
            }

            $profile->forceFill(['email_verified_at' => now()])->save();
            DB::table('work_reminder_email_verifications')->where('profile_id', $profile->id)->delete();

            return response()->json(['verified' => true, 'profile' => $this->transformProfile($profile)]);
        });
    }

    public function reminderNotifications(Request $request): JsonResponse
    {
        $data = $request->validate(['browser_id' => ['nullable', 'string', 'min:12', 'max:180']]);
        $profile = $this->currentProfileOrFail();

        return response()->json([
            'profile' => $this->transformProfile($profile),
            'reminder_notifications' => app(ReminderService::class)->notifications($profile),
        ]);
    }

    public function saveReminder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
            'email' => ['nullable', 'email', 'max:254'],
            'enabled' => ['required', 'boolean'],
            'reminder_time' => ['nullable', 'date_format:H:i'],
            'reminder_start_date' => ['nullable', 'date_format:Y-m-d'],
            'reminder_details' => ['nullable', 'string', 'max:200'],
            'schedule_mode' => ['sometimes', Rule::in(['recurring', 'once'])],
            'days' => ['nullable', 'array', 'max:7'],
            'days.*' => ['integer', 'between:1,7', 'distinct'],
        ]);
        $ownedProfile = $this->currentProfileOrFail();

        return DB::transaction(function () use ($ownedProfile, $data): JsonResponse {
            $profile = WorkProfile::whereKey($ownedProfile->id)->lockForUpdate()->firstOrFail();
            $enabled = (bool) $data['enabled'];
            $mode = $data['schedule_mode'] ?? $profile->reminder_schedule_mode ?? 'recurring';
            $time = $data['reminder_time'] ?? substr((string) $profile->reminder_time, 0, 5);
            $date = $data['reminder_start_date'] ?? $profile->reminder_start_date?->toDateString()
                ?? Carbon::now(config('worktrack.reminder_timezone'))->toDateString();
            $days = isset($data['days']) ? array_values(array_map('intval', $data['days'])) : ($profile->reminder_days ?: []);
            if ($enabled) {
                if (! $profile->email || ! $profile->email_verified_at
                    || (isset($data['email']) && Str::lower(trim($data['email'])) !== Str::lower($profile->email))) {
                    throw ValidationException::withMessages(['email' => 'Verify your reminder email before enabling reminders.']);
                }
                if (! $time || ($mode === 'recurring' && ! $days)) {
                    throw ValidationException::withMessages(['schedule' => 'Choose a time and the days for this reminder.']);
                }
                if ($mode === 'once' && Carbon::parse($date.' '.$time, config('worktrack.reminder_timezone'))->lessThanOrEqualTo(Carbon::now())) {
                    throw ValidationException::withMessages(['reminder_start_date' => 'Choose a future Philippine date and time for this reminder.']);
                }
            }
            $details = trim((string) ($data['reminder_details'] ?? $profile->reminder_details));
            $profile->forceFill([
                'reminder_enabled' => $enabled, 'reminder_time' => $time ?: null,
                'reminder_start_date' => $date, 'reminder_details' => $details === '' ? null : $details,
                'reminder_schedule_mode' => $mode, 'reminder_days' => $mode === 'once' ? [] : $days,
                'reminder_schedule_saved_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'skip_if_complete' => false, 'last_email_reminded_on' => null,
            ])->save();
            DB::table('work_reminder_deliveries')->where('profile_id', $profile->id)->where('status', 'scheduled')
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
            $reminders = app(ReminderService::class);
            $reminders->ensureUpcoming($profile);

            return response()->json([
                'profile' => $this->transformProfile($profile), 'enabled' => $enabled,
                'reminder_notifications' => $reminders->notifications($profile),
            ]);
        });
    }

    public function reminderDue(Request $request): JsonResponse
    {
        $profile = app(ProfileAccessService::class)->current($request, true);
        $notifications = $profile ? app(ReminderService::class)->notifications($profile) : null;
        $next = $notifications['next_occurrence'] ?? null;

        return response()->json([
            'enabled' => (bool) ($notifications['enabled'] ?? false),
            'due' => (bool) ($next && Carbon::parse($next['scheduled_at'])->lessThanOrEqualTo(now())),
            'server_time' => now()->toIso8601String(),
            'reminder_notifications' => $notifications,
        ]);
    }

    public function saveEntry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'regular_hours' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'normal_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'extended_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rate_type' => ['required', Rule::in(['normal', 'night'])],
            'modifier' => ['required', Rule::in(['none', 'holiday', 'double'])],
            'status' => ['required', Rule::in(['work', 'rest', 'absent'])],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $profile = $this->currentProfileOrFail();
        $regularHours = (float) ($data['regular_hours'] ?? $profile->default_regular_hours ?? $this->defaultRegularHoursFor($profile->employee_status ?? 'full_time'));
        $hasManualHours = isset($data['normal_hours']) || isset($data['extended_hours']);
        $hours = $hasManualHours
            ? $this->manualHours($data['normal_hours'] ?? null, $data['extended_hours'] ?? null, $data['status'])
            : $this->computeHours($data['time_in'] ?? null, $data['time_out'] ?? null, $data['status'], $regularHours);

        if ($data['status'] === 'work') {
            $timeIn = $data['time_in'] ?? null;
            $timeOut = $data['time_out'] ?? null;
            if ((bool) $timeIn !== (bool) $timeOut) {
                throw ValidationException::withMessages(['time_out' => 'Set both Time In and Time Out, or leave both blank.']);
            }
            if ($timeIn && $timeOut && $timeIn === $timeOut) {
                throw ValidationException::withMessages(['time_out' => 'Time In and Time Out cannot be the same.']);
            }
            $total = $hours['normal_hours'] + $hours['extended_hours'];
            if ($total <= 0) {
                throw ValidationException::withMessages(['normal_hours' => 'Enter work hours or set both work times.']);
            }
            if ($hasManualHours && $timeIn && $timeOut) {
                $elapsed = $this->computeHours($timeIn, $timeOut, 'work', $regularHours);
                if ($total > $elapsed['normal_hours'] + $elapsed['extended_hours']) {
                    throw ValidationException::withMessages(['normal_hours' => 'Work hours cannot exceed the Time In to Time Out span.']);
                }
            }
        }

        $entry = WorkEntry::updateOrCreate(
            [
                'profile_id' => $profile->id,
                'work_date' => $data['work_date'],
            ],
            [
                'browser_id' => $profile->browser_token_hash ?: $profile->browser_id,
                'time_in' => $data['status'] === 'work' ? ($data['time_in'] ?? null) : null,
                'time_out' => $data['status'] === 'work' ? ($data['time_out'] ?? null) : null,
                'regular_hours' => $regularHours,
                'hourly_rate_snapshot' => $profile->hourly_rate,
                'normal_hours' => $hours['normal_hours'],
                'extended_hours' => $hours['extended_hours'],
                'rate_type' => $data['rate_type'],
                'modifier' => $data['modifier'],
                'status' => $data['status'],
                'note' => $data['note'] ?? '',
            ]
        );

        return response()->json([
            'entry' => $this->transformEntry($entry, (float) $profile->hourly_rate),
        ]);
    }

    public function deleteEntry(Request $request, string $workDate): JsonResponse
    {
        $data = $request->validate([
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
        ]);
        validator(['work_date' => $workDate], [
            'work_date' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        $profile = $this->currentProfileOrFail();
        if ($profile) {
            $profile->entries()
                ->where('work_date', $workDate)
                ->delete();
        }

        return response()->json(['deleted' => true]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
        ]);

        $profile = $this->currentProfileOrFail();
        $entriesDeleted = DB::transaction(function () use ($profile): int {
            return $profile->entries()->delete();
        });

        return response()->json([
            'reset' => true,
            'entries_deleted' => $entriesDeleted,
            'csrf_token' => $request->session()->token(),
        ]);
    }

    private function currentProfile(): ?WorkProfile
    {
        $profile = app(ProfileAccessService::class)->current(request(), true);
        $expected = request()->header('X-WorkTrack-Profile');
        if (! request()->isMethodSafe() && $expected && (! $profile || ! hash_equals($profile->system_user_id, $expected))) {
            abort(409, 'The active profile changed. Reload before saving.');
        }

        return $profile;
    }

    private function currentProfileOrFail(): WorkProfile
    {
        $profile = $this->currentProfile();

        if (! $profile) {
            abort(401, 'Verify your email to access your saved profile.');
        }

        return $profile;
    }

    private function computeHours(?string $timeIn, ?string $timeOut, string $status, float $regularHours): array
    {
        if ($status !== 'work' || ! $timeIn || ! $timeOut) {
            return ['normal_hours' => 0, 'extended_hours' => 0];
        }

        $start = Carbon::createFromFormat('H:i', $timeIn);
        $end = Carbon::createFromFormat('H:i', $timeOut);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $totalHours = round($start->diffInMinutes($end) / 60, 2);
        $regularLimit = max(0, min($regularHours, 24));

        return [
            'normal_hours' => min($totalHours, $regularLimit),
            'extended_hours' => max($totalHours - $regularLimit, 0),
        ];
    }

    private function manualHours(null|int|float|string $normalHours, null|int|float|string $extendedHours, string $status): array
    {
        if ($status !== 'work') {
            return ['normal_hours' => 0, 'extended_hours' => 0];
        }

        $normal = round((float) ($normalHours ?? 0), 2);
        $extended = round((float) ($extendedHours ?? 0), 2);

        if (($normal + $extended) > 24) {
            throw ValidationException::withMessages([
                'normal_hours' => 'Total normal and extended hours must not exceed 24 hours.',
            ]);
        }

        return [
            'normal_hours' => $normal,
            'extended_hours' => $extended,
        ];
    }

    private function defaultRegularHoursFor(?string $employeeStatus): float
    {
        return match ($employeeStatus) {
            'part_time' => 4,
            'rotational' => 10,
            default => 8,
        };
    }

    private function transformProfile(WorkProfile $profile): array
    {
        $verification = DB::table('work_reminder_email_verifications')
            ->where('profile_id', $profile->id)
            ->first(['expires_at', 'last_sent_at']);

        return [
            'system_user_id' => $profile->system_user_id,
            'user_id' => $profile->user_id,
            'username' => $profile->user_id,
            'device_access_enabled' => (bool) $profile->access_enabled,
            'reminder_configured' => (bool) $profile->reminder_schedule_saved_at,
            'email' => $profile->email,
            'nickname' => $profile->nickname,
            'work_type' => $profile->work_type,
            'employee_status' => $profile->employee_status ?? 'full_time',
            'default_regular_hours' => (float) ($profile->default_regular_hours ?? $this->defaultRegularHoursFor($profile->employee_status ?? 'full_time')),
            'hourly_rate' => (float) $profile->hourly_rate,
            'reminder_enabled' => app(ReminderService::class)->active($profile),
            'reminder_schedule_mode' => $profile->reminder_schedule_mode ?? 'recurring',
            'reminder_time' => substr((string) $profile->reminder_time, 0, 5),
            'reminder_start_date' => optional($profile->reminder_start_date)->toDateString(),
            'reminder_details' => $profile->reminder_details,
            'reminder_days' => $profile->reminder_days ?: [],
            'skip_if_complete' => (bool) $profile->skip_if_complete,
            'email_verified' => (bool) $profile->email_verified_at,
            'email_verified_at' => $profile->email_verified_at?->toIso8601String(),
            'reminder_verification_expires_at' => $verification?->expires_at
                ? Carbon::parse($verification->expires_at, config('app.timezone'))->toIso8601String()
                : null,
            'reminder_verification_resend_at' => $verification?->last_sent_at
                ? Carbon::parse($verification->last_sent_at)->addSeconds(60)->toIso8601String()
                : null,
        ];
    }

    private function transformEntry(WorkEntry $entry, float $hourlyRate): array
    {
        $appliedRate = (float) ($entry->hourly_rate_snapshot ?? $hourlyRate);
        $normalMultiplier = $entry->rate_type === 'night' ? 1.10 : 1;
        $modifierMultiplier = match ($entry->modifier) {
            'holiday' => 1.30,
            'double' => 2.0,
            default => 1,
        };

        $pay = 0;
        if ($entry->status === 'work') {
            $pay = ($entry->normal_hours * $appliedRate * $normalMultiplier * $modifierMultiplier)
                + ($entry->extended_hours * $appliedRate * $normalMultiplier * $modifierMultiplier * 1.25);
        }

        return [
            'id' => $entry->id,
            'work_date' => $entry->work_date->toDateString(),
            'time_in' => $entry->time_in,
            'time_out' => $entry->time_out,
            'regular_hours' => (float) ($entry->regular_hours ?? 8),
            'hourly_rate_applied' => $appliedRate,
            'normal_hours' => (float) $entry->normal_hours,
            'extended_hours' => (float) $entry->extended_hours,
            'total_hours' => (float) $entry->normal_hours + (float) $entry->extended_hours,
            'rate_type' => $entry->rate_type,
            'modifier' => $entry->modifier,
            'status' => $entry->status,
            'note' => $entry->note,
            'daily_pay' => round($pay, 2),
        ];
    }
}
