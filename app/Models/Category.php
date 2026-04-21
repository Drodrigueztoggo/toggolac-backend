<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'categories';

     /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name_category',
        'description_category',
        'image_category',
        'created_at',
        'updated_at'
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_category) ? url('storage/' . $this->image_category) : null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at'
    ];


}
