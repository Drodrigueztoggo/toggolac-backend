<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    protected $fillable = ['name', 'state_id', 'state_code', 'state_name', 'country_id', 'country_code', 'country_name', 'latitude', 'longitude', 'wikiDataId', 'is_main'];
}
