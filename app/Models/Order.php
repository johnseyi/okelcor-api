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
