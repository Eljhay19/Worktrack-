<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkTrackAccessCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code, public readonly string $purpose) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WORKTRACK email verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.worktrack-access-code');
    }
}
