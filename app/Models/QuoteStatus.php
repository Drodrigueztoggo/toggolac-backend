<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quote_status';


    protected $fillable = [
        'name'
    ];
}
