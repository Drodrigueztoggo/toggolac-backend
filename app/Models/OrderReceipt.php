<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable legal record created once per paid order.
 * NEVER soft-delete or hard-delete rows — required for chargeback defense
 * and financial record-keeping (minimum 7 years, recommended 10 years).
 */
class OrderReceipt extends Model
{
    // No soft deletes — receipts must be immutable
    protected $fillable = [
        'purchase_order_id',
        'invoice_number',
        'customer_name',
        'customer_email',
        'shipping_address',
        'payment_transaction_id',
        'payment_authorization_code',
        'payment_method_type',
        'payment_card_brand',
        'payment_last_4',
        'payment_amount',
        'payment_currency',
        'payment_approved_at',
        'customer_ip',
        'user_agent',
        'order_snapshot',
        'receipt_pdf_path',
        'pdf_generated_at',
    ];

    protected $casts = [
        'order_snapshot'      => 'array',
        'payment_approved_at' => 'datetime',
        'pdf_generated_at'    => 'datetime',
        'payment_amount'      => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderHeader::class, 'purchase_order_id');
    }
}
