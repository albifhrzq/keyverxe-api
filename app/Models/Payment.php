<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'xendit_invoice_id',
        'xendit_invoice_url',
        'payment_method',
        'amount',
        'status',
        'xendit_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'xendit_response' => 'json',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * A payment belongs to an order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
