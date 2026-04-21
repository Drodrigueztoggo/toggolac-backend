<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'brands';

    protected $fillable = ['name_brand', 'country_id', 'state_id', 'city_id', 'description_brand',  'image_brand'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_brand) ? url('storage/' . $this->image_brand) : null;
    }

    public function categories()
    {
        return $this->hasManyThrough(
            Category::class,
            BrandCategory::class,
            'brand_id', // Foreign key on BrandCategory
            'id',       // Local key on Category
            'id',       // Local key on Brand
            'category_id' // Foreign key on Category
        )->select('categories.id', 'name_category AS name');
    }

    public function storeMall()
    {
        return $this->hasManyThrough(
            StoreMall::class,
            StoreBrand::class,
            'brand_id', // Foreign key on BrandCategory
            'id',       // Local key on Category
            'id',       // Local key on Brand
            'store_mall_id' // Foreign key on Category
        )->select('store_malls.id', 'store_malls.store AS name');
    }

    public function categoriesRelation()
    {
        return $this->hasMany(BrandCategory::class, 'brand_id');
    }

    public function storeBrandRelation()
    {
        return $this->hasMany(StoreBrand::class, 'brand_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id')->select('id', 'name');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id')->select('id', 'name');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id')->select('id', 'name');
    }

  
}
