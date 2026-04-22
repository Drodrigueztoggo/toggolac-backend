<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderDetail extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_order_details';

    protected $fillable = [
        'purchase_order_header_id',
        'product_id',
        // 'mall_id',
        'store_id',
        'price',
        'price_origin',
        'amount',
        'weight',
        'comment'
    ];

    protected $dates = ['deleted_at'];

    public function receptionCenterDetail()
    {
        return $this->belongsTo(ReceptionCenter::class, 'id', 'purchase_product_id');
    }

    public function store()
    {
        return $this->belongsTo(StoreMall::class, 'store_id')->select('id', 'store AS name', 'image_store', 'mall_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->select('id', 'name_product AS name', 'image_product', 'price_from', 'price_to', 'brand_id', 'supplier_url', 'supplier_cost');
    }

    public function images()
    {
        return $this->hasMany(PurchaseOrderDetailImage::class, 'purchase_order_detail_id')->select('id', 'purchase_order_detail_id', 'image_purchase');
    }

    public function purchaseOrderHeader()
    {
        return $this->belongsTo(PurchaseOrderHeader::class, 'purchase_order_header_id');
    }

    public function purchaseOrderDetailTax()
    {
        return $this->hasOne(PurchaseOrderDetailTax::class);
    }
}
