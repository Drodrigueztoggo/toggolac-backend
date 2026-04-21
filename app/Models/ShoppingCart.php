<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingCart extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'shopping_carts';

    protected $fillable = ['is_purchase_order', 'user_id', 'product_id', 'quantity', 'comment', 'by', 'reserved_at'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->select('id', 'name_product AS name', 'image_product', 'price_from', 'price_to', 'mall_id', 'weight', 'brand_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->select('id', 'name', 'image_user', 'email');
    }

}
