<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('system_user_id', 40)->unique();
            $table->string('browser_id', 64)->unique();
            $table->string('browser_token_hash', 64)->unique();
            $table->string('user_id', 80);
            $table->string('nickname', 120);
            $table->string('work_type', 120);
            $table->decimal('hourly_rate', 10, 2);
            $table->boolean('reminder_enabled')->default(false);
            $table->time('reminder_time')->default('18:00');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_profiles');
    }
};
