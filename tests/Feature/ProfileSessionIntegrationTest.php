<?php

namespace Tests\Feature;

use App\Models\WorkProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileSessionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function profile(string $email): WorkProfile
    {
        $hash = hash('sha256', Str::random(64));

        return WorkProfile::create([
            'system_user_id' => 'WTU-'.Str::random(18), 'browser_id' => $hash, 'browser_token_hash' => $hash,
            'user_id' => 'username', 'nickname' => $email, 'work_type' => 'Crew', 'hourly_rate' => 90,
            'email' => $email, 'access_email' => $email, 'access_enabled' => true,
            'auth_version' => 1, 'email_verified_at' => now(),
        ]);
    }

    public function test_profile_cannot_be_created_without_email_verification(): void
    {
        $this->postJson('/api/worktrack/profile', ['browser_id' => 'new-browser-identifier',
            'user_id' => 'username', 'nickname' => 'User', 'work_type' => 'Crew', 'hourly_rate' => 90])
            ->assertUnauthorized();
        $this->assertDatabaseCount('work_profiles', 0);
    }

    public function test_session_owns_api_records_without_a_browser_token_and_ignores_forged_device_identity(): void
    {
        $a = $this->profile('first@example.com');
        $b = $this->profile('second@example.com');
        $this->withSession(['worktrack_profile_id' => $a->id, 'worktrack_auth_version' => 1]);
        $entry = ['work_date' => '2026-10-26', 'time_in' => '08:00', 'time_out' => '16:00',
            'rate_type' => 'normal', 'modifier' => 'none', 'status' => 'work', 'note' => 'Shared central record'];
        $this->postJson('/api/worktrack/entries', $entry)->assertOk();
        $this->getJson('/api/worktrack/state?browser_id='.$b->browser_id)->assertOk()
            ->assertJsonPath('profile.system_user_id', $a->system_user_id)->assertJsonCount(1, 'entries');
        $this->withSession(['worktrack_profile_id' => $b->id, 'worktrack_auth_version' => 1]);
        $this->getJson('/api/worktrack/state')->assertJsonPath('profile.system_user_id', $b->system_user_id)
            ->assertJsonCount(0, 'entries');
        $this->postJson('/api/worktrack/entries', $entry, ['X-WorkTrack-Profile' => $a->system_user_id])->assertStatus(409);
        $this->deleteJson('/api/worktrack/entries/2026-10-26')->assertOk();
        $this->assertDatabaseHas('work_entries', ['profile_id' => $a->id, 'note' => 'Shared central record']);
        // A second valid session for the same permanent profile sees the same database records.
        $this->withSession(['worktrack_profile_id' => $a->id, 'worktrack_auth_version' => 1]);
        $this->getJson('/api/worktrack/state')->assertJsonCount(1, 'entries');
        $this->postJson('/api/worktrack/entries', array_replace($entry, ['note' => 'Updated on another device']))->assertOk();
        $this->getJson('/api/worktrack/state')->assertJsonPath('entries.0.note', 'Updated on another device');
        $this->postJson('/api/worktrack/access/logout')->assertOk();
        $this->getJson('/api/worktrack/state?browser_id='.$a->browser_id)->assertJsonPath('profile', null);
        $this->postJson('/api/worktrack/entries', $entry)->assertUnauthorized();
        $this->getJson('/api/worktrack/state')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_reminder_link_is_an_intent_not_a_record_access_credential(): void
    {
        $a = $this->profile('first@example.com');
        $b = $this->profile('second@example.com');
        $link = URL::signedRoute('worktrack.reminders.open', ['profile' => $a->id]);
        $this->get($link)->assertRedirect('/worktrack/access');
        $this->getJson('/api/worktrack/state')->assertJsonPath('profile', null)->assertJsonPath('access_required', true);
        $this->withSession(['worktrack_profile_id' => $b->id, 'worktrack_auth_version' => 1]);
        $this->get($link)->assertRedirect('/worktrack/access');
        $this->getJson('/api/worktrack/state')->assertJsonPath('profile', null)->assertJsonPath('switch_required', true);
        $this->withSession(['worktrack_profile_id' => $a->id, 'worktrack_auth_version' => 1]);
        $this->get($link)->assertRedirect('/worktrack/calendar');
        $this->getJson('/api/worktrack/state')->assertJsonPath('profile.system_user_id', $a->system_user_id);
        $this->get('/worktrack/reminders/open/'.$b->id)->assertForbidden();
    }
}
