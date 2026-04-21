<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceptionCenter extends Model
{
    use HasFactory;

    protected $table = 'reception_centers';

    protected $fillable = [
        'purchase_id', 'purchase_product_id', 'optimal_conditions_product', 'verified_quantity', 'conditions_brand', 'invoice_order', 'status', 'comment', 'user_id'
    ];

    protected $dates = ['created_at', 'updated_at'];


    public $timestamps = true;


    public function infoProduct()
    {
        return $this->belongsTo(PurchaseOrderDetail::class, 'purchase_product_id');
    }

}
