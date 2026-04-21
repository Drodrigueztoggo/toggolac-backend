<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HelpCenterCommnetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $comment;
    public $id;
    public $name;

    public function __construct($comment, $id, $name)
    {
        $this->name = $name;
        $this->comment = $comment;
        $this->id = $id;
    }

    public function build()
    {
        return $this->view('emails.help_center_comment')
            ->subject('TOGGO PQR: #' . $this->id); // Asunto del correo
    }
}