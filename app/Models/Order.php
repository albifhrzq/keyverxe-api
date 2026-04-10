<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'idempotency_key',
        'total_amount',
        'status',
        'shipping_address',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * An order belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * An order has many items.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * An order has one payment.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Scope pending orders that are still waiting for payment.
     */
    public function scopeActivePendingForUser($query, int $userId)
    {
        return $query
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->where(function ($pendingQuery) {
                $pendingQuery->whereDoesntHave('payment')
                    ->orWhereHas('payment', function ($paymentQuery) {
                        $paymentQuery->where('status', 'pending');
                    });
            });
    }

    /**
     * Generate a unique order number.
     * Format: KVX-YYYYMMDD-XXXX
     */
    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -4));

        return "KVX-{$date}-{$random}";
    }
}
