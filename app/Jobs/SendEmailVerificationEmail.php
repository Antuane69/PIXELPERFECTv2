<?php

namespace App\Jobs;

use App\Mail\EmailVerificationMail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailVerificationEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $verificationUrl,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send(new EmailVerificationMail(
            name: $this->name,
            verificationUrl: $this->verificationUrl,
        ));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudo enviar el correo de verificación.', [
            'email' => $this->email,
            'error' => $exception?->getMessage(),
        ]);
    }
}
