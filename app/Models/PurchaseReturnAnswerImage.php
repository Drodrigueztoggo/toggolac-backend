<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnAnswerImage extends Model
{
    use HasFactory;
    protected $table = 'purchase_return_answer_images';

    protected $fillable = [
        'purchase_return_answer_id', 'image_answer'
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_answer) ? url('storage/' . $this->image_answer) : null;
    }
}
