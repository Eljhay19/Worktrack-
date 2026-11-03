<?php

namespace App\Services;

use App\Models\WorkProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileAccessService
{
    public function current(Request $request, bool $allowLegacy = true): ?WorkProfile
    {
        $session = $request->session();
        if ($id = $session->get('worktrack_profile_id')) {
            $profile = WorkProfile::find($id);
            if ($profile && $profile->access_enabled && $profile->email_verified_at && $profile->access_email
                && $profile->access_email === Str::lower(trim((string) $profile->email))
                && (int) $profile->auth_version === (int) $session->get('worktrack_auth_version', -1)) {
                return $profile;
            }
            $session->forget(['worktrack_profile_id', 'worktrack_auth_version']);
            $session->put('worktrack_logged_out', true);

            return null;
        }
        if (! $allowLegacy || $session->get('worktrack_logged_out', false)) {
            return null;
        }
        $token = $request->input('browser_id');
        if (! is_string($token) || strlen($token) < 12 || strlen($token) > 180) {
            return null;
        }
        $hash = hash_hmac('sha256', $token, config('app.key') ?: config('app.name'));

        return WorkProfile::where('access_enabled', false)->where(function ($query) use ($hash): void {
            $query->where('browser_token_hash', $hash)->orWhere('browser_id', $hash);
        })->first();
    }

    public function login(Request $request, WorkProfile $profile): void
    {
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
        $request->session()->put([
            'worktrack_profile_id' => $profile->id,
            'worktrack_auth_version' => (int) $profile->auth_version,
            'worktrack_logged_out' => false,
        ]);
        $request->session()->forget(['worktrack_access_challenge', 'worktrack_access_nonce', 'worktrack_intended_profile', 'worktrack_link_profile_id']);
    }
}
