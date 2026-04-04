<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'media_type',
        'image_url',
        'video_url',
        'sort_order',
        'cta_primary_label',
        'cta_primary_href',
        'cta_secondary_label',
        'cta_secondary_href',
        'is_active',
    ];
}
