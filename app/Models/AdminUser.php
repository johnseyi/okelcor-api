<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'password',
        'role',
        'last_login_at',
        'last_login_ip',
        'must_change_password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'last_login_at'       => 'datetime',
        'password'            => 'hashed',
        'must_change_password' => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function media()
    {
        return $this->hasMany(Media::class, 'uploaded_by');
    }
}
