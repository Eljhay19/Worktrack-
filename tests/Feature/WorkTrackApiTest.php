<?php

namespace Tests\Feature;

use App\Mail\WorkTrackDailyReminder;
use App\Mail\WorkTrackEmailVerificationCode;
use App\Models\WorkEntry;
use App\Models\WorkProfile;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesLegacyProfiles;
use Tests\TestCase;

class WorkTrackApiTest extends TestCase
{
    use CreatesLegacyProfiles;
    use RefreshDatabase;

    public function test_api_health_endpoint_works(): void
    {
        $this->getJson('/api/worktrack/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'WORKTRACK API')
            ->assertJsonStructure(['server_time']);
    }

    public function test_profile_entry_and_work_log_reset_flow_keeps_profile(): void
    {
        $browserId = 'test-browser-id';

        $this->getJson('/api/worktrack/state?browser_id='.$browserId)
            ->assertOk()
            ->assertJsonPath('profile', null)
            ->assertJsonCount(0, 'entries');

        $this->postJson('/api/worktrack/entries', [
            'browser_id' => $browserId,
            'work_date' => '2026-10-02',
            'time_in' => '07:00',
            'time_out' => '18:00',
            'rate_type' => 'normal',
            'modifier' => 'holiday',
            'status' => 'work',
        ])->assertUnauthorized();

        $profileResponse = $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-001',
            'nickname' => 'Lyka',
            'work_type' => 'Part-time',
            'hourly_rate' => 75,
            'reminder_enabled' => true,
            'reminder_time' => '18:00',
        ])->assertOk()
            ->assertJsonPath('profile.user_id', 'WT-001')
            ->assertJsonPath('profile.nickname', 'Lyka');

        $profilePayload = $profileResponse->json('profile');
        $this->assertArrayHasKey('system_user_id', $profilePayload);
        $this->assertArrayNotHasKey('browser_id', $profilePayload);
        $this->assertArrayNotHasKey('browser_token_hash', $profilePayload);

        $profile = WorkProfile::firstOrFail();
        $browserHash = hash_hmac('sha256', $browserId, config('app.key') ?: config('app.name'));
        $this->assertSame($browserHash, $profile->browser_id);
        $this->assertSame($browserHash, $profile->browser_token_hash);
        $this->assertNotSame($browserId, $profile->browser_id);

        $this->postJson('/api/worktrack/entries', [
            'browser_id' => $browserId,
            'work_date' => '2026-10-02',
            'time_in' => '07:00',
            'time_out' => '18:00',
            'rate_type' => 'normal',
            'modifier' => 'holiday',
            'status' => 'work',
            'note' => 'Covered holiday shift',
        ])->assertOk()
            ->assertJsonPath('entry.normal_hours', 8)
            ->assertJsonPath('entry.extended_hours', 3)
            ->assertJsonPath('entry.total_hours', 11)
            ->assertJsonPath('entry.daily_pay', 1145.63);

        $entry = WorkEntry::firstOrFail();
        $this->assertSame($profile->id, $entry->profile_id);
        $this->assertSame($browserHash, $entry->browser_id);

        $this->getJson('/api/worktrack/state?browser_id='.$browserId)
            ->assertOk()
            ->assertJsonPath('profile.system_user_id', $profile->system_user_id)
            ->assertJsonCount(1, 'entries');

        $this->deleteJson('/api/worktrack/reset', [
            'browser_id' => $browserId,
        ])->assertOk()
            ->assertJsonPath('reset', true);

        $this->getJson('/api/worktrack/state?browser_id='.$browserId)
            ->assertOk()
            ->assertJsonPath('profile.system_user_id', $profile->system_user_id)
            ->assertJsonPath('profile.nickname', 'Lyka')
            ->assertJsonCount(0, 'entries');

        $this->assertDatabaseHas('work_profiles', [
            'id' => $profile->id,
            'nickname' => 'Lyka',
        ]);
    }

    public function test_reminder_due_is_a_snapshot_without_consuming_email_occurrence(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->travelTo(Carbon::create(2026, 10, 4, 17, 59, 0, 'Asia/Manila'));
        $browserId = 'reminder-browser-id';
        $this->updateLegacyProfile(['browser_id' => $browserId, 'user_id' => 'WT-REMINDER',
            'nickname' => 'Lyka', 'work_type' => 'Crew', 'hourly_rate' => 86.88])->assertOk();
        $profile = WorkProfile::firstOrFail();
        $profile->forceFill(['email' => 'lyka@example.com', 'email_verified_at' => now(),
            'reminder_enabled' => true, 'reminder_time' => '18:00', 'reminder_days' => [7]])->save();
        app(ReminderService::class)->ensureUpcoming($profile);
        $this->getJson('/api/worktrack/reminder/due?browser_id='.$browserId)->assertOk()
            ->assertJsonPath('due', false)->assertJsonPath('enabled', true);
        $this->travel(1)->minutes();
        $this->getJson('/api/worktrack/reminder/due?browser_id='.$browserId)->assertJsonPath('due', true);
        $this->getJson('/api/worktrack/reminder/due?browser_id='.$browserId)->assertJsonPath('due', true);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        $this->getJson('/api/worktrack/reminder/due?browser_id='.$browserId)->assertJsonPath('due', false);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
    }

    public function test_reminder_cannot_be_enabled_without_verified_email_but_can_be_turned_off(): void
    {
        $browserId = 'reminder-off-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-REMINDER-OFF',
            'nickname' => 'Lyka',
            'work_type' => 'Crew',
            'hourly_rate' => 86.88,
        ])->assertOk();

        $this->postJson('/api/worktrack/reminder', [
            'browser_id' => $browserId,
            'enabled' => true,
            'reminder_time' => '18:00',
            'days' => [1, 2, 3, 4, 5],
            'skip_if_complete' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/worktrack/reminder', [
            'browser_id' => $browserId,
            'enabled' => false,
        ])->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('profile.reminder_enabled', false);
    }

    public function test_verified_reminder_saves_its_message_and_days_without_work_log_exclusions(): void
    {
        $browserId = 'verified-reminder-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-VERIFIED-REMINDER',
            'nickname' => 'Lyka',
            'work_type' => 'Crew',
            'hourly_rate' => 86.88,
        ])->assertOk();
        WorkProfile::firstOrFail()->forceFill([
            'email' => 'lyka@example.com',
            'email_verified_at' => now(),
        ])->save();

        $this->postJson('/api/worktrack/reminder', [
            'browser_id' => $browserId,
            'enabled' => true,
            'reminder_time' => '18:30',
            'reminder_start_date' => '2026-10-24',
            'reminder_details' => 'Log today’s hours.',
            'days' => [1, 3, 5],
            'skip_if_complete' => true,
        ])->assertOk()
            ->assertJsonPath('profile.reminder_enabled', true)
            ->assertJsonPath('profile.reminder_time', '18:30')
            ->assertJsonPath('profile.reminder_start_date', '2026-10-24')
            ->assertJsonPath('profile.reminder_details', 'Log today’s hours.')
            ->assertJsonPath('profile.reminder_days', [1, 3, 5])
            ->assertJsonPath('profile.skip_if_complete', false)
            ->assertJsonPath('profile.email_verified', true);
    }

    public function test_email_verification_code_is_sent_hashed_and_bound_to_the_browser_profile(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $browserId = 'email-verify-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-EMAIL-VERIFY',
            'nickname' => 'Lyka',
            'work_type' => 'Crew',
            'hourly_rate' => 86.88,
        ])->assertOk();

        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => 'a-different-browser-identifier',
            'email' => 'lyka@example.com',
        ])->assertUnauthorized();

        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => 'Lyka@example.com',
        ])->assertOk()
            ->assertJsonPath('sent', true);

        $code = null;
        Mail::assertSent(WorkTrackEmailVerificationCode::class, function (WorkTrackEmailVerificationCode $mail) use (&$code): bool {
            $code = $mail->code;

            return $mail->hasTo('lyka@example.com');
        });
        $challenge = DB::table('work_reminder_email_verifications')->first();
        $this->assertNotNull($code);
        $this->assertNotSame($code, $challenge->code_hash);

        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => 'lyka@example.com',
        ])->assertTooManyRequests();
        $this->travel(61)->seconds();
        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => 'lyka@example.com',
        ])->assertOk()->assertJsonMissingPath('code');

        $sentCodes = [];
        Mail::assertSent(WorkTrackEmailVerificationCode::class, function (WorkTrackEmailVerificationCode $mail) use (&$sentCodes): bool {
            $sentCodes[] = $mail->code;

            return $mail->hasTo('lyka@example.com');
        });
        $this->assertCount(2, $sentCodes);
        $this->assertNotSame($sentCodes[0], $sentCodes[1]);
        $this->postJson('/api/worktrack/reminder/email/verify', [
            'browser_id' => $browserId,
            'email' => 'lyka@example.com',
            'code' => $sentCodes[0],
        ])->assertUnprocessable();
        $this->postJson('/api/worktrack/reminder/email/verify', [
            'browser_id' => $browserId,
            'email' => 'another@example.com',
            'code' => $sentCodes[1],
        ])->assertUnprocessable();

        $this->postJson('/api/worktrack/reminder/email/verify', [
            'browser_id' => $browserId,
            'email' => 'lyka@example.com',
            'code' => $sentCodes[1],
        ])->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('profile.email_verified', true);
        $this->assertDatabaseMissing('work_reminder_email_verifications', ['profile_id' => WorkProfile::firstOrFail()->id]);
    }

    public function test_failed_verification_delivery_keeps_the_previous_email_and_allows_retry(): void
    {
        config(['mail.default' => 'smtp']);
        $browserId = 'email-delivery-retry-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-EMAIL-RETRY',
            'nickname' => 'Mail retry',
            'work_type' => 'Crew',
            'hourly_rate' => 86.88,
        ])->assertOk();
        $profile = WorkProfile::firstOrFail();
        $profile->forceFill([
            'email' => 'previous@example.com',
            'email_verified_at' => now(),
            'reminder_enabled' => true,
        ])->save();

        $mailManager = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->with('replacement@example.com')
            ->andThrow(new \RuntimeException('Transport unavailable'));
        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => 'replacement@example.com',
        ])->assertStatus(503)->assertExactJson([
            'message' => 'The verification email could not be sent. Please try again shortly.',
        ]);
        $this->assertDatabaseMissing('work_reminder_email_verifications', ['profile_id' => $profile->id]);
        $this->assertSame('previous@example.com', $profile->fresh()->email);
        $this->assertNotNull($profile->fresh()->email_verified_at);
        $this->assertTrue($profile->fresh()->reminder_enabled);

        Mail::swap($mailManager);
        Mail::fake();
        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => 'replacement@example.com',
        ])->assertOk()->assertJsonPath('sent', true)->assertJsonMissingPath('code');
        Mail::assertSent(WorkTrackEmailVerificationCode::class, fn ($mail) => $mail->hasTo('replacement@example.com'));
    }

    public function test_email_verification_locks_after_five_wrong_attempts_and_codes_expire(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $browserId = 'email-attempt-limit-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-EMAIL-LIMIT',
            'nickname' => 'Lyka',
            'work_type' => 'Crew',
            'hourly_rate' => 86.88,
        ])->assertOk();

        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => 'limit@example.com',
        ])->assertOk();
        $validCode = null;
        Mail::assertSent(WorkTrackEmailVerificationCode::class, function (WorkTrackEmailVerificationCode $mail) use (&$validCode): bool {
            $validCode = $mail->code;

            return $mail->hasTo('limit@example.com');
        });
        $wrongCode = $validCode === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/worktrack/reminder/email/verify', [
                'browser_id' => $browserId,
                'email' => 'limit@example.com',
                'code' => $wrongCode,
            ])->assertUnprocessable();
        }

        $profileId = WorkProfile::firstOrFail()->id;
        $this->assertDatabaseHas('work_reminder_email_verifications', ['profile_id' => $profileId, 'attempts' => 5]);
        $this->postJson('/api/worktrack/reminder/email/verify', [
            'browser_id' => $browserId,
            'email' => 'limit@example.com',
            'code' => $validCode,
        ])->assertTooManyRequests();

        $this->travel(61)->seconds();
        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => 'limit@example.com',
        ])->assertOk();
        $codes = [];
        Mail::assertSent(WorkTrackEmailVerificationCode::class, function (WorkTrackEmailVerificationCode $mail) use (&$codes): bool {
            $codes[] = $mail->code;

            return $mail->hasTo('limit@example.com');
        });
        $freshCode = $codes[count($codes) - 1];
        $this->travel(5)->minutes();
        $this->postJson('/api/worktrack/reminder/email/verify', [
            'browser_id' => $browserId,
            'email' => 'limit@example.com',
            'code' => $freshCode,
        ])->assertUnprocessable();
    }

    public function test_scheduler_sends_once_per_occurrence_on_rest_days_and_honors_unsubscribe(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->travelTo(Carbon::create(2026, 10, 5, 10, 0, 0, 'Asia/Manila'));
        $browserId = 'scheduler-reminder-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-SCHEDULER',
            'nickname' => 'Lyka',
            'work_type' => 'Crew',
            'hourly_rate' => 86.88,
        ])->assertOk();
        $profile = WorkProfile::firstOrFail();
        $profile->forceFill([
            'email' => 'lyka@example.com',
            'email_verified_at' => now(),
            'reminder_enabled' => true,
            'reminder_time' => '09:00',
            'reminder_days' => [1],
            'skip_if_complete' => true,
        ])->save();

        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
        $reminderEmail = null;
        Mail::assertSent(WorkTrackDailyReminder::class, function (WorkTrackDailyReminder $mail) use (&$reminderEmail): bool {
            $reminderEmail = $mail;
            $html = $mail->render();

            return $mail->hasTo('lyka@example.com')
                && $mail->date === 'October 5, 2026'
                && str_contains($html, 'Hi Lyka')
                && str_contains($html, 'October 5, 2026')
                && str_contains($html, 'Open WorkTrack')
                && str_contains($html, 'turn off email reminders');
        });
        $this->assertDatabaseHas('work_reminder_deliveries', [
            'profile_id' => $profile->id,
            'reminder_date' => '2026-10-05',
            'status' => 'sent',
        ]);

        $profile->forceFill(['last_email_reminded_on' => null])->save();
        $profile->entries()->create([
            'browser_id' => $profile->browser_id,
            'work_date' => '2026-10-05',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'normal_hours' => 8,
            'extended_hours' => 1,
            'rate_type' => 'normal',
            'modifier' => 'none',
            'status' => 'work',
        ]);
        $this->travelTo(Carbon::create(2026, 10, 12, 10, 0, 0, 'Asia/Manila'));
        $profile->entries()->create([
            'browser_id' => $profile->browser_id,
            'work_date' => '2026-10-12',
            'normal_hours' => 0,
            'extended_hours' => 0,
            'rate_type' => 'normal',
            'modifier' => 'none',
            'status' => 'rest',
        ]);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 2);
        $this->get($reminderEmail->turnOffUrl)->assertOk()->assertSee('Turn off email reminders');
        $this->post($reminderEmail->turnOffUrl)->assertOk()->assertSee('Reminders turned off');
        $this->assertDatabaseHas('work_profiles', ['id' => $profile->id, 'reminder_enabled' => false]);

        $this->travelTo(Carbon::create(2026, 10, 19, 10, 0, 0, 'Asia/Manila'));
        $profile->entries()->create([
            'browser_id' => $profile->browser_id,
            'work_date' => '2026-10-19',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'normal_hours' => 8,
            'extended_hours' => 1,
            'rate_type' => 'normal',
            'modifier' => 'none',
            'status' => 'work',
        ]);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 2);
    }

    public function test_verified_reminder_is_sent_at_its_saved_philippine_date_and_time(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->travelTo(Carbon::create(2026, 10, 25, 13, 24, 0, 'Asia/Manila'));
        $browserId = 'timed-reminder-browser-id';
        $email = 'timed-reminder@example.com';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-TIMED-REMINDER',
            'nickname' => 'Timed reminder',
            'work_type' => 'Crew',
            'hourly_rate' => 94.88,
        ])->assertOk();
        $this->postJson('/api/worktrack/reminder/email/verify/send', [
            'browser_id' => $browserId,
            'email' => $email,
        ])->assertOk();
        $code = null;
        Mail::assertSent(WorkTrackEmailVerificationCode::class, function ($mail) use (&$code, $email): bool {
            $code = $mail->code;

            return $mail->hasTo($email);
        });
        $this->postJson('/api/worktrack/reminder/email/verify', [
            'browser_id' => $browserId,
            'email' => $email,
            'code' => $code,
        ])->assertOk()->assertJsonPath('profile.email_verified', true);
        $this->postJson('/api/worktrack/reminder', [
            'browser_id' => $browserId,
            'email' => $email,
            'enabled' => true,
            'reminder_time' => '13:25',
            'reminder_start_date' => '2026-10-25',
            'reminder_details' => 'Check your shift.',
            'days' => [7],
            'skip_if_complete' => false,
        ])->assertOk()->assertJsonPath('profile.reminder_enabled', true);
        $this->getJson('/api/worktrack/state?browser_id='.$browserId)
            ->assertOk()->assertJsonPath('profile.reminder_time', '13:25')
            ->assertJsonPath('profile.reminder_start_date', '2026-10-25');

        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertNotSent(WorkTrackDailyReminder::class);
        $this->travel(1)->minutes();
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
        Mail::assertSent(WorkTrackDailyReminder::class, fn ($mail) => $mail->hasTo($email)
            && $mail->date === 'October 25, 2026' && $mail->details === 'Check your shift.');
        $this->assertDatabaseHas('work_reminder_deliveries', [
            'profile_id' => WorkProfile::firstOrFail()->id,
            'reminder_date' => '2026-10-25',
            'status' => 'sent',
        ]);
        $this->artisan('worktrack:send-reminders')->assertExitCode(0);
        Mail::assertSentTimes(WorkTrackDailyReminder::class, 1);
    }

    public function test_custom_regular_hours_drive_normal_and_extended_hours(): void
    {
        $browserId = 'regular-hours-browser-id';

        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-HOURS',
            'nickname' => 'Lyka',
            'work_type' => 'Crew',
            'employee_status' => 'rotational',
            'default_regular_hours' => 10,
            'hourly_rate' => 75,
        ])->assertOk()
            ->assertJsonPath('profile.employee_status', 'rotational')
            ->assertJsonPath('profile.default_regular_hours', 10);

        $this->postJson('/api/worktrack/entries', [
            'browser_id' => $browserId,
            'work_date' => '2026-10-05',
            'time_in' => '07:00',
            'time_out' => '18:00',
            'regular_hours' => 10,
            'rate_type' => 'normal',
            'modifier' => 'holiday',
            'status' => 'work',
        ])->assertOk()
            ->assertJsonPath('entry.regular_hours', 10)
            ->assertJsonPath('entry.normal_hours', 10)
            ->assertJsonPath('entry.extended_hours', 1)
            ->assertJsonPath('entry.total_hours', 11)
            ->assertJsonPath('entry.daily_pay', 1096.88);
    }

    public function test_manual_normal_and_extended_hours_are_saved_and_paid(): void
    {
        $browserId = 'manual-hours-browser-id';

        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-MANUAL',
            'nickname' => 'Lyka',
            'work_type' => 'Crew',
            'employee_status' => 'part_time',
            'default_regular_hours' => 4,
            'hourly_rate' => 100,
        ])->assertOk();

        $this->postJson('/api/worktrack/entries', [
            'browser_id' => $browserId,
            'work_date' => '2026-10-06',
            'time_in' => '09:00',
            'time_out' => '17:00',
            'normal_hours' => 6,
            'extended_hours' => 2,
            'rate_type' => 'night',
            'modifier' => 'none',
            'status' => 'work',
        ])->assertOk()
            ->assertJsonPath('entry.normal_hours', 6)
            ->assertJsonPath('entry.extended_hours', 2)
            ->assertJsonPath('entry.total_hours', 8)
            ->assertJsonPath('entry.daily_pay', 935);
    }

    public function test_blank_manual_hours_use_work_times_and_reject_inconsistent_entries(): void
    {
        $browserId = 'time-only-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-TIME',
            'nickname' => 'Lia',
            'work_type' => 'Crew',
            'hourly_rate' => 100,
        ])->assertOk();

        $entry = [
            'browser_id' => $browserId,
            'work_date' => '2026-10-07',
            'time_in' => '09:00',
            'time_out' => '19:00',
            'normal_hours' => null,
            'extended_hours' => null,
            'rate_type' => 'normal',
            'modifier' => 'none',
            'status' => 'work',
        ];
        $this->postJson('/api/worktrack/entries', $entry)->assertOk()
            ->assertJsonPath('entry.normal_hours', 8)
            ->assertJsonPath('entry.extended_hours', 2)
            ->assertJsonPath('entry.daily_pay', 1050);

        $this->postJson('/api/worktrack/entries', array_replace($entry, [
            'normal_hours' => 11, 'extended_hours' => 0,
        ]))->assertUnprocessable()->assertJsonValidationErrors('normal_hours');
        $this->postJson('/api/worktrack/entries', array_replace($entry, [
            'time_out' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('time_out');
        $this->postJson('/api/worktrack/entries', array_replace($entry, [
            'time_out' => '09:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('time_out');
        $this->postJson('/api/worktrack/entries', array_replace($entry, [
            'time_in' => null, 'time_out' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('normal_hours');
    }

    public function test_saved_earnings_keep_the_rate_used_when_the_entry_was_saved(): void
    {
        $browserId = 'historical-rate-browser-id';
        $profile = [
            'browser_id' => $browserId,
            'user_id' => 'WT-RATE',
            'nickname' => 'Lia',
            'work_type' => 'Crew',
            'hourly_rate' => 100,
        ];
        $this->updateLegacyProfile($profile)->assertOk();
        $entry = [
            'browser_id' => $browserId,
            'work_date' => '2026-10-08',
            'normal_hours' => 8,
            'extended_hours' => 0,
            'rate_type' => 'normal',
            'modifier' => 'none',
            'status' => 'work',
        ];
        $this->postJson('/api/worktrack/entries', $entry)->assertOk()
            ->assertJsonPath('entry.daily_pay', 800)
            ->assertJsonPath('entry.hourly_rate_applied', 100);

        $this->updateLegacyProfile(array_replace($profile, ['hourly_rate' => 150]))->assertOk();
        $this->getJson('/api/worktrack/state?browser_id='.$browserId)->assertOk()
            ->assertJsonPath('profile.hourly_rate', 150)
            ->assertJsonPath('entries.0.daily_pay', 800)
            ->assertJsonPath('entries.0.hourly_rate_applied', 100);

        $this->postJson('/api/worktrack/entries', $entry)->assertOk()
            ->assertJsonPath('entry.daily_pay', 1200)
            ->assertJsonPath('entry.hourly_rate_applied', 150);
    }

    public function test_rate_snapshot_migration_preserves_existing_entries_at_the_current_rate(): void
    {
        $browserId = 'upgrade-rate-browser-id';
        $this->updateLegacyProfile([
            'browser_id' => $browserId,
            'user_id' => 'WT-UPGRADE',
            'nickname' => 'Lia',
            'work_type' => 'Crew',
            'hourly_rate' => 75,
        ])->assertOk();
        $this->postJson('/api/worktrack/entries', [
            'browser_id' => $browserId,
            'work_date' => '2026-10-09',
            'normal_hours' => 8,
            'rate_type' => 'normal',
            'modifier' => 'none',
            'status' => 'work',
        ])->assertOk();

        $migration = require database_path('migrations/2026_10_31_000000_snapshot_work_entry_hourly_rate.php');
        $migration->down();
        WorkProfile::query()->update(['hourly_rate' => 90]);
        $migration->up();

        $this->assertEquals(90, WorkEntry::firstOrFail()->hourly_rate_snapshot);
        $this->getJson('/api/worktrack/state?browser_id='.$browserId)->assertOk()
            ->assertJsonPath('entries.0.daily_pay', 720);
    }
}
