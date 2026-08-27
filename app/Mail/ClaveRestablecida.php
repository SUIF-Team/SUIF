<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo con la clave nueva tras un restablecimiento. A diferencia del
 * correo de alta, avisa que la clave anterior dejó de funcionar y qué
 * hacer si la persona no pidió el cambio.
 */
class ClaveRestablecida extends Mailable
{
    public function __construct(public readonly string $clave)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu clave de acceso SUIF fue restablecida');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.clave-restablecida');
    }
}
