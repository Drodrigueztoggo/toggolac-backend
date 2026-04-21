<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'email',
        'source',
        'page_url',
        'accepted_terms',
        'consented_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_terms' => 'boolean',
        'consented_at' => 'datetime',
    ];
}
