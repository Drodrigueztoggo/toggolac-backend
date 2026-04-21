<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'quotes';


    protected $fillable = [
        'availability_personal_shopper_id', 'store_mall_id', 'country_id', 'state_id', 'city_id', 'user_id', 'create_user_id', 'date', 'start_time', 'end_time', 'quantity_products', 'comment', 'status_id'
    ];

    protected $dates = [
        'deleted_at', 'duration'
    ];

    protected $appends = ['duration'];


    public function getDurationAttribute()
    {

        $tasaProductosPorHora = 3;

        return ceil($this->quantity_products / $tasaProductosPorHora);
    }

    protected $primaryKey = 'id'; // Nombre de la clave primaria si es diferente al nombre predeterminado


    public function store()
    {
        return $this->belongsTo(StoreMall::class, 'store_mall_id')->select('id', 'store AS name', 'mall_id');
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
    public function status()
    {
        return $this->belongsTo(QuoteStatus::class, 'status_id')->select('id', 'name');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id')->select('id', 'name');
    }
    public function infoPersonalShopper()
    {
        return $this->belongsTo(User::class, 'availability_personal_shopper_id')->select('id', 'name');
    }

}
