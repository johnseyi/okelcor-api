<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleTranslation extends Model
{
    protected $fillable = [
        'article_id',
        'locale',
        'category',
        'title',
        'read_time',
        'summary',
        'body',
    ];

    protected $casts = [
        'body' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
