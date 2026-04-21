<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreBrand extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stores_brands';


    protected $fillable = [
        'brand_id',
        'store_mall_id',
    ];

    public function name()
    {
        return $this->belongsTo(StoreMall::class, 'store_mall_id')->select('id', 'store AS name');
    }


    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

}
