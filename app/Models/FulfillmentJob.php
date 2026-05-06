<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentJob extends Model
{
    protected $table = 'fulfillment_jobs';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'supplier_url',
        'gateway',
        'shipping_mode',
        'shipping_name',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'status',
        'lots_order_id',
        'notes',
        'attempts',
        'last_attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'last_attempted_at' => 'datetime',
        'completed_at'      => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(PurchaseOrderHeader::class, 'order_id');
    }
}
