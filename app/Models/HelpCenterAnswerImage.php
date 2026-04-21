<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpCenterAnswerImage extends Model
{
    use HasFactory;

    protected $table = 'help_centers_answers_images';

    protected $fillable = [
        'help_center_answer_id', 'image_answer'
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_answer) ? url('storage/' . $this->image_answer) : null;
    }
}
