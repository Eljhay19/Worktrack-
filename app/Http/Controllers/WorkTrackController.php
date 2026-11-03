<?php

namespace App\Http\Controllers;

use App\Models\WorkProfile;
use App\Services\ProfileAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkTrackController extends Controller
{
    public function access(): View
    {
        return $this->index('access');
    }

    public function emailSettings(): View
    {
        return $this->index('settings', 'email');
    }

    public function openReminder(Request $request, WorkProfile $profile): RedirectResponse
    {
        $current = app(ProfileAccessService::class)->current($request, false);
        if ($current && $current->id === $profile->id) {
            $request->session()->forget('worktrack_intended_profile');

            return redirect()->route('worktrack.calendar');
        }
        $request->session()->put('worktrack_intended_profile', $profile->id);

        return redirect()->route('worktrack.access');
    }

    public function calendar(?string $month = null): View
    {
        return $this->index('calendar', $month);
    }

    public function records(): View
    {
        return $this->index('calendar', 'records');
    }

    public function resetConfirmation(): View
    {
        return $this->index('calendar', 'reset');
    }

    public function entry(string $date): View
    {
        return $this->index('calendar', 'entry', $date);
    }

    public function entryTimeIn(string $date): View
    {
        return $this->entry($date);
    }

    public function entryTimeOut(string $date): View
    {
        return $this->entry($date);
    }

    public function settings(): View
    {
        return $this->index('settings');
    }

    public function reminder(): View
    {
        return $this->index('settings', 'reminder');
    }

    public function reminderTime(): View
    {
        return $this->reminder();
    }

    public function profileSetup(): View
    {
        return $this->index('profile-setup');
    }

    public function notifications(): View
    {
        return $this->index('notifications');
    }

    public function index(string $section = 'calendar', ?string $feature = null, ?string $date = null): View
    {
        return view('worktrack.index', [
            'section' => $section,
            'feature' => $feature,
            'date' => $date,
        ]);
    }
}
