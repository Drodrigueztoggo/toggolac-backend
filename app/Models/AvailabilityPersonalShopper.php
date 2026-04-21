<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvailabilityPersonalShopper extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'availability_personal_shoppers';


    protected $fillable = [
        'user_id', 'date', 'day', 'start_time', 'end_time',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->select('id', 'country_id', 'city_id', 'image_user', 'name');
        
    }

}
