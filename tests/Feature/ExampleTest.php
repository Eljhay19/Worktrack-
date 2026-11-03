<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_home_redirects_to_worktrack_calendar(): void
    {
        $this->get('/')
            ->assertRedirect('/worktrack/calendar');
    }

    public function test_worktrack_settings_page_renders(): void
    {
        $this->get('/worktrack/settings')
            ->assertOk()
            ->assertSee('WORKTRACK')
            ->assertSee('Daily Reminder')
            ->assertSee('Enable email reminder')
            ->assertSee('Philippine Time (UTC+8)')
            ->assertSee('Custom date (once)')
            ->assertSee('Reminder details');
    }

    public function test_worktrack_sections_and_overlays_have_named_refreshable_routes(): void
    {
        $routes = [
            ['worktrack.calendar', [], '/worktrack/calendar'],
            ['worktrack.calendar.month', ['month' => '2026-10'], '/worktrack/calendar/2026-10'],
            ['worktrack.calendar.records', [], '/worktrack/calendar/records'],
            ['worktrack.calendar.reset', [], '/worktrack/calendar/reset'],
            ['worktrack.calendar.entry', ['date' => '2026-10-24'], '/worktrack/calendar/entry/2026-10-24'],
            ['worktrack.calendar.entry.time-in', ['date' => '2026-10-24'], '/worktrack/calendar/entry/2026-10-24/time-in'],
            ['worktrack.calendar.entry.time-out', ['date' => '2026-10-24'], '/worktrack/calendar/entry/2026-10-24/time-out'],
            ['worktrack.settings', [], '/worktrack/settings'],
            ['worktrack.settings.reminder', [], '/worktrack/settings/reminder'],
            ['worktrack.settings.reminder.email', [], '/worktrack/settings/reminder/email'],
            ['worktrack.settings.reminder.verify', [], '/worktrack/settings/reminder/verify'],
            ['worktrack.settings.reminder.schedule', [], '/worktrack/settings/reminder/schedule'],
            ['worktrack.settings.reminder.time', [], '/worktrack/settings/reminder/time'],
            ['worktrack.profile-setup', [], '/worktrack/profile-setup'],
            ['worktrack.notifications', [], '/worktrack/notifications'],
        ];

        foreach ($routes as [$name, $parameters, $expectedPath]) {
            $path = route($name, $parameters, false);
            $this->assertSame($expectedPath, $path, "Unexpected URL for route [{$name}].");
            $this->get($path)->assertOk();
        }
    }
}
