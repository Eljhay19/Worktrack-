<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_profiles', 'last_reminded_on')) {
                $table->date('last_reminded_on')->nullable()->after('reminder_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('work_profiles', 'last_reminded_on')) {
                $table->dropColumn('last_reminded_on');
            }
        });
    }
};
