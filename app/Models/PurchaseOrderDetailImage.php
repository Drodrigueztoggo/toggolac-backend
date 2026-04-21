<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetailImage extends Model
{

    protected $table = 'purchase_order_details_images';

    protected $fillable = [
        'purchase_order_detail_id',
        'image_purchase'
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_purchase) ? url('storage/' . $this->image_purchase) : null;
    }


}
