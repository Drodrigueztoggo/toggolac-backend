<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $table = 'products';

    protected $fillable = [
        'name_product',
        'name_product_en',
        'description_product',
        'description_product_en',
        'price_from',
        'price_to',
        'weight',
        'brand_id',
        // 'mall_id',
        'image_product',
        'supplier_url',
        'supplier_cost',
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        if (!isset($this->image_product) || $this->image_product === '') return '';
        if (str_starts_with($this->image_product, 'http')) return $this->image_product;
        return url('storage/' . $this->image_product);
    }

    public function evaluations()
    {
        return $this->hasMany(EvaluationsProduct::class, 'product_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id')->select('id', 'name_brand', 'description_brand', 'image_brand');
    }

    public function mall()
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'categories_products', 'product_id', 'category_id')
            ->select('categories.id', 'name_category AS name')->withTimestamps();
    }

    public function storeProducts()
    {

        return $this->belongsToMany(StoreMall::class, 'store_products', 'product_id', 'store_mall_id')
            ->select('store_malls.id', 'store AS name', 'mall_id', 'image_store')->whereNull('store_products.deleted_at')->withTimestamps();
    }

    public function categoriesRelation()
    {
        return $this->belongsToMany(Category::class, 'categories_products', 'product_id', 'category_id');
    }

    public function categoriesProduct()
    {
        return $this->hasMany(CategorieProduct::class, 'product_id');
    }



    public function storeProductsRelation()
    {
        return $this->belongsToMany(StoreProduct::class, 'store_products', 'product_id', 'store_mall_id')
            ->withTimestamps();
    }

    public function mallProductsRelation()
    {
        return $this->belongsToMany(MallProduct::class, 'mall_products', 'product_id', 'mall_id')
            ->withTimestamps();
    }

    public function mallProducts()
    {
        return $this->belongsToMany(Mall::class, 'mall_products', 'product_id', 'mall_id')
            ->select('malls.id', 'name_mall AS name', 'country_id', 'state_id', 'city_id', 'image_mall')->withTimestamps();
    }

    public function cities()
    {
        return $this->belongsToMany(Mall::class, 'mall_products', 'product_id', 'mall_id')
            ->select('cities.id', 'cities.name AS name')->join('cities', 'malls.city_id', 'cities.id');
    }



    public function shouldBeSearchable(): bool
    {
        return !empty($this->image_product) && is_null($this->deleted_at);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'                  => $this->id,
            'status'              => 'available',
            'name_product'        => $this->name_product,
            'name_product_en'     => $this->name_product_en,
            'description_product' => $this->description_product,
            'price_from'          => (float) $this->price_from,
            'price_to'            => (float) $this->price_to,
            'category'            => $this->categoriesRelation->pluck('name_category')->toArray(),
            'image'               => $this->image,
        ];
    }

}
