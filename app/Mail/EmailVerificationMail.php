<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $verificationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirma tu correo electrónico | Pixel Perfect',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
            with: [
                'name' => $this->name,
                'verificationUrl' => $this->verificationUrl,
                'expirationMinutes' => (int) config('auth.verification.expire', 60),
            ],
        );
    }

    /**
     * @return array<int, never>
     */
    public function attachments(): array
    {
        return [];
    }
}
