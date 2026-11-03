<?php

namespace App\Services;

use App\Mail\WorkTrackDailyReminder;
use App\Models\WorkProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class ReminderService
{
    public function timezone(): string
    {
        return config('worktrack.reminder_timezone', 'Asia/Manila');
    }

    public function scheduleLabel(WorkProfile $profile): string
    {
        if ($profile->reminder_schedule_mode === 'once') {
            return 'Once on '.$profile->reminder_start_date?->format('M j, Y');
        }
        $days = array_map('intval', $profile->reminder_days ?: []);
        sort($days);
        if ($days === [1, 2, 3, 4, 5, 6, 7]) {
            return 'Daily';
        }
        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return implode(', ', array_map(fn ($day) => $names[$day] ?? '', $days));
    }

    public function active(WorkProfile $profile): bool
    {
        return $profile->reminder_enabled && $profile->email_verified_at && $profile->email
            && $profile->reminder_time && ($profile->reminder_schedule_mode === 'once' || ! empty($profile->reminder_days));
    }

    private function matchesSchedule(WorkProfile $profile, object $row): bool
    {
        if (! $this->active($profile)
            || $profile->email !== $row->recipient
            || (string) ($profile->reminder_details ?? '') !== (string) ($row->details ?? '')) {
            return false;
        }
        $at = Carbon::parse($row->scheduled_at, 'UTC')->timezone($this->timezone());
        if ($at->format('H:i') !== substr($profile->reminder_time, 0, 5)) {
            return false;
        }
        if ($profile->reminder_start_date && $at->toDateString() < $profile->reminder_start_date->toDateString()) {
            return false;
        }
        if ($profile->reminder_schedule_saved_at && $at->lessThan(Carbon::parse($profile->getRawOriginal('reminder_schedule_saved_at'), 'UTC'))) {
            return false;
        }

        return $profile->reminder_schedule_mode === 'once'
            ? $at->toDateString() === $profile->reminder_start_date?->toDateString()
            : in_array($at->dayOfWeekIso, array_map('intval', $profile->reminder_days ?: []), true);
    }

    public function ensureUpcoming(WorkProfile $profile): ?object
    {
        if (! $this->active($profile)) {
            return null;
        }
        $now = Carbon::now($this->timezone());
        $start = $profile->reminder_start_date?->toDateString() ?: $now->toDateString();
        $savedAt = $profile->reminder_schedule_saved_at
            ? Carbon::parse($profile->getRawOriginal('reminder_schedule_saved_at'), 'UTC') : null;
        $date = $profile->reminder_schedule_mode === 'once'
            ? Carbon::parse($start, $this->timezone())->startOfDay()
            : Carbon::parse(max($start, $now->toDateString()), $this->timezone())->startOfDay();
        $existing = DB::table('work_reminder_deliveries')->where('profile_id', $profile->id)
            ->whereDate('reminder_date', '>=', $date->toDateString())->get()->keyBy('scheduled_at');
        for ($offset = 0; $offset < 370; $offset++, $date->addDay()) {
            if ($profile->reminder_schedule_mode === 'once' && $offset > 0) {
                break;
            }
            if ($profile->reminder_schedule_mode !== 'once' && ! in_array($date->dayOfWeekIso, array_map('intval', $profile->reminder_days ?: []), true)) {
                continue;
            }
            $at = $date->copy()->setTimeFromTimeString(substr($profile->reminder_time, 0, 5).':00');
            if ($savedAt && $at->lessThan($savedAt)) {
                continue;
            }
            $utc = $at->copy()->utc()->format('Y-m-d H:i:s');
            $row = $existing->get($utc);
            if ($row && ! in_array($row->status, ['scheduled', 'cancelled'], true)) {
                continue;
            }
            if (! $row || $row->status === 'cancelled') {
                $values = [
                    'reminder_date' => $date->toDateString(), 'status' => 'scheduled',
                    'recipient' => $profile->email, 'details' => $profile->reminder_details,
                    'schedule_label' => $this->scheduleLabel($profile), 'updated_at' => now(),
                ];
                if ($row) {
                    DB::table('work_reminder_deliveries')->where('id', $row->id)->where('status', 'cancelled')->update($values);
                } else {
                    DB::table('work_reminder_deliveries')->insertOrIgnore($values + [
                        'profile_id' => $profile->id, 'scheduled_at' => $utc, 'created_at' => now(),
                    ]);
                }
            }

            return DB::table('work_reminder_deliveries')->where('profile_id', $profile->id)->where('scheduled_at', $utc)->first();
        }

        return null;
    }

    public function sendDue(): int
    {
        // An interrupted send has an unknown outcome; never automatically resend it.
        DB::table('work_reminder_deliveries')->where('status', 'pending')
            ->where('attempted_at', '<', Carbon::now('UTC')->subMinutes(10)->format('Y-m-d H:i:s'))
            ->update(['status' => 'failed', 'failed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'failure_message' => 'Delivery could not be confirmed. No automatic retry was made.', 'updated_at' => now()]);
        DB::table('work_reminder_deliveries')->where('status', 'scheduled')
            ->whereIn('profile_id', WorkProfile::where('reminder_enabled', true)->select('id'))
            ->where('scheduled_at', '<', Carbon::now($this->timezone())->startOfDay()->utc()->format('Y-m-d H:i:s'))
            ->update(['status' => 'failed', 'failed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'failure_message' => 'This occurrence was missed while the sender was unavailable.', 'updated_at' => now()]);
        $sent = 0;
        foreach (WorkProfile::where('reminder_enabled', true)->whereNotNull('email_verified_at')->get() as $profile) {
            $row = $this->ensureUpcoming($profile);
            if (! $row || $row->status !== 'scheduled' || Carbon::parse($row->scheduled_at, 'UTC')->isFuture()) {
                continue;
            }
            $claimed = DB::transaction(function () use ($profile, $row): int {
                $current = WorkProfile::whereKey($profile->id)->lockForUpdate()->first();
                if (! $current || ! $this->matchesSchedule($current, $row)) {
                    DB::table('work_reminder_deliveries')->where('id', $row->id)->where('status', 'scheduled')->update(['status' => 'cancelled']);

                    return 0;
                }

                return DB::table('work_reminder_deliveries')->where('id', $row->id)->where('status', 'scheduled')
                    ->update(['status' => 'pending', 'attempted_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'), 'updated_at' => now()]);
            });
            if (! $claimed) {
                continue;
            }
            $scheduled = Carbon::parse($row->scheduled_at, 'UTC')->timezone($this->timezone());
            try {
                Mail::to($row->recipient, $profile->nickname)->send(new WorkTrackDailyReminder(
                    $profile->nickname, $scheduled->format('F j, Y'), URL::signedRoute('worktrack.reminders.open', ['profile' => $profile->id]),
                    URL::signedRoute('worktrack.reminders.disable', ['profile' => $profile->id]),
                    (string) ($row->details ?? ''), $scheduled->format('F j, Y · h:i A').' Philippine Time', $row->schedule_label,
                ));
            } catch (Throwable $error) {
                DB::table('work_reminder_deliveries')->where('id', $row->id)->update([
                    'status' => 'failed', 'failed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                    'failure_message' => 'The email service did not confirm acceptance. No automatic retry was made.', 'updated_at' => now(),
                ]);
                // Keep diagnostics useful without writing recipients, message bodies, OTPs, or credentials.
                Log::warning('WORKTRACK reminder transport failed.', [
                    'delivery_id' => $row->id,
                    'exception' => get_class($error),
                    'code' => (string) $error->getCode(),
                ]);
                $this->ensureUpcoming($profile->fresh());

                continue;
            }
            // Sent means transport acceptance, not inbox placement or a read receipt.
            DB::table('work_reminder_deliveries')->where('id', $row->id)->update([
                'status' => 'sent', 'sent_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'), 'updated_at' => now(),
            ]);
            $profile->forceFill(['last_email_reminded_on' => $scheduled->toDateString()])->saveQuietly();
            $this->ensureUpcoming($profile->fresh());
            $sent++;
        }

        return $sent;
    }

    public function notifications(WorkProfile $profile): array
    {
        $rows = DB::table('work_reminder_deliveries')->where('profile_id', $profile->id)
            ->whereIn('status', ['scheduled', 'pending', 'sent', 'failed'])->orderByDesc('scheduled_at')->get();
        $format = fn ($at) => $at ? Carbon::parse($at, 'UTC')->timezone($this->timezone())->toIso8601String() : null;
        $transform = fn ($row) => [
            'id' => $row->id, 'status' => $row->status === 'pending' ? 'scheduled' : $row->status,
            'sending' => $row->status === 'pending', 'notes' => $row->details,
            'schedule' => $row->schedule_label, 'scheduled_at' => $format($row->scheduled_at),
            'sent_at' => $format($row->sent_at), 'failed_at' => $format($row->failed_at),
            'failure_message' => $row->failure_message,
        ];
        $upcoming = $this->active($profile) ? $rows->whereIn('status', ['scheduled', 'pending'])->filter(fn ($row) => $this->matchesSchedule($profile, $row))->sortBy('scheduled_at')->first() : null;

        return [
            'enabled' => $this->active($profile), 'timezone' => $this->timezone(),
            'notes' => $profile->reminder_details, 'schedule' => $this->scheduleLabel($profile),
            'system_notifications' => app(SystemNotificationService::class)->recent($profile),
            'next_occurrence' => $upcoming ? $transform($upcoming) : null,
            'history' => $rows->whereIn('status', ['sent', 'failed'])->sortByDesc('scheduled_at')->take(20)->map($transform)->values()->all(),
            'server_time' => Carbon::now($this->timezone())->toIso8601String(),
        ];
    }
}
