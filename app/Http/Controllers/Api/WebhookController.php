<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Xendit webhook callback.
     */
    public function xendit(Request $request): JsonResponse
    {
        // Verify webhook token
        $callbackToken = $request->header('x-callback-token');
        if ($callbackToken !== config('services.xendit.webhook_token')) {
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

        $order = Order::where('order_number', $externalId)->first();

        if (!$order) {
            Log::warning("Xendit webhook: order not found", ['external_id' => $externalId]);
            return response()->json(['message' => 'Order not found'], 404);
        }

        $payment = $order->payment;

        if (!$payment) {
            Log::warning("Xendit webhook: payment not found", ['order_id' => $order->id]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Idempotent: skip if already processed
        if ($payment->status === 'paid') {
            return response()->json(['message' => 'Already processed'], 200);
        }

        // Map Xendit status to our status
        switch (strtoupper($status)) {
            case 'PAID':
            case 'SETTLED':
                $payment->update([
                    'status' => 'paid',
                    'payment_method' => $paymentMethod,
                    'xendit_response' => $request->all(),
                    'paid_at' => now(),
                ]);
                $order->update(['status' => 'processing']);
                break;

            case 'EXPIRED':
                $payment->update([
                    'status' => 'expired',
                    'xendit_response' => $request->all(),
                ]);
                $order->update(['status' => 'cancelled']);

                // Restore stock
                foreach ($order->orderItems as $item) {
                    $item->product->increment('stock', $item->quantity);
                }
                break;

            default:
                $payment->update([
                    'status' => 'failed',
                    'xendit_response' => $request->all(),
                ]);
                $order->update(['status' => 'cancelled']);

                // Restore stock
                foreach ($order->orderItems as $item) {
                    $item->product->increment('stock', $item->quantity);
                }
                break;
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }
}
