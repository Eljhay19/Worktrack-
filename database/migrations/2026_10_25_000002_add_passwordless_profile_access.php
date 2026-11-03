<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
        Schema::table('work_profiles', function (Blueprint $table): void {
            // Existing profile IDs, browser keys and history are deliberately preserved.
            $table->boolean('access_enabled')->default(false);
            $table->string('access_email', 254)->nullable()->unique();
            $table->unsignedInteger('auth_version')->default(0);
        });

        Schema::create('work_profile_access_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('session_hash', 64)->index();
            $table->string('email', 254)->index();
            $table->string('purpose', 20);
            $table->foreignId('profile_id')->nullable()->constrained('work_profiles')->cascadeOnDelete();
            $table->unsignedInteger('auth_version')->nullable();
            $table->text('profile_payload')->nullable();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('last_sent_at');
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('work_profile_access_cooldowns', function (Blueprint $table): void {
            $table->string('email_hash', 64)->primary();
            $table->dateTime('last_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_profile_access_challenges');
        Schema::dropIfExists('work_profile_access_cooldowns');
        Schema::table('work_profiles', function (Blueprint $table): void {
            $table->dropUnique(['access_email']);
            $table->dropColumn(['access_enabled', 'access_email', 'auth_version']);
        });
    }
};
