<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    use HasFactory;
    
    protected $table = 'transaction_logs';

    protected $fillable = [
        'transaction_id', 'previous_status', 'new_status', 'description', 'user_id'
    ];
}
