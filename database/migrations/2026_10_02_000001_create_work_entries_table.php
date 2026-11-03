<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('work_profiles')->cascadeOnDelete();
            $table->string('browser_id', 64)->index();
            $table->date('work_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('normal_hours', 5, 2)->default(0);
            $table->decimal('extended_hours', 5, 2)->default(0);
            $table->string('rate_type', 20)->default('normal');
            $table->string('modifier', 20)->default('none');
            $table->string('status', 20)->default('work');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'work_date']);
            $table->unique(['browser_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_entries');
    }
};
