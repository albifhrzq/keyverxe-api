<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Xendit webhook callback.
     */
    public function xendit(Request $request): JsonResponse
    {
        // Verify webhook token
        $configuredToken = (string) config('services.xendit.webhook_token');
        $callbackToken = $request->header('x-callback-token');

        if (
            $configuredToken === ''
            || !$callbackToken
            || !hash_equals($configuredToken, (string) $callbackToken)
        ) {
            Log::warning('Xendit webhook: invalid callback token');
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $externalId = $request->input('external_id');
        $status = $request->input('status');
        $paymentMethod = $request->input('payment_method');

        Log::info("Xendit webhook received", [
            'external_id' => $externalId,
            'status' => $status,
        ]);

        return DB::transaction(function () use ($externalId, $status, $paymentMethod, $request) {
            $order = Order::with(['orderItems.product', 'payment'])
                ->where('order_number', $externalId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                Log::warning('Xendit webhook: order not found', ['external_id' => $externalId]);
                return response()->json(['message' => 'Order not found'], 404);
            }

            $payment = $order->payment()->lockForUpdate()->first();

            if (!$payment) {
                Log::warning('Xendit webhook: payment not found', ['order_id' => $order->id]);
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $invoiceId = (string) ($request->input('id') ?? $request->input('invoice_id') ?? '');
            if ($invoiceId !== '' && $invoiceId !== (string) $payment->xendit_invoice_id) {
                Log::warning('Xendit webhook: invoice ID mismatch', [
                    'external_id' => $externalId,
                    'invoice_id' => $invoiceId,
                    'stored_invoice_id' => $payment->xendit_invoice_id,
                ]);

                return response()->json(['message' => 'Invoice mismatch ignored'], 200);
            }

            $webhookAmount = $request->input('amount');
            if ($webhookAmount !== null && (float) $webhookAmount !== (float) $payment->amount) {
                Log::warning('Xendit webhook: amount mismatch', [
                    'external_id' => $externalId,
                    'amount' => $webhookAmount,
                    'stored_amount' => $payment->amount,
                ]);

                return response()->json(['message' => 'Amount mismatch ignored'], 200);
            }

            $normalizedStatus = strtoupper((string) $status);

            if (!in_array($normalizedStatus, ['PAID', 'SETTLED', 'EXPIRED', 'FAILED'], true)) {
                Log::warning('Xendit webhook: unsupported status ignored', [
                    'external_id' => $externalId,
                    'status' => $status,
                ]);

                $payment->update([
                    'xendit_response' => $request->all(),
                ]);

                return response()->json(['message' => 'Unsupported status ignored'], 202);
            }

            // Idempotent: only process pending payment once.
            if ($payment->status !== 'pending') {
                if (in_array($normalizedStatus, ['PAID', 'SETTLED'], true) && $payment->status !== 'paid') {
                    $this->handleLatePaidCallback($order, $payment, $paymentMethod, $request->all());

                    return response()->json(['message' => 'Late paid webhook processed'], 200);
                }

                return response()->json(['message' => 'Already processed'], 200);
            }

            // Map Xendit status to our status.
            switch ($normalizedStatus) {
                case 'PAID':
                case 'SETTLED':
                    $payment->update([
                        'status' => 'paid',
                        'payment_method' => $paymentMethod,
                        'xendit_response' => $request->all(),
                        'paid_at' => now(),
                    ]);
                    $this->commitReservedStock($order);
                    $order->update(['status' => 'processing']);
                    break;

                case 'EXPIRED':
                    $payment->update([
                        'status' => 'expired',
                        'xendit_response' => $request->all(),
                    ]);
                    $this->releaseReservedStock($order);
                    $order->update(['status' => 'cancelled']);
                    break;

                case 'FAILED':
                    $payment->update([
                        'status' => 'failed',
                        'xendit_response' => $request->all(),
                    ]);
                    $this->releaseReservedStock($order);
                    $order->update(['status' => 'cancelled']);
                    break;
            }

            return response()->json(['message' => 'Webhook processed'], 200);
        });
    }

    private function commitReservedStock(Order $order): void
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
            }
        }
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

    private function handleLatePaidCallback(
        Order $order,
        $payment,
        ?string $paymentMethod,
        array $payload,
    ): void {
        $reallocationSucceeded = $this->reallocateStockForLatePaidOrder($order);

        $payment->update([
            'status' => 'paid',
            'payment_method' => $paymentMethod,
            'xendit_response' => $payload,
            'paid_at' => now(),
        ]);

        if ($reallocationSucceeded) {
            $order->update(['status' => 'processing']);
            return;
        }

        Log::error('Xendit webhook: late paid order needs manual reconciliation', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    private function reallocateStockForLatePaidOrder(Order $order): bool
    {
        $products = [];
        $sortedItems = $order->orderItems->sortBy('product_id');

        foreach ($sortedItems as $item) {
            $product = $item->product()->lockForUpdate()->first();

            if (!$product || $product->stock < $item->quantity) {
                return false;
            }

            $products[] = [
                'product' => $product,
                'quantity' => (int) $item->quantity,
            ];
        }

        foreach ($products as $entry) {
            $entry['product']->decrement('stock', $entry['quantity']);
        }

        return true;
    }
}
