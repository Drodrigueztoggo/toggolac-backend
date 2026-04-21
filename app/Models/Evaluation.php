<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;
    protected $table = 'evaluations';

    protected $fillable = [
        'user_id', 'purchase_order_id', 'general_rating', 'delivery_time', 'product_quality', 'customer_service', 'store_navigation', 'payment_process', 'review'
    ];
}
