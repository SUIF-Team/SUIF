<?php

namespace App\Mail;

use App\Servicios\GestionClaves;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo con el enlace para generar una clave nueva. No lleva ninguna clave:
 * la vigente sigue sirviendo hasta que la persona use el enlace.
 */
class EnlaceRecuperacion extends Mailable
{
    public function __construct(public readonly string $enlace)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recupera tu clave de acceso SUIF');
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.enlace-recuperacion',
            with: ['vigencia' => GestionClaves::VIGENCIA_ENLACE_MINUTOS],
        );
    }
}
