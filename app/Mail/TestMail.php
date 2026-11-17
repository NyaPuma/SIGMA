<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $mailerName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('notifications.mail_test_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-mail',
            with: [
                'mailerName' => $this->mailerName !== '' ? $this->mailerName : (is_string(config('mail.default', 'log')) ? (string) config('mail.default', 'log') : 'log'),
            ],
        );
    }
}
