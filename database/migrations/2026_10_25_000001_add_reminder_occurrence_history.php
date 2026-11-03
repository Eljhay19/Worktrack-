<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_profiles', function (Blueprint $table): void {
            $table->string('reminder_schedule_mode', 16)->default('recurring');
            $table->dateTime('reminder_schedule_saved_at')->nullable();
        });
        Schema::table('work_reminder_deliveries', function (Blueprint $table): void {
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->string('recipient', 254)->nullable();
            $table->text('details')->nullable();
            $table->string('schedule_label')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->string('failure_message')->nullable();
        });
        DB::table('work_reminder_deliveries')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $profile = DB::table('work_profiles')->find($row->profile_id);
                $scheduled = Carbon::parse($row->reminder_date.' '.($profile->reminder_time ?: '18:00'), config('worktrack.reminder_timezone'))->utc();
                DB::table('work_reminder_deliveries')->where('id', $row->id)->update([
                    'scheduled_at' => $scheduled->format('Y-m-d H:i:s'),
                    'recipient' => $profile->email,
                    'details' => $profile->reminder_details,
                    'schedule_label' => 'Previously saved reminder',
                    'sent_at' => $row->sent_at ? Carbon::parse($row->sent_at, config('app.timezone'))->utc()->format('Y-m-d H:i:s') : null,
                    'status' => $row->status === 'pending' ? 'failed' : $row->status,
                    'failure_message' => $row->status === 'pending' ? 'Previous delivery could not be confirmed.' : null,
                ]);
            }
        });
        Schema::table('work_reminder_deliveries', function (Blueprint $table): void {
            $table->unique(['profile_id', 'scheduled_at']);
            $table->dropUnique(['profile_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        $duplicates = DB::table('work_reminder_deliveries')->select('profile_id', 'reminder_date')
            ->groupBy('profile_id', 'reminder_date')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Multiple occurrences share a date. Export or restore history before rolling back.');
        }
        Schema::table('work_reminder_deliveries', function (Blueprint $table): void {
            $table->unique(['profile_id', 'reminder_date']);
            $table->dropUnique(['profile_id', 'scheduled_at']);
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn(['scheduled_at', 'recipient', 'details', 'schedule_label', 'attempted_at', 'failed_at', 'failure_message']);
        });
        Schema::table('work_profiles', function (Blueprint $table): void {
            $table->dropColumn(['reminder_schedule_mode', 'reminder_schedule_saved_at']);
        });
    }
};
