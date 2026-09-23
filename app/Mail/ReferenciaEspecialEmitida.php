<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de que la DEC ya emitió la referencia especial con la que un tercero
 * paga la certificación de varios participantes. Va a cada participante: la
 * referencia la comparten todos y con ella entran a su siguiente paso.
 */
class ReferenciaEspecialEmitida extends Mailable
{
    public function __construct(
        public readonly string $referencia,
        public readonly string $razonSocial,
        public readonly int $participantes
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu referencia especial de pago SUIF');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.referencia-especial-emitida');
    }
}
