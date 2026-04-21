<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationsPersonalShoper extends Model
{
    use HasFactory;
    protected $table = 'evaluations_personal_shopper';

    protected $fillable = [
         'evaluation_id', 'user_id', 'rating'
    ];
}
