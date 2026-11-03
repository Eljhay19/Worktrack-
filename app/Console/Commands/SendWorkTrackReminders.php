<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendWorkTrackReminders extends Command
{
    protected $signature = 'worktrack:send-reminders';

    protected $description = 'Send due WORKTRACK reminder occurrences';

    public function handle(ReminderService $reminders): int
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->components->error('WORKTRACK reminders require a real email delivery transport.');

            return self::FAILURE;
        }
        $sent = $reminders->sendDue();
        $this->info("Sent {$sent} WORKTRACK reminder email(s).");

        return self::SUCCESS;
    }
}
