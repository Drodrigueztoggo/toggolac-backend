<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $table = 'shipments';

    protected $fillable = [
        'purchase_order_id',
        'carrier_id',
        'origin_address',
        'destination_address',
        'shipment_status_id',
        'customer_name_lastname',
        'origin_country_id',
        'origin_state_id',
        'origin_city_id',
        'destination_country_id',
        'destination_state_id',
        'destination_city_id',
        'tracking_number',
        'date',
        'origin_postal_code',
        'destination_postal_code',
        'pounds_weight',
        'total_shipping_cost',
        'current_cost',
        'create_user_id',
    ];

    public function orderDetail()
    {
        return $this->belongsTo(PurchaseOrderHeader::class, 'purchase_order_id')->select('id', 'client_id');
    }

    public function originCountry()
    {
        return $this->belongsTo(Country::class, 'origin_country_id')->select('id', 'name');
    }

    public function originState()
    {
        return $this->belongsTo(State::class, 'origin_state_id')->select('id', 'name');
    }

    public function originCity()
    {
        return $this->belongsTo(City::class, 'origin_city_id')->select('id', 'name');
    }

    public function destinationCountry()
    {
        return $this->belongsTo(Country::class, 'destination_country_id')->select('id', 'name');
    }

    public function destinationState()
    {
        return $this->belongsTo(State::class, 'destination_state_id')->select('id', 'name');
    }

    public function destinationCity()
    {
        return $this->belongsTo(City::class, 'destination_city_id')->select('id', 'name');
    }

    public function createUser()
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function shipmentStatus()
    {
        return $this->belongsTo(ShipmentStatus::class, 'shipment_status_id')->select('id', 'name');
    }

    public function shipmentLogStatuses()
    {
        return $this->hasMany(ShipmentLogStatus::class, 'shipment_id');
    }
}
