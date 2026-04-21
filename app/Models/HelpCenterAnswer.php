<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpCenterAnswer extends Model
{
    use HasFactory;
    protected $table = 'help_centers_answers';

    protected $fillable = [
        'help_center_id', 'comment', 'user_id'
    ];

}
