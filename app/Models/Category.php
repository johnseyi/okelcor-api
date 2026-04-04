<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'slug',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function translations()
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translation(string $locale = 'en')
    {
        return $this->hasOne(CategoryTranslation::class)->where('locale', $locale);
    }
}
