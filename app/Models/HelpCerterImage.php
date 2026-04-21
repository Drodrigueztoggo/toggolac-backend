<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HelpCerterImage extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = "help_centers_images";

    protected $fillable = ['help_center_id', 'image_help'];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_help) ? url('storage/' . $this->image_help) : null;
    }

}
