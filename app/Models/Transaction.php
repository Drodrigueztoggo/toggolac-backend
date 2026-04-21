<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'transactions';

    protected $fillable = [
        'order_token', 'user_id', 'purchase_order_id', 'payment_id', 'payment_method_type', 'merchant_checkout_token', 'status', 'amount', 'currency', 'created_date', 'approved_date', 'request'
    ];

    public function infoOrder()
    {
        return $this->belongsTo(PurchaseOrderHeader::class, 'purchase_order_id');
    }
}
