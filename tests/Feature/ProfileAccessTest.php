<?php

namespace Tests\Feature;

use App\Mail\WorkTrackAccessCode;
use App\Models\WorkEntry;
use App\Models\WorkProfile;
use App\Services\ProfileAccessService;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
        Carbon::setTestNow(Carbon::parse('2026-10-26 08:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_access_endpoints_work_with_real_throttle_and_session_middleware(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        $this->getJson('/api/worktrack/access/status')->assertOk()->assertJsonPath('authenticated', false);
        $this->send('register', 'middleware@example.com', ['profile' => $this->draft()])
            ->assertOk()->assertJsonPath('created', true);
        $this->getJson('/api/worktrack/access/status')->assertOk()->assertJsonPath('authenticated', true);
    }

    public function test_new_registration_creates_profile_without_email_verification(): void
    {
        $response = $this->send('register', 'new@example.com', ['profile' => $this->draft()])->assertOk();
        $response->assertJsonStructure(['created', 'profile_id', 'redirect', 'csrf_token']);
        $profile = WorkProfile::firstOrFail();
        $this->assertTrue($profile->access_enabled);
        $this->assertSame('new@example.com', $profile->access_email);
        $this->assertSame('sample-username', $profile->user_id);
        $this->assertNotNull($profile->email_verified_at);
        Mail::assertNothingSent();
        $this->getJson('/api/worktrack/access/status')->assertJsonPath('authenticated', true)->assertJsonPath('pending', null);
        $this->assertDatabaseCount('work_profile_access_challenges', 0);
        $this->assertDatabaseCount('work_profiles', 1);
    }

    public function test_a_different_session_cannot_redeem_a_known_challenge_and_correct_code(): void
    {
        $this->profile(true, 'session@example.com');
        $id = $this->send('login', 'session@example.com')->json('challenge_id');
        $code = $this->code();
        $this->app['session']->flush();
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $code])->assertUnprocessable();
        $this->assertDatabaseCount('work_profiles', 1);
    }

    public function test_nickname_only_registration_generates_identity_and_settings_preserve_it(): void
    {
        $draft = $this->draft();
        unset($draft['user_id'], $draft['default_regular_hours']);
        $this->send('register', 'nickname-only@example.com', ['profile' => $draft])
            ->assertOk()->assertJsonPath('created', true);
        $profile = WorkProfile::firstOrFail();
        $identity = $profile->system_user_id;
        $this->assertStringStartsWith('WTU-', $identity);
        $this->assertSame($identity, $profile->user_id);
        $this->assertEquals(4, $profile->default_regular_hours);
        $this->postJson('/api/worktrack/profile', [
            'nickname' => 'Updated nickname', 'work_type' => 'Crew',
            'employee_status' => 'part_time', 'hourly_rate' => 100,
        ], ['X-WorkTrack-Profile' => $identity])->assertOk();
        $profile->refresh();
        $this->assertSame($identity, $profile->system_user_id);
        $this->assertSame($identity, $profile->user_id);
        $this->assertSame('nickname-only@example.com', $profile->access_email);
        $this->assertSame('Updated nickname', $profile->nickname);
    }

    public function test_email_change_from_a_stale_profile_tab_is_rejected_before_sending(): void
    {
        $first = $this->profile(true, 'first-tab@example.com');
        $second = $this->profile(true, 'current-tab@example.com', 'current-profile-browser');
        $this->withSession(['worktrack_profile_id' => $second->id, 'worktrack_auth_version' => $second->auth_version]);
        $this->postJson('/api/worktrack/access/send', [
            'purpose' => 'email_change', 'email' => 'replacement@example.com',
        ], ['X-WorkTrack-Profile' => $first->system_user_id])->assertStatus(409);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('work_profile_access_challenges', 0);
        $this->assertSame('current-tab@example.com', $second->fresh()->access_email);
        $id = $this->postJson('/api/worktrack/access/send', [
            'purpose' => 'email_change', 'email' => 'replacement@example.com',
        ], ['X-WorkTrack-Profile' => $second->system_user_id])->assertOk()->json('challenge_id');
        Mail::assertSentTimes(WorkTrackAccessCode::class, 1);
        // A later account switch in this same browser cannot redeem the prior account's change.
        $this->withSession(['worktrack_profile_id' => $first->id, 'worktrack_auth_version' => $first->auth_version]);
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $this->code()])->assertUnauthorized();
        $this->assertSame('first-tab@example.com', $first->fresh()->access_email);
        $this->assertSame('current-tab@example.com', $second->fresh()->access_email);
    }

    public function test_existing_email_registration_does_not_start_otp_and_preserves_history(): void
    {
        $profile = $this->profile(true, 'existing@example.com');
        $entry = $this->entry($profile);
        $this->send('register', 'EXISTING@example.com', ['profile' => $this->draft()])
            ->assertUnprocessable();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('work_profile_access_challenges', 0);
        $this->assertDatabaseCount('work_profiles', 1);
        $this->assertSame('Original nickname', $profile->fresh()->nickname);
        $this->assertDatabaseHas('work_entries', ['id' => $entry->id, 'profile_id' => $profile->id]);

        $id = $this->send('login', 'EXISTING@example.com')
            ->assertOk()->assertJsonPath('purpose', 'login')->json('challenge_id');
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $this->code()])->assertOk();
        $this->assertDatabaseCount('work_profiles', 1);
        $this->assertSame('Original nickname', $profile->fresh()->nickname);
        $this->assertDatabaseHas('work_entries', ['id' => $entry->id, 'profile_id' => $profile->id]);
        $this->assertSame($profile->id, $this->app['session']->get('worktrack_profile_id'));
        $this->postJson('/api/worktrack/access/logout')->assertOk();
        $this->assertNull(app(ProfileAccessService::class)->current($this->sessionRequest(['browser_id' => 'legacy-browser-token']), true));
        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $id = $this->send('login', 'existing@example.com')->assertOk()->json('challenge_id');
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $this->code()])->assertOk();
        $this->assertSame($profile->id, $this->app['session']->get('worktrack_profile_id'));
    }

    public function test_legacy_link_requires_original_browser_and_preserves_permanent_id_and_entries(): void
    {
        $profile = $this->profile(false, 'old@example.com');
        $entry = $this->entry($profile);
        $this->send('login', 'old@example.com')->assertUnprocessable();
        $this->send('link', 'old@example.com')->assertUnauthorized();
        $id = $this->send('link', 'old@example.com', ['browser_id' => 'legacy-browser-token'])
            ->assertOk()->json('challenge_id');
        // The verification popup does not need the legacy token again; its challenge is session bound.
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $this->code()])->assertOk();
        $this->assertSame($profile->system_user_id, $profile->fresh()->system_user_id);
        $this->assertTrue($profile->fresh()->access_enabled);
        $this->assertDatabaseHas('work_entries', ['id' => $entry->id, 'profile_id' => $profile->id]);
        $freshSession = $this->sessionRequest(['browser_id' => 'legacy-browser-token']);
        $this->assertNull(app(ProfileAccessService::class)->current($freshSession, true));
    }

    public function test_conflicting_legacy_emails_never_merge_profiles(): void
    {
        $first = $this->profile(false, 'shared@example.com');
        $second = $this->profile(false, 'shared@example.com', 'second-browser-token');
        $this->send('login', 'shared@example.com')->assertUnprocessable();
        $this->send('link', 'shared@example.com', ['browser_id' => 'legacy-browser-token'])->assertUnprocessable();
        $this->assertDatabaseCount('work_profiles', 2);
        $this->assertFalse($first->fresh()->access_enabled);
        $this->assertFalse($second->fresh()->access_enabled);
    }

    public function test_email_change_commits_after_verification_and_invalidates_other_sessions(): void
    {
        $profile = $this->profile(true, 'before@example.com');
        $profile->forceFill(['reminder_enabled' => true, 'reminder_time' => '18:00', 'reminder_days' => [1, 2, 3, 4, 5, 6, 7],
            'reminder_details' => 'Bring the requirements.', 'reminder_start_date' => '2026-10-26'])->save();
        app(ReminderService::class)->ensureUpcoming($profile);
        $otherRequest = $this->sessionRequest();
        app(ProfileAccessService::class)->login($otherRequest, $profile);
        $this->withSession(['worktrack_profile_id' => $profile->id, 'worktrack_auth_version' => $profile->auth_version]);
        $id = $this->send('email_change', 'after@example.com')->assertOk()->json('challenge_id');
        $this->assertSame('before@example.com', $profile->fresh()->email);
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $this->code()])->assertOk();
        $this->assertSame('after@example.com', $profile->fresh()->access_email);
        $this->assertSame('after@example.com', $profile->fresh()->email);
        $this->assertNull(app(ProfileAccessService::class)->current($otherRequest));
        $this->getJson('/api/worktrack/access/status')->assertJsonPath('authenticated', true);
        $this->assertDatabaseHas('work_reminder_deliveries', ['profile_id' => $profile->id, 'status' => 'scheduled', 'recipient' => 'after@example.com']);
        $this->assertDatabaseMissing('work_reminder_deliveries', ['profile_id' => $profile->id, 'status' => 'scheduled', 'recipient' => 'before@example.com']);
    }

    public function test_resend_cooldown_is_global_and_old_code_is_invalidated(): void
    {
        $this->send('register', 'cooldown@example.com', ['profile' => $this->draft()])->assertJsonPath('created', true);
        $this->postJson('/api/worktrack/access/logout')->assertOk();
        $id = $this->send('login', 'cooldown@example.com')->assertOk()->json('challenge_id');
        $oldCode = $this->code();
        $this->send('login', 'cooldown@example.com', ['challenge_id' => $id])->assertTooManyRequests();
        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $newId = $this->send('login', 'cooldown@example.com', ['challenge_id' => $id])->assertOk()->json('challenge_id');
        $this->assertNotSame($id, $newId);
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $oldCode])->assertUnprocessable();
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $newId, 'code' => $this->code()])->assertOk();
        $this->app['session']->flush();
        $this->send('login', 'cooldown@example.com')->assertTooManyRequests();
    }

    public function test_five_attempt_limit_and_expiration_are_enforced(): void
    {
        $this->send('register', 'attempts@example.com', ['profile' => $this->draft()])->assertJsonPath('created', true);
        $this->postJson('/api/worktrack/access/logout')->assertOk();
        $id = $this->send('login', 'attempts@example.com')->json('challenge_id');
        $code = $this->code();
        $wrong = $code === '000000' ? '999999' : '000000';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $wrong])->assertStatus($attempt === 4 ? 429 : 422);
        }
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $code])->assertTooManyRequests();
        $this->assertDatabaseCount('work_profiles', 1);
        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $id = $this->send('login', 'attempts@example.com', ['challenge_id' => $id])->json('challenge_id');
        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $this->code()])->assertUnprocessable();
        $this->assertDatabaseCount('work_profiles', 1);
    }

    public function test_reminder_link_intent_cannot_authenticate_the_wrong_profile(): void
    {
        $target = $this->profile(true, 'target@example.com');
        $this->profile(true, 'wrong@example.com', 'wrong-profile-browser');
        $this->withSession(['worktrack_intended_profile' => $target->id]);
        $this->send('login', 'wrong@example.com')->assertUnprocessable();
        $id = $this->send('login', 'target@example.com')->assertOk()->json('challenge_id');
        $this->postJson('/api/worktrack/access/verify', ['challenge_id' => $id, 'code' => $this->code()])->assertOk();
        $this->assertSame($target->id, $this->app['session']->get('worktrack_profile_id'));
        $this->assertNull($this->app['session']->get('worktrack_intended_profile'));
    }

    public function test_new_registration_does_not_require_smtp(): void
    {
        $this->send('register', 'new-without-smtp@example.com', ['profile' => $this->draft()])
            ->assertOk()->assertJsonPath('created', true);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('work_profiles', 1);
        $this->assertDatabaseCount('work_profile_access_challenges', 0);
    }

    private function send(string $purpose, string $email, array $extra = [])
    {
        return $this->postJson('/api/worktrack/access/send', ['purpose' => $purpose, 'email' => $email] + $extra);
    }

    private function code(): string
    {
        return Mail::sent(WorkTrackAccessCode::class)->last()->code;
    }

    private function draft(): array
    {
        return ['user_id' => 'sample-username', 'nickname' => 'Test nickname', 'work_type' => 'Crew',
            'employee_status' => 'part_time', 'hourly_rate' => 94.53, 'default_regular_hours' => 4];
    }

    private function profile(bool $enabled, string $email, string $browser = 'legacy-browser-token'): WorkProfile
    {
        $hash = hash_hmac('sha256', $browser, config('app.key') ?: config('app.name'));

        return WorkProfile::create(['system_user_id' => 'WTU-'.Str::upper((string) Str::ulid()), 'browser_id' => $hash,
            'browser_token_hash' => $hash, 'user_id' => 'original', 'nickname' => 'Original nickname', 'work_type' => 'Crew',
            'employee_status' => 'part_time', 'hourly_rate' => 94.53, 'default_regular_hours' => 4,
            'email' => $email, 'email_verified_at' => now(), 'access_enabled' => $enabled,
            'access_email' => $enabled ? $email : null, 'auth_version' => $enabled ? 1 : 0]);
    }

    private function entry(WorkProfile $profile): WorkEntry
    {
        return WorkEntry::create(['profile_id' => $profile->id, 'browser_id' => $profile->browser_id, 'work_date' => '2026-10-24',
            'time_in' => '06:00', 'time_out' => '13:00', 'normal_hours' => 6, 'extended_hours' => 1, 'total_hours' => 7,
            'rate_type' => 'normal', 'modifier' => 'none', 'status' => 'work', 'note' => 'Original history']);
    }

    private function sessionRequest(array $data = []): Request
    {
        $request = Request::create('/api/worktrack/state', 'GET', $data);
        $session = new Store('worktrack-test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }
}
