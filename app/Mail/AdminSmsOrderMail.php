<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminSmsOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int    $orderId,
        public string $invoiceNumber,
        public string $customerName,
        public        $total,
    ) {}

    public function build(): static
    {
        return $this->text('emails.admin_sms_order')
            ->subject('Nueva compra');
    }
}
