<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FetEngine extends Model
{
    protected $fillable = [
        'category',
        'manufacturer',
        'model_series',
        'engine_code',
        'displacement',
        'fuel_type',
        'fet_model',
        'notes',
    ];
}
