<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;

    protected $table = 'states';

    protected $fillable = ['name', 'country_id', 'country_code', 'country_name', 'state_code', 'type', 'latitude', 'longitude', 'is_main'];

}
