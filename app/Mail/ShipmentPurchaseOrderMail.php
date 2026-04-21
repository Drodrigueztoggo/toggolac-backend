<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShipmentPurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $numseguimiento;
    public $fechaenvio;
    public $ciudaddestino;
    public $direcciondestino;
    public $fechaestimada;
    public $destinationpostalcode;

    public function __construct(
        $name,
        $numseguimiento,
        $fechaenvio,
        $ciudaddestino,
        $direcciondestino,
        $fechaestimada,
        $destinationpostalcode,
    ) {
        $this->name = $name;
        $this->numseguimiento = $numseguimiento;
        $this->fechaenvio = $fechaenvio;
        $this->ciudaddestino = $ciudaddestino;
        $this->direcciondestino = $direcciondestino;
        $this->fechaestimada = $fechaestimada;
        $this->destinationpostalcode = $destinationpostalcode;
    }

    public function build()
    {
        return $this->view('emails.envio_orden_compra')
            ->subject('TOGGO Tu pedido está en camino'); // Asunto del correo
    }
}
