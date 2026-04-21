<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalShopper extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'personal_shoper';

    protected $fillable = [
        'first_name',
        'phone_number',
        'country_id',
        'password',
        'last_name',
        'email',
        'department_id',
        'personal_id_number',
        'gender',
        'city_id',
        'driver_license_number',
        'date_of_birth',
        'image',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
