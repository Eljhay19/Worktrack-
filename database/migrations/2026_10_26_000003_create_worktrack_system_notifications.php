<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worktrack_system_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('work_profiles')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('message', 255);
            $table->timestamps();

            $table->unique(['profile_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worktrack_system_notifications');
    }
};
