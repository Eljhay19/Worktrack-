<?php

namespace Tests\Feature;

use App\Mail\WorkTrackDailyReminder;
use App\Models\WorkProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesLegacyProfiles;
use Tests\TestCase;

class ReminderNotificationsTest extends TestCase
{
    use CreatesLegacyProfiles;
    use RefreshDatabase;

    private string $browser = 'reminder-notification-test-browser';

    private function profile(): WorkProfile
    {
        config(['mail.default' => 'smtp']);
        $this->updateLegacyProfile([
            'browser_id' => $this->browser, 'user_id' => 'REMINDER-TEST',
            'nickname' => 'Reminder user', 'work_type' => 'Crew', 'hourly_rate' => 90,
        ])->assertOk();
        $profile = WorkProfile::firstOrFail();
        $profile->forceFill(['email' => 'recipient@example.com', 'email_verified_at' => now()])->save();

        return $profile;
    }

    private function schedule(array $overrides = [])
    {
        return $this->postJson('/api/worktrack/reminder', array_replace([
            'browser_id' => $this->browser, 'email' => 'recipient@example.com', 'enabled' => true,
            'reminder_time' => '12:00', 'reminder_start_date' => '2026-10-26',
            'reminder_details' => 'Dalhin ang requirements bukas.', 'days' => [1, 3],
            'schedule_mode' => 'recurring',
        ], $overrides));
    }

    private function notifications()
    {
        return $this->getJson('/api/worktrack/reminder/notifications?browser_id='.$this->browser);
    }

    public function test_one_time_reminder_shows_scheduled_then_sent_at_exact_philippine_time(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 10, 26, 11, 59, 59, 'Asia/Manila'));
        $profile = $this->profile();
        $this->schedule(['schedule_mode' => 'once', 'days' => []])->assertOk()
            ->assertJsonPath('reminder_notifications.next_occurrence.status', 'scheduled')
            ->assertJsonPath('reminder_notifications.next_occurrence.scheduled_at', '2026-10-26T12:00:00+08:00');
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->travel(1)->seconds();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
        Mail::assertSent(WorkTrackDailyReminder::class, function ($mail): bool {
            $body = $mail->render();

            return $mail->hasTo('recipient@example.com') && str_contains($body, 'Dalhin ang requirements bukas.')
                && str_contains($body, '12:00 PM') && str_contains($body, 'Philippine Time')
                && ! str_contains($body, 'record or complete your time in');
        });
        $this->notifications()->assertOk()
            ->assertJsonPath('reminder_notifications.history.0.status', 'sent')
            ->assertJsonPath('reminder_notifications.history.0.sent_at', '2026-10-26T12:00:00+08:00')
            ->assertJsonPath('reminder_notifications.next_occurrence', null);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
        $this->assertDatabaseCount('work_reminder_deliveries', 1);
        $this->assertDatabaseHas('work_reminder_deliveries', [
            'profile_id' => $profile->id, 'scheduled_at' => '2026-10-26 04:00:00', 'status' => 'sent',
        ]);
    }

    public function test_optional_reminder_details_can_be_blank_and_email_still_sends(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 10, 26, 11, 59, 59, 'Asia/Manila'));
        $this->profile();
        $this->schedule(['schedule_mode' => 'once', 'days' => [], 'reminder_details' => ''])->assertOk();
        $this->travel(1)->seconds();

        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSent(WorkTrackDailyReminder::class, function ($mail): bool {
            $body = $mail->render();

            return $mail->hasTo('recipient@example.com')
                && str_contains($body, 'You have a scheduled reminder.')
                && ! str_contains($body, 'null');
        });
        $this->notifications()->assertOk()
            ->assertJsonPath('reminder_notifications.history.0.status', 'sent')
            ->assertJsonPath('reminder_notifications.history.0.notes', null);
    }

    public function test_recurring_reminder_retains_sent_history_and_next_selected_day(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 10, 26, 11, 59, 0, 'Asia/Manila'));
        $this->profile();
        $this->schedule()->assertOk();
        $this->travel(1)->minutes();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        $this->notifications()->assertOk()
            ->assertJsonPath('reminder_notifications.history.0.status', 'sent')
            ->assertJsonPath('reminder_notifications.history.0.notes', 'Dalhin ang requirements bukas.')
            ->assertJsonPath('reminder_notifications.next_occurrence.scheduled_at', '2026-10-28T12:00:00+08:00');
        $this->travelTo(Carbon::create(2026, 10, 28, 12, 0, 0, 'Asia/Manila'));
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 2);
        $this->notifications()->assertJsonCount(2, 'reminder_notifications.history')
            ->assertJsonPath('reminder_notifications.next_occurrence.scheduled_at', '2026-11-02T12:00:00+08:00');
    }

    public function test_failed_transport_is_retained_as_failed_without_automatic_duplicate_attempts(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 26, 11, 59, 0, 'Asia/Manila'));
        $this->profile();
        $this->schedule()->assertOk();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Private SMTP diagnostic'));
        $this->travel(1)->minutes();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        $response = $this->notifications()->assertOk()
            ->assertJsonPath('reminder_notifications.history.0.status', 'failed')
            ->assertJsonPath('reminder_notifications.history.0.sent_at', null)
            ->assertJsonPath('reminder_notifications.next_occurrence.scheduled_at', '2026-10-28T12:00:00+08:00');
        $this->assertStringNotContainsString('Private SMTP diagnostic', $response->getContent());
        $this->assertDatabaseHas('work_reminder_deliveries', ['status' => 'failed', 'sent_at' => null]);
    }

    public function test_schedule_edits_cancel_previous_occurrence_and_status_is_owned_by_browser(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 10, 26, 11, 59, 0, 'Asia/Manila'));
        $profile = $this->profile();
        $this->schedule()->assertOk();
        $this->schedule(['reminder_time' => '13:00', 'reminder_details' => 'Updated notes'])->assertOk();
        $this->travel(1)->minutes();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertDatabaseHas('work_reminder_deliveries', [
            'profile_id' => $profile->id, 'scheduled_at' => '2026-10-26 04:00:00', 'status' => 'cancelled',
        ]);
        $this->notifications()->assertJsonPath('reminder_notifications.next_occurrence.notes', 'Updated notes');
        $this->getJson('/api/worktrack/reminder/notifications?browser_id=some-other-browser-identifier')->assertUnauthorized();
        $this->schedule(['enabled' => false])->assertOk()->assertJsonPath('reminder_notifications.next_occurrence', null);
        $this->travel(1)->hours();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
    }

    public function test_custom_date_rejects_past_times_and_uses_philippine_midnight(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 10, 25, 23, 59, 0, 'Asia/Manila'));
        $this->profile();
        $this->schedule(['schedule_mode' => 'once', 'days' => [], 'reminder_start_date' => '2026-10-25', 'reminder_time' => '23:58'])
            ->assertUnprocessable()->assertJsonValidationErrors('reminder_start_date');
        $this->schedule(['schedule_mode' => 'once', 'days' => [], 'reminder_start_date' => '2026-10-26', 'reminder_time' => '00:00'])->assertOk();
        $this->assertDatabaseHas('work_reminder_deliveries', ['scheduled_at' => '2026-10-25 16:00:00']);
        $this->travel(1)->minutes();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
        $this->notifications()->assertJsonPath('reminder_notifications.history.0.sent_at', '2026-10-26T00:00:00+08:00');
    }

    public function test_daily_schedule_uses_next_day_and_does_not_retry_interrupted_send(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 10, 26, 11, 59, 0, 'Asia/Manila'));
        $this->profile();
        $this->schedule(['days' => [1, 2, 3, 4, 5, 6, 7]])->assertOk();
        $this->travel(1)->minutes();
        DB::table('work_reminder_deliveries')->where('status', 'scheduled')->update([
            'status' => 'pending', 'attempted_at' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
        ]);
        $this->travel(11)->minutes();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->notifications()->assertJsonPath('reminder_notifications.history.0.status', 'failed')
            ->assertJsonPath('reminder_notifications.history.0.sent_at', null)
            ->assertJsonPath('reminder_notifications.next_occurrence.schedule', 'Daily')
            ->assertJsonPath('reminder_notifications.next_occurrence.scheduled_at', '2026-10-27T12:00:00+08:00');
        $this->travelTo(Carbon::create(2026, 10, 27, 12, 0, 0, 'Asia/Manila'));
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
        $this->notifications()->assertJsonPath('reminder_notifications.history.0.status', 'sent')
            ->assertJsonPath('reminder_notifications.next_occurrence.scheduled_at', '2026-10-28T12:00:00+08:00');
    }

    public function test_occurrence_missed_on_previous_date_is_failed_and_next_day_remains_scheduled(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::create(2026, 10, 26, 11, 59, 0, 'Asia/Manila'));
        $this->profile();
        $this->schedule(['days' => [1, 2, 3, 4, 5, 6, 7]])->assertOk();
        $this->travelTo(Carbon::create(2026, 10, 27, 10, 0, 0, 'Asia/Manila'));
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->notifications()->assertJsonPath('reminder_notifications.history.0.status', 'failed')
            ->assertJsonPath('reminder_notifications.history.0.failure_message', 'This occurrence was missed while the sender was unavailable.')
            ->assertJsonPath('reminder_notifications.next_occurrence.scheduled_at', '2026-10-27T12:00:00+08:00');
    }

    public function test_system_notifications_include_one_persistent_profile_ready_update(): void
    {
        $profile = $this->profile();

        $this->notifications()->assertOk()
            ->assertJsonPath('reminder_notifications.system_notifications.0.type', 'profile_ready')
            ->assertJsonPath('reminder_notifications.system_notifications.0.message', 'Welcome to WorkTrack. Your profile is ready.');

        $this->notifications()->assertOk()
            ->assertJsonCount(1, 'reminder_notifications.system_notifications');
        $this->assertDatabaseHas('worktrack_system_notifications', [
            'profile_id' => $profile->id,
            'type' => 'profile_ready',
        ]);
    }
}
