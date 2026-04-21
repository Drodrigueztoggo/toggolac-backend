<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationDevice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'notification_device';

    protected $fillable = [
        'id',
        'divice_token',
        'user_id',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
