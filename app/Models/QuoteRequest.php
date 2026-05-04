<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
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
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
        'delivery_address',
        'delivery_city',
        'delivery_postal_code',
    ];

    protected $hidden = [
        'ip_address',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
