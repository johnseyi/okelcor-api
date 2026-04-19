<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'ref_number',
        'full_name',
        'company_name',
        'email',
        'phone',
        'country',
        'business_type',
        'tyre_category',
        'brand_preference',
        'tyre_size',
        'quantity',
        'budget_range',
        'delivery_location',
        'delivery_timeline',
        'notes',
        'status',
        'admin_notes',
        'ip_address',
        'vat_number',
        'vat_valid',
    ];

    protected $hidden = [
        'ip_address',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
