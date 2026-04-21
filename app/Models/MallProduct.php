<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MallProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'mall_products';

    protected $fillable = [
        'mall_id', 'product_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function mall()
    {
        return $this->belongsTo(Mall::class);
    }
}
