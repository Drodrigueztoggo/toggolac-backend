<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $table = 'commissions';

    protected $fillable = ['reception_center_ok', 'user_id', 'purchase_order_id', 'amount', 'received_by_shopper', 'received_date'];

    public function personalShopper()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


}
