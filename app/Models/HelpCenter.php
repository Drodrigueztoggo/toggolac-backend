<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HelpCenter extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'help_centers';

    protected $fillable = [
        'purchase_id',
        'request_type',
        'reason',
        'product',
        'personal_shopper_id',
        'petition',
        'status',
        'user_id'
        // 'image',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];


    public function images()
    {
        return $this->hasMany(HelpCerterImage::class, 'help_center_id')->select('id',
        'help_center_id',
        'image_help');
    }

    public function personalShopper()
    {
        return $this->belongsTo(User::class, 'personal_shopper_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
