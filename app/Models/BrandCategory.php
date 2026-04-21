<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'brands_categories';

    protected $fillable = ['brand_id', 'category_id'];


    public function name()
    {
        return $this->belongsTo(Category::class, 'category_id')->select('id', 'name_category AS name');
    }


    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
