<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderHeaderLog extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_headers_logs';

    protected $fillable = [
        'purchase_order_id',
        'previous_status_id',
        'status_id',
        'description',
        'user_id',
    ];

    public function status()
    {
        return $this->belongsTo(PurchaseOrderStatus::class, 'status_id')->select('id', 'name');
    }

    public function previousStatus()
    {
        return $this->belongsTo(PurchaseOrderStatus::class, 'previous_status_id')->select('id', 'name');
    }
}
