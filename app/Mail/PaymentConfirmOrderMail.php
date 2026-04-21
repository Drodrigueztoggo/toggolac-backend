<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string       $name               Customer full name
     * @param  array        $products           purchase_order_details with nested product
     * @param  string|float $total              Formatted order total
     * @param  string|null  $invoiceNumber      e.g. TGG-2026-000042
     * @param  string|null  $orderToken         Public order reference
     * @param  string|null  $orderDate          Human-readable date (locale-aware)
     * @param  string|null  $destinationAddress Full shipping address string
     * @param  array        $taxes              Tax lines already filtered and labelled
     * @param  string       $language           'en' or 'es'
     * @param  string|null  $paymentLast4       Last 4 digits of card used
     * @param  string|null  $paymentBrand       Card brand (VISA, MASTERCARD, AMEX…) or method type
     */
    public function __construct(
        public string  $name,
        public array   $products,
        public         $total,
        public ?string $invoiceNumber      = null,
        public ?string $orderToken         = null,
        public ?string $orderDate          = null,
        public ?string $destinationAddress = null,
        public array   $taxes              = [],
        public string  $language           = 'en',
        public ?string $paymentLast4       = null,
        public ?string $paymentBrand       = null,
    ) {}

    public function build(): static
    {
        $subject = $this->language === 'es'
            ? 'Toggolac — Confirmación y recibo de tu pedido'
            : 'Toggolac — Order Confirmation & Receipt';

        return $this->view('emails.payment_confirm_order')->subject($subject);
    }
}
