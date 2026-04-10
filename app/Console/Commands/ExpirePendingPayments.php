<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Expire pending payments that passed deadline and release reserved stock';

    public function handle(): int
    {
        $expiredCount = 0;
        $now = now();

        $paymentIds = Payment::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id');

        foreach ($paymentIds as $paymentId) {
            $didExpire = DB::transaction(function () use ($paymentId, $now) {
                $payment = Payment::query()->whereKey($paymentId)->first();

                if (!$payment || $payment->status !== 'pending') {
                    return false;
                }

                if (!$payment->expires_at || $payment->expires_at->gt($now)) {
                    return false;
                }

                $order = Order::with('orderItems.product')
                    ->whereKey($payment->order_id)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    return false;
                }

                $payment = $order->payment()->lockForUpdate()->first();

                if (!$payment || $payment->status !== 'pending') {
                    return false;
                }

                if (!$payment->expires_at || $payment->expires_at->gt($now)) {
                    return false;
                }

                $payment->update([
                    'status' => 'expired',
                ]);

                $order->update([
                    'status' => 'cancelled',
                ]);

                $this->releaseReservedStock($order);

                return true;
            }, 3);

            if ($didExpire) {
                $expiredCount++;
            }
        }

        $this->info("Expired {$expiredCount} pending payment(s).");

        return self::SUCCESS;
    }

    private function releaseReservedStock(Order $order): void
    {
        $sortedItems = $order->orderItems->sortBy('product_id');

        foreach ($sortedItems as $item) {
            $product = $item->product()->lockForUpdate()->first();

            if (!$product) {
                continue;
            }

            $reservedQuantity = (int) $product->reserved_quantity;
            $releaseQuantity = min($reservedQuantity, (int) $item->quantity);

            if ($releaseQuantity > 0) {
                $product->decrement('reserved_quantity', $releaseQuantity);
                $product->increment('stock', $releaseQuantity);
            }
        }
    }
}
