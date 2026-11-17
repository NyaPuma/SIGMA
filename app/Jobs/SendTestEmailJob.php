<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\TestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendTestEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of send attempts.
     */
    public int $tries = 3;

    /**
     * Wait time (in seconds) between attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    /**
     * Maximum execution time for the job in seconds.
     */
    public int $timeout = 30;

    public function __construct(
        public readonly string $email,
        public readonly string $name,
    ) {}

    public function handle(): void
    {
        $mailerConfig = config('services.custom.notification.mailer');
        $mailerConfig = is_string($mailerConfig) ? $mailerConfig : null;

        $mailClient = $mailerConfig ? Mail::mailer($mailerConfig) : Mail::mailer();

        $defaultMailer = config('mail.default', 'log');
        $defaultMailer = is_string($defaultMailer) ? $defaultMailer : 'log';
        $mailClient->to($this->email)->send(new TestMail($this->name, $mailerConfig ?? $defaultMailer));

        Log::info('Test email sent successfully via queue', [
            'email' => $this->email,
            'mailer' => $mailerConfig ?? config('mail.default'),
        ]);
    }

    /**
     * Logs the failure when the email could not be sent after all attempts.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to send test email via queue', [
            'email' => $this->email,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
