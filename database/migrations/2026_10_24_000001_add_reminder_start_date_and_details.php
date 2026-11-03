<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_profiles', 'reminder_start_date')) {
                $table->date('reminder_start_date')->nullable()->after('reminder_time');
            }
            if (!Schema::hasColumn('work_profiles', 'reminder_details')) {
                $table->string('reminder_details', 200)->nullable()->after('reminder_start_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            $columns = array_filter(['reminder_start_date', 'reminder_details'], fn (string $column): bool => Schema::hasColumn('work_profiles', $column));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
