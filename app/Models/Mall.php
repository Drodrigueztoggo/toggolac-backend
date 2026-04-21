<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mall extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'malls';

    //address, num_phone | estos campos faltan en la base de datos
    protected $fillable = ['name_mall', 'address', 'num_phone', 'country_id', 'state_id', 'city_id', 'postal_code', 'image_mall'];


    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_mall) ? url('storage/' . $this->image_mall) : null;
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id')->select('id', 'name');
    }
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id')->select('id', 'name');
    }
    
    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id')->select('id', 'name');
    }

    protected function serializeDate(DateTimeInterface $date) : string
    {
        return $date->format('Y-m-d H:i:s');
    }

}
