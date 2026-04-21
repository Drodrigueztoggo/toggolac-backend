<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegisterUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $email;
    public $pass;

    public function __construct($name, $email, $pass)
    {
        $this->name = $name;
        $this->email = $email;
        $this->pass = $pass;
    }

    public function build()
    {
        return $this->view('emails.register_user')
            ->subject('Registro en Toggo Exitoso'); // Asunto del correo
    }
}