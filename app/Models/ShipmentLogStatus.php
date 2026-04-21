<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentLogStatus extends Model
{
    use HasFactory;

    protected $table = 'shipment_log_status';

    protected $fillable = [
        'shipment_id',
        'status_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->select('id', 'name');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function status()
    {
        return $this->belongsTo(ShipmentStatus::class, 'status_id')->select('id', 'name');
    }
    
}
