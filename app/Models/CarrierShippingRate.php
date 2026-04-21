<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarrierShippingRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'carrier_shipping_rates';

    protected $fillable = [
        'carrier_id',
        'country_id',
        'city_id',
        'min_weight',
        'max_weight',
        'price',
        'additional_charge',
        'group_rate'
    ];

    protected $dates = ['deleted_at'];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
