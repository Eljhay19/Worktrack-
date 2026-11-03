<?php

namespace App\Http\Controllers;

use App\Models\WorkProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkTrackReminderController extends Controller
{
    public function disable(Request $request, WorkProfile $profile): View
    {
        if ($request->isMethod('post')) {
            $profile->forceFill(['reminder_enabled' => false])->save();

            return view('worktrack.reminder-unsubscribe', ['disabled' => true]);
        }

        return view('worktrack.reminder-unsubscribe', ['disabled' => false]);
    }
}
