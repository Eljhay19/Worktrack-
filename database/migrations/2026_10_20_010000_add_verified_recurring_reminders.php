<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_profiles', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (!Schema::hasColumn('work_profiles', 'reminder_days')) {
                $table->json('reminder_days')->nullable()->after('reminder_time');
            }
            if (!Schema::hasColumn('work_profiles', 'skip_if_complete')) {
                $table->boolean('skip_if_complete')->default(true)->after('reminder_days');
            }
        });

        Schema::create('work_reminder_email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->unique()->constrained('work_profiles')->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('code_hash', 64);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('work_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('work_profiles')->cascadeOnDelete();
            $table->date('reminder_date');
            $table->string('status', 16)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['profile_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_reminder_deliveries');
        Schema::dropIfExists('work_reminder_email_verifications');
        Schema::table('work_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'email_verified_at',
                'reminder_days',
                'skip_if_complete',
            ]);
        });
    }
};
