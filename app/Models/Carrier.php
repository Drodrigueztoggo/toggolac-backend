<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carrier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'carriers';

    protected $fillable = [
        'name',
        'country_id'
    ];

    protected $dates = ['deleted_at'];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id')->select('name', 'id');
    }

    public function shippingRates()
    {
        return $this->hasMany(CarrierShippingRate::class, 'carrier_id');
    }

}
