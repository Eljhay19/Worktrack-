<?php

namespace Tests\Concerns;

use App\Models\WorkProfile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait CreatesLegacyProfiles
{
    // Fixtures model profiles saved before email access was introduced.
    private function updateLegacyProfile(array $data): TestResponse
    {
        $hash = hash_hmac('sha256', $data['browser_id'], config('app.key') ?: config('app.name'));
        WorkProfile::firstOrCreate(['browser_token_hash' => $hash], [
            'browser_id' => $hash, 'system_user_id' => 'WTU-TEST-'.Str::random(12),
            'user_id' => $data['user_id'], 'nickname' => $data['nickname'],
            'work_type' => $data['work_type'], 'hourly_rate' => $data['hourly_rate'],
            'employee_status' => $data['employee_status'] ?? 'full_time',
            'default_regular_hours' => $data['default_regular_hours'] ?? 8,
        ]);

        return $this->postJson('/api/worktrack/profile', $data);
    }
}
