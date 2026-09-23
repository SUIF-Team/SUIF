<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo con la clave de acceso generada en el pre-registro. Es el
 * respaldo de la clave que la persona ya ve en pantalla.
 */
class ClaveAcceso extends Mailable
{
    public function __construct(public readonly string $clave)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Clave de acceso para SUIF');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.clave-acceso');
    }
}
