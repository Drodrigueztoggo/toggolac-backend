<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodeBondRegistry extends Mailable
{
    use Queueable, SerializesModels;

    private $name;
    private $bond;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $bond)
    {
        $this->name = $name;
        $this->bond = $bond;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: '¡Gracias por elegirnos! Aquí tienes un descuento para tu primera compra 🎁',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'emails.code_bond_registry',
            with: [
                'name' => $this->name,
                'bond' => $this->bond,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
