<?php

use App\Services\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(ReminderService::class)->sendDue())
    ->name('worktrack:send-reminders')
    ->everySecond()
    ->withoutOverlapping(2)
    ->when(fn (): bool => ! in_array(config('mail.default'), ['log', 'array'], true));

Schedule::command('worktrack:backup')
    ->dailyAt('02:15')
    ->timezone(config('worktrack.reminder_timezone'))
    ->withoutOverlapping();
