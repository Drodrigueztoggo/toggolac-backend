<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'store_products';

    protected $fillable = [
        'store_mall_id', 'product_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function storeMall()
    {
        return $this->belongsTo(StoreMall::class);
    }
}
