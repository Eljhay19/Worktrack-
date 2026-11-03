<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_entries', function (Blueprint $table): void {
            $table->decimal('hourly_rate_snapshot', 8, 2)->nullable()->after('regular_hours');
        });

        // Existing entries have no historical rate; freeze the current profile rate for them.
        DB::table('work_entries')->select('id', 'profile_id')->orderBy('id')->chunkById(500, function ($entries): void {
            $rates = DB::table('work_profiles')
                ->whereIn('id', $entries->pluck('profile_id')->unique())
                ->pluck('hourly_rate', 'id');

            foreach ($entries as $entry) {
                DB::table('work_entries')->where('id', $entry->id)->update([
                    'hourly_rate_snapshot' => $rates[$entry->profile_id] ?? 0,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table): void {
            $table->dropColumn('hourly_rate_snapshot');
        });
    }
};
