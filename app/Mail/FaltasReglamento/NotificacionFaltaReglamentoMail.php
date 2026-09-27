<?php

namespace App\Mail\FaltasReglamento;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionFaltaReglamentoMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $datos */
    public function __construct(
        public readonly string $tipo,
        public readonly array $datos,
    ) {}

    public function envelope(): Envelope
    {
        $empleado = data_get($this->datos, 'empleado.nombre', 'empleado');
        $subject = $this->tipo === 'solicitud'
            ? "Nuevo reporte de falta al reglamento: {$empleado}"
            : "Resolución del reporte de falta al reglamento: {$empleado}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.faltas-reglamento.notificacion',
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
