<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetailTax extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_details_taxes';

    protected $fillable = [
        'purchase_order_detail_id',
        'taxes'
    ];

    public function purchaseOrderDetail()
    {
        return $this->belongsTo(PurchaseOrderDetail::class);
    }
}
