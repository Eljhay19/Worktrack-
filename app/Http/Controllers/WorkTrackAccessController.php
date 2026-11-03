<?php

namespace App\Http\Controllers;

use App\Mail\WorkTrackAccessCode;
use App\Models\WorkProfile;
use App\Services\ProfileAccessService;
use App\Services\ReminderService;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorkTrackAccessController extends Controller
{
    public function send(Request $request, ProfileAccessService $access): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', Rule::in(['register', 'login', 'link', 'email_change'])],
            'email' => ['required', 'email', 'max:254'],
            'browser_id' => ['nullable', 'string', 'min:12', 'max:180'],
            'challenge_id' => ['nullable', 'uuid'],
        ]);
        $email = Str::lower(trim($data['email']));
        $purpose = $data['purpose'];
        $current = $access->current($request);
        $draft = null;
        $subject = null;
        $sessionHash = $this->sessionHash($request);
        if (! empty($data['challenge_id'])) {
            $previous = DB::table('work_profile_access_challenges')->where('id', $data['challenge_id'])
                ->where('session_hash', $sessionHash)->whereNull('consumed_at')->first();
            if (! $previous || $previous->email !== $email || $previous->purpose !== $purpose) {
                throw ValidationException::withMessages(['email' => 'This verification request is no longer available. Start again.']);
            }
            $draft = $previous->profile_payload ? Crypt::decryptString($previous->profile_payload) : null;
            if ($purpose === 'link' && ! $current && (int) $request->session()->get('worktrack_link_profile_id') === (int) $previous->profile_id) {
                $current = WorkProfile::whereKey($previous->profile_id)->where('access_enabled', false)->first();
            }
        }

        if (in_array($purpose, ['link', 'email_change'], true)) {
            $expectedProfile = $request->header('X-WorkTrack-Profile');
            if ($expectedProfile && (! $current || ! hash_equals($current->system_user_id, $expectedProfile))) {
                abort(409, 'The active profile changed. Reload before changing its email.');
            }
            if (! $current || ($purpose === 'email_change' && ! $current->access_enabled)
                || ($purpose === 'link' && $current->access_enabled)) {
                return response()->json(['message' => 'Open the original profile or verify access before linking or changing its email.'], 401);
            }
            $subject = $current;
            if ($purpose === 'link') {
                $request->session()->put('worktrack_link_profile_id', $current->id);
            }
            if ($this->emailOwnedByAnotherProfile($email, $current->id)) {
                throw ValidationException::withMessages(['email' => 'That email is linked to another profile. Records cannot be merged or overwritten.']);
            }
        } else {
            $subject = WorkProfile::where('access_enabled', true)->where('access_email', $email)->first();
            if ($subject) {
                // Registering with an existing email always verifies the original profile.
                $purpose = 'login';
            } elseif (WorkProfile::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Open the original browser, then choose Link Email / Enable Device Access in Settings. Your existing history will be preserved.',
                ]);
            } elseif ($purpose === 'login') {
                throw ValidationException::withMessages(['email' => 'No enabled profile is linked to this email. Link it from the original browser, or create a profile.']);
            } else {
                if ($current) {
                    throw ValidationException::withMessages(['email' => 'This browser already has a profile. Link its email in Settings or log out before creating another profile.']);
                }
                $draft ??= json_encode($this->validatedDraft($request), JSON_THROW_ON_ERROR);
            }
        }
        $intended = $request->session()->get('worktrack_intended_profile');
        if ($intended && (! $subject || (int) $subject->id !== (int) $intended)) {
            throw ValidationException::withMessages(['email' => 'Verify the email belonging to the profile in your reminder link.']);
        }

        // A brand-new Profile Setup does not need an email challenge. OTP is
        // reserved for reopening an existing profile on another device.
        if ($purpose === 'register' && ! $subject && ! $current && $draft) {
            return DB::transaction(function () use ($request, $access, $email, $draft): JsonResponse {
                if ($this->emailOwnedByAnotherProfile($email, null)) {
                    throw ValidationException::withMessages(['email' => 'That email is already linked to a profile. Access it with the verification code instead.']);
                }

                $systemUserId = 'WTU-'.Str::upper((string) Str::ulid());
                $browserHash = hash_hmac('sha256', Str::random(64), (string) config('app.key'));
                $profileData = json_decode($draft, true, flags: JSON_THROW_ON_ERROR);
                $profileData['user_id'] ??= $systemUserId;
                $profile = WorkProfile::create($profileData + [
                    'system_user_id' => $systemUserId,
                    'browser_id' => $browserHash,
                    'browser_token_hash' => $browserHash,
                    'access_enabled' => true,
                    'access_email' => $email,
                    'auth_version' => 1,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'last_seen_at' => now(),
                ]);
                app(SystemNotificationService::class)->ensureProfileReady($profile);
                $access->login($request, $profile);

                return response()->json([
                    'created' => true,
                    'profile_id' => $profile->system_user_id,
                    'redirect' => '/worktrack/calendar',
                    'csrf_token' => $request->session()->token(),
                ]);
            }, 3);
        }

        if (in_array(config('mail.default'), ['log', 'array'], true) && ! app()->environment('testing')) {
            return response()->json(['message' => 'Email delivery is unavailable. Please try again later.'], 503);
        }

        return DB::transaction(function () use ($request, $email, $purpose, $subject, $draft, $sessionHash): JsonResponse {
            $emailHash = hash_hmac('sha256', $email, (string) config('app.key'));
            DB::table('work_profile_access_cooldowns')->insertOrIgnore(['email_hash' => $emailHash]);
            $cooldown = DB::table('work_profile_access_cooldowns')->where('email_hash', $emailHash)->lockForUpdate()->first();
            $now = Carbon::now('UTC');
            if ($cooldown->last_sent_at && Carbon::parse($cooldown->last_sent_at, 'UTC')->addSeconds(60)->isAfter($now)) {
                return response()->json([
                    'message' => 'Please wait 60 seconds before requesting another code.',
                    'resend_at' => Carbon::parse($cooldown->last_sent_at, 'UTC')->addSeconds(60)->toIso8601String(),
                ], 429);
            }
            $id = (string) Str::uuid();
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            try {
                Mail::to($email)->send(new WorkTrackAccessCode($code, $purpose));
            } catch (Throwable) {
                // No code, credentials, email contents or SMTP diagnostic is logged or returned.
                return response()->json(['message' => 'The verification email could not be sent. Please try again shortly.'], 503);
            }
            DB::table('work_profile_access_challenges')->where('email', $email)->whereNull('consumed_at')
                ->update(['consumed_at' => $now, 'updated_at' => $now]);
            DB::table('work_profile_access_challenges')->insert([
                'id' => $id, 'session_hash' => $sessionHash, 'email' => $email, 'purpose' => $purpose,
                'profile_id' => $subject?->id, 'auth_version' => $subject?->auth_version,
                'profile_payload' => $draft ? Crypt::encryptString($draft) : null,
                'code_hash' => $this->codeHash($id, $code), 'attempts' => 0,
                'expires_at' => $now->copy()->addMinutes(5), 'last_sent_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('work_profile_access_cooldowns')->where('email_hash', $emailHash)->update(['last_sent_at' => $now]);
            $request->session()->put('worktrack_access_challenge', $id);

            return response()->json(['sent' => true] + $this->metadata(DB::table('work_profile_access_challenges')->where('id', $id)->first()));
        }, 3);
    }

    public function verify(Request $request, ProfileAccessService $access): JsonResponse
    {
        $data = $request->validate(['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'digits:6']]);
        $sessionHash = $this->sessionHash($request);

        return DB::transaction(function () use ($request, $access, $data, $sessionHash): JsonResponse {
            $candidate = DB::table('work_profile_access_challenges')->where('id', $data['challenge_id'])
                ->where('session_hash', $sessionHash)->first();
            if ($candidate) {
                // Keep the same lock order as sending/resending a code for this email.
                DB::table('work_profile_access_cooldowns')
                    ->where('email_hash', hash_hmac('sha256', $candidate->email, (string) config('app.key')))
                    ->lockForUpdate()->first();
            }
            $challenge = DB::table('work_profile_access_challenges')->where('id', $data['challenge_id'])
                ->where('session_hash', $sessionHash)->lockForUpdate()->first();
            if (! $challenge || $challenge->consumed_at || Carbon::now('UTC')->greaterThanOrEqualTo(Carbon::parse($challenge->expires_at, 'UTC'))) {
                return response()->json(['message' => 'This code expired or was already used. Request a new code.'], 422);
            }
            if ($challenge->attempts >= 5) {
                return response()->json(['message' => 'Too many incorrect codes. Request a new code.'], 429);
            }
            if (! hash_equals($challenge->code_hash, $this->codeHash($challenge->id, $data['code']))) {
                $attempts = $challenge->attempts + 1;
                DB::table('work_profile_access_challenges')->where('id', $challenge->id)->update(['attempts' => $attempts, 'updated_at' => now()]);

                return response()->json(['message' => $attempts >= 5 ? 'Too many incorrect codes. Request a new code.' : 'That verification code is incorrect.',
                    'attempts_remaining' => max(0, 5 - $attempts)], $attempts >= 5 ? 429 : 422);
            }

            $profile = $challenge->profile_id ? WorkProfile::whereKey($challenge->profile_id)->lockForUpdate()->first() : null;
            if ($challenge->profile_id && (! $profile || (int) $profile->auth_version !== (int) $challenge->auth_version)) {
                return response()->json(['message' => 'This profile changed while verifying. Request a new code.'], 422);
            }
            $intended = $request->session()->get('worktrack_intended_profile');
            if ($intended && (! $profile || (int) $profile->id !== (int) $intended)) {
                return response()->json(['message' => 'Verify the profile belonging to your reminder link.'], 403);
            }
            if (in_array($challenge->purpose, ['link', 'email_change'], true)) {
                $current = $access->current($request);
                $legacyLink = $challenge->purpose === 'link' && ! $profile?->access_enabled
                    && (int) $request->session()->get('worktrack_link_profile_id') === (int) $profile?->id;
                if (! $legacyLink && (! $current || $current->id !== $profile?->id || ($challenge->purpose === 'email_change' && ! $current->access_enabled))) {
                    return response()->json(['message' => 'Your profile session is no longer valid. Verify access again.'], 401);
                }
            }
            if ($this->emailOwnedByAnotherProfile($challenge->email, $profile?->id)) {
                return response()->json(['message' => 'That email belongs to another profile. No records have been changed.'], 409);
            }
            if ($challenge->purpose === 'register') {
                $draft = json_decode(Crypt::decryptString($challenge->profile_payload), true, flags: JSON_THROW_ON_ERROR);
                $systemUserId = 'WTU-'.Str::upper((string) Str::ulid());
                $draft['user_id'] ??= $systemUserId;
                $browserHash = hash_hmac('sha256', Str::random(64), (string) config('app.key'));
                $profile = WorkProfile::create($draft + [
                    'system_user_id' => $systemUserId,
                    'browser_id' => $browserHash, 'browser_token_hash' => $browserHash,
                    'access_enabled' => true, 'access_email' => $challenge->email, 'auth_version' => 1,
                    'email' => $challenge->email, 'email_verified_at' => now(), 'last_seen_at' => now(),
                ]);
                app(SystemNotificationService::class)->ensureProfileReady($profile);
            } elseif (in_array($challenge->purpose, ['link', 'email_change'], true)) {
                $profile->forceFill([
                    'access_enabled' => true, 'access_email' => $challenge->email,
                    'email' => $challenge->email, 'email_verified_at' => now(),
                    'auth_version' => (int) $profile->auth_version + 1,
                ])->save();
                DB::table('work_reminder_deliveries')->where('profile_id', $profile->id)->where('status', 'scheduled')
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
                app(ReminderService::class)->ensureUpcoming($profile);
            } elseif (! $profile?->access_enabled || $profile->access_email !== $challenge->email) {
                return response()->json(['message' => 'This profile needs to be linked from the original browser.'], 422);
            }

            DB::table('work_profile_access_challenges')->where('id', $challenge->id)->update([
                'consumed_at' => Carbon::now('UTC'), 'code_hash' => hash_hmac('sha256', Str::random(64), (string) config('app.key')),
                'profile_payload' => null, 'updated_at' => now(),
            ]);
            $access->login($request, $profile);

            return response()->json(['verified' => true, 'csrf_token' => $request->session()->token(),
                'profile_id' => $profile->system_user_id, 'redirect' => '/worktrack/calendar']);
        }, 3);
    }

    public function status(Request $request, ProfileAccessService $access): JsonResponse
    {
        $profile = $access->current($request);
        $id = $request->session()->get('worktrack_access_challenge');
        $challenge = $id ? DB::table('work_profile_access_challenges')->where('id', $id)
            ->where('session_hash', $this->sessionHash($request))->whereNull('consumed_at')->first() : null;

        return response()->json([
            'authenticated' => (bool) ($profile?->access_enabled), 'legacy_profile' => (bool) ($profile && ! $profile->access_enabled),
            'intended' => (bool) $request->session()->get('worktrack_intended_profile'),
            'pending' => $challenge ? $this->metadata($challenge) : null,
            'csrf_token' => $request->session()->token(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('worktrack_logged_out', true);

        return response()->json(['logged_out' => true, 'csrf_token' => $request->session()->token()]);
    }

    private function sessionHash(Request $request): string
    {
        if (! $request->session()->has('worktrack_access_nonce')) {
            $request->session()->put('worktrack_access_nonce', Str::random(64));
        }

        return hash_hmac('sha256', $request->session()->get('worktrack_access_nonce'), (string) config('app.key'));
    }

    private function codeHash(string $id, string $code): string
    {
        return hash_hmac('sha256', $id.'|'.$code, (string) config('app.key'));
    }

    private function metadata(object $challenge): array
    {
        return [
            'challenge_id' => $challenge->id, 'purpose' => $challenge->purpose, 'email' => $challenge->email,
            'expires_at' => Carbon::parse($challenge->expires_at, 'UTC')->toIso8601String(),
            'resend_at' => Carbon::parse($challenge->last_sent_at, 'UTC')->addSeconds(60)->toIso8601String(),
            'expired' => Carbon::now('UTC')->greaterThanOrEqualTo(Carbon::parse($challenge->expires_at, 'UTC')),
            'attempts_remaining' => max(0, 5 - (int) $challenge->attempts),
        ];
    }

    private function emailOwnedByAnotherProfile(string $email, ?int $except): bool
    {
        return WorkProfile::where(function ($query) use ($email): void {
            $query->where('access_email', $email)->orWhereRaw('LOWER(email) = ?', [$email]);
        })->when($except, fn ($query) => $query->where('id', '!=', $except))->exists();
    }

    private function validatedDraft(Request $request): array
    {
        $data = $request->validate([
            'profile' => ['required', 'array:user_id,nickname,work_type,employee_status,hourly_rate,default_regular_hours'],
            'profile.user_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'profile.nickname' => ['required', 'string', 'max:120'],
            'profile.work_type' => ['required', 'string', 'max:120'],
            'profile.employee_status' => ['required', Rule::in(['full_time', 'part_time', 'rotational', 'custom'])],
            'profile.hourly_rate' => ['required', 'numeric', 'min:0', 'max:99999'],
            'profile.default_regular_hours' => ['sometimes', 'numeric', 'min:1', 'max:24'],
        ])['profile'];
        $data['default_regular_hours'] ??= match ($data['employee_status']) {
            'part_time' => 4,
            'rotational' => 10,
            default => 8,
        };

        return $data;
    }
}
