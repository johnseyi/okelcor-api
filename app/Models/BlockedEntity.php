<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedEntity extends Model
{
    protected $fillable = ['type', 'value', 'reason'];

    public static function isBlocked(string $type, string $value): bool
    {
        return static::where('type', $type)->where('value', $value)->exists();
    }
}
