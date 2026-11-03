<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            if (!Schema::hasColumn('work_profiles', 'email')) {
                $table->string('email', 254)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('work_profiles', 'last_email_reminded_on')) {
                $table->date('last_email_reminded_on')->nullable()->after('last_reminded_on');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('work_profiles', 'last_email_reminded_on')) {
                $table->dropColumn('last_email_reminded_on');
            }

            if (Schema::hasColumn('work_profiles', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
