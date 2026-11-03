<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_profiles', 'employee_status')) {
                $table->string('employee_status', 30)->default('full_time')->after('work_type');
            }

            if (!Schema::hasColumn('work_profiles', 'default_regular_hours')) {
                $table->decimal('default_regular_hours', 5, 2)->default(8)->after('employee_status');
            }
        });

        Schema::table('work_entries', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_entries', 'regular_hours')) {
                $table->decimal('regular_hours', 5, 2)->default(8)->after('time_out');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('work_entries', 'regular_hours')) {
                $table->dropColumn('regular_hours');
            }
        });

        Schema::table('work_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('work_profiles', 'default_regular_hours')) {
                $table->dropColumn('default_regular_hours');
            }

            if (Schema::hasColumn('work_profiles', 'employee_status')) {
                $table->dropColumn('employee_status');
            }
        });
    }
};
