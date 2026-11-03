<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkTrackDailyReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nickname,
        public readonly string $date,
        public readonly string $openUrl,
        public readonly string $turnOffUrl,
        public readonly string $details = '',
        public readonly ?string $scheduledFor = null,
        public readonly ?string $scheduleLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'WORKTRACK - Your scheduled reminder');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.worktrack-reminder');
    }
}
