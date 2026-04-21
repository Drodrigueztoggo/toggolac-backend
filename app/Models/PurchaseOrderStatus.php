<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderStatus extends Model
{
    use HasFactory;
    protected $table = 'purchase_order_status';

    protected $fillable = [
        'name'
    ];
}
