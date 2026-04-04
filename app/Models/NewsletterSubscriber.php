<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'subscribed_at';

    protected $fillable = [
        'email',
        'locale',
        'is_confirmed',
        'token',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'is_confirmed'    => 'boolean',
        'subscribed_at'   => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];
}
