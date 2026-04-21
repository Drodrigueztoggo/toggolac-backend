<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreMall extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'store_malls';

    protected $fillable = ['quotes', 'store', 'address', 'num_phone', 'mall_id', 'image_store'];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_store) ? url('storage/' . $this->image_store) : null;
    }


    public function mall()
    {
        return $this->belongsTo(Mall::class, 'mall_id', 'id')->select('id', 'name_mall AS name');
    }
    public function mallInfo()
    {
        return $this->belongsTo(Mall::class, 'mall_id', 'id')->select('id', 'name_mall', 'address', 'num_phone', 'country_id', 'state_id', 'city_id', 'postal_code', 'image_mall');
    }
    
    protected function serializeDate(DateTimeInterface $date) : string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
