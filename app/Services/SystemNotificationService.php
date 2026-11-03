<?php

namespace App\Services;

use App\Models\WorkProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SystemNotificationService
{
    /**
     * Store one concise, persistent setup confirmation for every profile.
     * The database key makes repeated state refreshes safe and idempotent.
     */
    public function ensureProfileReady(WorkProfile $profile): void
    {
        DB::table('worktrack_system_notifications')->insertOrIgnore([
            'profile_id' => $profile->id,
            'type' => 'profile_ready',
            'message' => 'Welcome to WorkTrack. Your profile is ready.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, array{id:int,type:string,message:string,created_at:string}> */
    public function recent(WorkProfile $profile): array
    {
        $this->ensureProfileReady($profile);

        return DB::table('worktrack_system_notifications')
            ->where('profile_id', $profile->id)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (object $notice): array => [
                'id' => $notice->id,
                'type' => $notice->type,
                'message' => $notice->message,
                'created_at' => Carbon::parse($notice->created_at, 'UTC')->toIso8601String(),
            ])
            ->all();
    }
}
