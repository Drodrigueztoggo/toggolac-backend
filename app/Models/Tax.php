<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'taxes';

    protected $fillable = [
        'is_manual', 'product_dependency', 'tax_dependency_id', 'destination_country_id', 'name', 'type', 'value', 'if_price', 'value_conditional', 'user_id'
    ];
}
