<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteLogStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quote_log_status';


    protected $fillable = [
        'quote_id',
        'status_id',
        'user_id',
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function status()
    {
        return $this->belongsTo(QuoteStatus::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
