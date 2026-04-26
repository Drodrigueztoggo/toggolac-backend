<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminNewOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int     $orderId,
        public string  $invoiceNumber,
        public string  $customerName,
        public string  $customerEmail,
        public         $total,
        public array   $products,
        public ?string $shippingAddress = null,
        public ?string $paymentBrand    = null,
        public ?string $paymentLast4    = null,
    ) {}

    public function build(): static
    {
        return $this->view('emails.admin_new_order')
            ->subject('🛒 Nueva compra confirmada — ' . $this->invoiceNumber);
    }
}
