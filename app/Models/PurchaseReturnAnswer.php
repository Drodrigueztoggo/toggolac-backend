<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnAnswer extends Model
{
    use HasFactory;
    protected $table = 'purchase_return_answers';

    protected $fillable = [
        'purchase_return_product_id', 'comment', 'user_id'
    ];
}
