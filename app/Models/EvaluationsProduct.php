<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationsProduct extends Model
{
    use HasFactory;
    protected $table = 'evaluations_products';

    protected $fillable = [
         'evaluation_id', 'product_id', 'rating'
    ];
}
