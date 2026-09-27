<?php

namespace App\Mail\PermisosLaborales;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionPermisoLaboralMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $datos */
    public function __construct(
        public readonly string $tipo,
        public readonly array $datos,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->tipo === 'solicitud'
            ? "Nueva solicitud de permiso laboral: {$this->datos['empleado']}"
            : "Respuesta a la solicitud de permiso laboral: {$this->datos['empleado']}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.permisos-laborales.notificacion',
            with: [
                'tipo' => $this->tipo,
                'datos' => $this->datos,
            ],
        );
    }

    /** @return array<int, never> */
    public function attachments(): array
    {
        return [];
    }
}
