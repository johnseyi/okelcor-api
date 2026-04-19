<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'customer_type',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'country',
        'company_name',
        'vat_number',
        'vat_verified',
        'industry',
        'email_verified_at',
        'must_reset_password',
        'is_active',
        'imported_from_wix',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'vat_verified'         => 'boolean',
        'must_reset_password'  => 'boolean',
        'is_active'            => 'boolean',
        'imported_from_wix'    => 'boolean',
    ];

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
