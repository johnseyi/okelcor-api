<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];
}
