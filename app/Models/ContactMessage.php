<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'subject',
        'inquiry',
        'status',
        'admin_notes',
        'ip_address',
    ];

    protected $hidden = [
        'ip_address',
    ];
}
