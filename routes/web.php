<?php

use App\Http\Controllers\WorkTrackController;
use App\Http\Controllers\WorkTrackReminderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/worktrack/calendar');

Route::get('/worktrack/access', [WorkTrackController::class, 'access'])->name('worktrack.access');
Route::get('/worktrack/access/verify', [WorkTrackController::class, 'access'])->name('worktrack.access.verify');
Route::get('/worktrack/settings/email', [WorkTrackController::class, 'emailSettings'])->name('worktrack.settings.email');
Route::get('/worktrack/settings/email/verify', [WorkTrackController::class, 'emailSettings'])->name('worktrack.settings.email.verify');
Route::get('/worktrack/reminders/open/{profile}', [WorkTrackController::class, 'openReminder'])->middleware('signed')->name('worktrack.reminders.open');

Route::get('/worktrack/calendar', [WorkTrackController::class, 'calendar'])->name('worktrack.calendar');
Route::get('/worktrack/calendar/records', [WorkTrackController::class, 'records'])->name('worktrack.calendar.records');
Route::get('/worktrack/calendar/reset', [WorkTrackController::class, 'resetConfirmation'])->name('worktrack.calendar.reset');
Route::get('/worktrack/calendar/entry/{date}/time-in', [WorkTrackController::class, 'entryTimeIn'])
    ->where('date', '\\d{4}-\\d{2}-\\d{2}')
    ->name('worktrack.calendar.entry.time-in');
Route::get('/worktrack/calendar/entry/{date}/time-out', [WorkTrackController::class, 'entryTimeOut'])
    ->where('date', '\\d{4}-\\d{2}-\\d{2}')
    ->name('worktrack.calendar.entry.time-out');
Route::get('/worktrack/calendar/entry/{date}', [WorkTrackController::class, 'entry'])
    ->where('date', '\\d{4}-\\d{2}-\\d{2}')
    ->name('worktrack.calendar.entry');
Route::get('/worktrack/calendar/{month}', [WorkTrackController::class, 'calendar'])
    ->where('month', '\\d{4}-\\d{2}')
    ->name('worktrack.calendar.month');
Route::get('/worktrack/settings', [WorkTrackController::class, 'settings'])->name('worktrack.settings');
Route::get('/worktrack/settings/reminder', [WorkTrackController::class, 'reminder'])->name('worktrack.settings.reminder');
Route::get('/worktrack/settings/reminder/email', [WorkTrackController::class, 'reminder'])->name('worktrack.settings.reminder.email');
Route::get('/worktrack/settings/reminder/verify', [WorkTrackController::class, 'reminder'])->name('worktrack.settings.reminder.verify');
Route::get('/worktrack/settings/reminder/schedule', [WorkTrackController::class, 'reminder'])->name('worktrack.settings.reminder.schedule');
Route::get('/worktrack/settings/reminder/time', [WorkTrackController::class, 'reminderTime'])->name('worktrack.settings.reminder.time');
Route::get('/worktrack/profile-setup', [WorkTrackController::class, 'profileSetup'])->name('worktrack.profile-setup');
Route::get('/worktrack/notifications', [WorkTrackController::class, 'notifications'])->name('worktrack.notifications');

Route::get('/worktrack/{section?}/{feature?}/{date?}', [WorkTrackController::class, 'index'])
    ->where([
        'section' => 'calendar|settings|profile-setup|notifications',
        'feature' => 'reminder|entry|records|reset|\\d{4}-\\d{2}',
        'date' => '\\d{4}-\\d{2}-\\d{2}',
    ])
    ->name('worktrack.index');

Route::middleware('signed')->match(['get', 'post'], '/worktrack/reminders/disable/{profile}', [WorkTrackReminderController::class, 'disable'])
    ->name('worktrack.reminders.disable');
