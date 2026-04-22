<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'offers';

    protected $fillable = [
        'name',
        'description',
        'discount_percentage_from',
        'discount_percentage_to',
        'discount_price_from',
        'discount_price_to',
        'image_offert',
        'start_date',
        'end_date',
        'country_id',
        'mall_id',
        'store_mall_id',
        'brand_id',
        'product_id',
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_offert) ? url('storage/' . $this->image_offert) : null;
    }


    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id')->select('id', 'name');
    }

    public function mall()
    {
        return $this->belongsTo(Mall::class, 'mall_id')->select('id', 'name_mall AS name');
    }

    public function storeMall()
    {
        return $this->belongsTo(StoreMall::class, 'store_mall_id')->select('id', 'store AS name', 'image_store');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id')->select('id', 'name_brand AS name', 'image_brand');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->select('id', 'name_product AS name', 'name_product_en', 'image_product', 'brand_id', 'price_from', 'price_to');
    }

    protected function serializeDate(DateTimeInterface $date) : string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
