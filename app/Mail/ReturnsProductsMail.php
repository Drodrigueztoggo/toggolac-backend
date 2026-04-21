<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReturnsProductsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $comment;
    public $product;
    public $name;

    public function __construct($comment, $product, $name)
    {
        $this->name = $name;
        $this->comment = $comment;
        $this->product = $product;
    }

    public function build()
    {
        return $this->view('emails.return_products')
            ->subject('Devolución de producto'); // Asunto del correo
    }
}