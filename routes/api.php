<?php

use App\Http\Controllers\WorkTrackApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('worktrack')
    ->name('worktrack.api.')
    ->middleware(['web', 'throttle:worktrack-api'])
    ->group(function (): void {
        require __DIR__.'/access.php';
        Route::get('/health', [WorkTrackApiController::class, 'health'])->name('health');
        Route::get('/state', [WorkTrackApiController::class, 'state'])->name('state');
        Route::get('/reminder/notifications', [WorkTrackApiController::class, 'reminderNotifications'])->name('reminder.notifications');
        Route::get('/reminder/due', [WorkTrackApiController::class, 'reminderDue'])->name('reminder.due');
        Route::post('/profile', [WorkTrackApiController::class, 'saveProfile'])->name('profile.save');
        Route::post('/reminder/email/verify/send', [WorkTrackApiController::class, 'sendReminderVerification'])->middleware('throttle:worktrack-email-code')->name('reminder.email.send');
        Route::post('/reminder/email/verify', [WorkTrackApiController::class, 'verifyReminderEmail'])->middleware('throttle:worktrack-email-code-check')->name('reminder.email.verify');
        Route::post('/reminder', [WorkTrackApiController::class, 'saveReminder'])->name('reminder.save');
        Route::post('/entries', [WorkTrackApiController::class, 'saveEntry'])->name('entries.save');
        Route::delete('/entries/{workDate}', [WorkTrackApiController::class, 'deleteEntry'])->name('entries.delete');
        Route::delete('/reset', [WorkTrackApiController::class, 'reset'])->name('reset');
    });
