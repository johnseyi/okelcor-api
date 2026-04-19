<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'customer_id',
        'invoice_number',
        'issued_at',
        'due_at',
        'amount',
        'status',
        'pdf_url',
        'order_ref',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'due_at'    => 'datetime',
        'amount'    => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
