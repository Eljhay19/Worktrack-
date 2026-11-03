<?php

use App\Http\Controllers\WorkTrackAccessController;
use Illuminate\Support\Facades\Route;

Route::post('/access/send', [WorkTrackAccessController::class, 'send'])
    ->middleware('throttle:5,10')->name('access.send');
Route::post('/access/verify', [WorkTrackAccessController::class, 'verify'])
    ->middleware('throttle:12,1')->name('access.verify');
Route::get('/access/status', [WorkTrackAccessController::class, 'status'])->name('access.status');
Route::post('/access/logout', [WorkTrackAccessController::class, 'logout'])->name('access.logout');
