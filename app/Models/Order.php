<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'ref',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'city',
        'postal_code',
        'country',
        'payment_method',
        'subtotal',
        'delivery_cost',
        'total',
        'status',
        'payment_status',
        'mode',
        'admin_notes',
        'ip_address',
        'vat_number',
        'vat_valid',
        'payment_intent_id',
        'carrier',
        'tracking_number',
        'estimated_delivery',
    ];

    protected $hidden = [
        'ip_address',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
