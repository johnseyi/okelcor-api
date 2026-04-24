<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'title',
        'subheadline',
        'button_text',
        'button_link',
        'image_url',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];
}
