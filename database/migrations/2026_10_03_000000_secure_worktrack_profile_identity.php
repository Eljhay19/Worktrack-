<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('work_profiles')) {
            return;
        }

        Schema::table('work_profiles', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_profiles', 'system_user_id')) {
                $table->string('system_user_id', 40)->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('work_profiles', 'browser_token_hash')) {
                $table->string('browser_token_hash', 64)->nullable()->unique()->after('system_user_id');
            }

            if (!Schema::hasColumn('work_profiles', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('reminder_time');
            }
        });

        if (Schema::hasTable('work_entries') && !Schema::hasColumn('work_entries', 'profile_id')) {
            Schema::table('work_entries', function (Blueprint $table): void {
                $table->foreignId('profile_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('work_profiles')
                    ->cascadeOnDelete();
            });
        }

        DB::table('work_profiles')
            ->orderBy('id')
            ->get()
            ->each(function (object $profile): void {
                $legacyBrowserToken = (string) ($profile->browser_id ?? '');
                $browserHash = $this->hashBrowserToken($legacyBrowserToken);
                $systemUserId = $profile->system_user_id ?: $this->makeSystemUserId((int) $profile->id);

                if (Schema::hasTable('work_entries')) {
                    DB::table('work_entries')
                        ->where('browser_id', $legacyBrowserToken)
                        ->update([
                            'profile_id' => $profile->id,
                            'browser_id' => $browserHash,
                        ]);
                }

                DB::table('work_profiles')
                    ->where('id', $profile->id)
                    ->update([
                        'system_user_id' => $systemUserId,
                        'browser_token_hash' => $browserHash,
                        'browser_id' => $browserHash,
                    ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('work_entries') && Schema::hasColumn('work_entries', 'profile_id')) {
            Schema::table('work_entries', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('profile_id');
            });
        }

        if (Schema::hasTable('work_profiles')) {
            Schema::table('work_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('work_profiles', 'last_seen_at')) {
                    $table->dropColumn('last_seen_at');
                }

                if (Schema::hasColumn('work_profiles', 'browser_token_hash')) {
                    $table->dropColumn('browser_token_hash');
                }

                if (Schema::hasColumn('work_profiles', 'system_user_id')) {
                    $table->dropColumn('system_user_id');
                }
            });
        }
    }

    private function hashBrowserToken(string $browserToken): string
    {
        return hash_hmac('sha256', $browserToken, config('app.key') ?: config('app.name'));
    }

    private function makeSystemUserId(int $profileId): string
    {
        return 'WTU-'.str_pad((string) $profileId, 6, '0', STR_PAD_LEFT);
    }
};
