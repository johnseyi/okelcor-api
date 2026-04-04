<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlideTranslation extends Model
{
    protected $fillable = [
        'slide_id',
        'locale',
        'title',
        'subtitle',
        'cta_primary',
        'cta_secondary',
    ];

    public function slide()
    {
        return $this->belongsTo(HeroSlide::class, 'slide_id');
    }
}
