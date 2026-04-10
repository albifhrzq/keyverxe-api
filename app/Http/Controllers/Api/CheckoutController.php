<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\XenditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private const IDEMPOTENCY_HEADER = 'X-Idempotency-Key';

    /**
     * Process checkout — validate items, create order, call Xendit.
     */
    public function store(Request $request, XenditService $xenditService): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $idempotencyKey = trim((string) $request->header(self::IDEMPOTENCY_HEADER));

        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        if (strlen($idempotencyKey) > 255) {
            return response()->json([
                'message' => 'Invalid idempotency key.',
            ], 422);
        }

        $existingOrder = $this->findExistingOrderByIdempotency($user->id, $idempotencyKey);

        if ($existingOrder) {
            return response()->json([
                'message' => 'Checkout already processed. Returning existing order.',
                'order' => $existingOrder,
                'invoice_url' => $existingOrder->payment?->xendit_invoice_url,
            ], 200);
        }

        try {
            return DB::transaction(function () use ($validated, $user, $xenditService, $idempotencyKey) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                $existingOrder = $this->findExistingOrderByIdempotency($user->id, $idempotencyKey);

                if ($existingOrder) {
                    return response()->json([
                        'message' => 'Checkout already processed. Returning existing order.',
                        'order' => $existingOrder,
                        'invoice_url' => $existingOrder->payment?->xendit_invoice_url,
                    ], 200);
                }

                $maxPendingOrders = max((int) config('services.checkout.max_pending_orders', 3), 1);
                $pendingCount = Order::activePendingForUser($user->id)->count();

                if ($pendingCount >= $maxPendingOrders) {
                    return response()->json([
                        'message' => "You already have {$pendingCount} pending orders. Please complete payment before creating a new order.",
                        'pending_orders_count' => $pendingCount,
                        'max_pending_orders' => $maxPendingOrders,
                    ], 422);
                }

                $totalAmount = 0;
                $orderItems = [];
                $invoiceDuration = max((int) config('services.xendit.invoice_duration', 86400), 60);
                $expiresAt = now()->addSeconds($invoiceDuration);
                $sortedItems = collect($validated['items'])
                    ->sortBy('product_id')
                    ->values()
                    ->all();

                // Validate stock and calculate prices from DB
                foreach ($sortedItems as $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new \Exception("Product not found or inactive: ID {$item['product_id']}");
                    }

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock}");
                    }

                    $subtotal = $product->price * $item['quantity'];
                    $totalAmount += $subtotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                        'subtotal' => $subtotal,
                    ];

                    // Reserve available stock while payment is pending.
                    $product->decrement('stock', $item['quantity']);
                    $product->increment('reserved_quantity', $item['quantity']);
                }

                // Create order
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_number' => Order::generateOrderNumber(),
                    'idempotency_key' => $idempotencyKey,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'shipping_address' => $validated['shipping_address'],
                    'phone' => $validated['phone'],
                ]);

                // Create order items
                $order->orderItems()->createMany($orderItems);

                // Create Xendit invoice
                $invoice = $xenditService->createInvoice([
                    'external_id' => $order->order_number,
                    'amount' => $totalAmount,
                    'payer_email' => $user->email,
                    'description' => "Payment for order {$order->order_number}",
                ]);

                // Create payment record
                $order->payment()->create([
                    'xendit_invoice_id' => $invoice['invoice_id'],
                    'xendit_invoice_url' => $invoice['invoice_url'],
                    'amount' => $totalAmount,
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                ]);

                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => $order->load(['orderItems.product', 'payment']),
                    'invoice_url' => $invoice['invoice_url'],
                ], 201);
            });
        } catch (QueryException $e) {
            if ($this->isIdempotencyConflict($e)) {
                $existingOrder = $this->findExistingOrderByIdempotency($user->id, $idempotencyKey);

                if ($existingOrder) {
                    return response()->json([
                        'message' => 'Checkout already processed. Returning existing order.',
                        'order' => $existingOrder,
                        'invoice_url' => $existingOrder->payment?->xendit_invoice_url,
                    ], 200);
                }
            }

            return response()->json([
                'message' => 'Failed to process checkout request.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function findExistingOrderByIdempotency(int $userId, string $idempotencyKey): ?Order
    {
        return Order::with(['orderItems.product', 'payment'])
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function isIdempotencyConflict(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && str_contains(strtolower($exception->getMessage()), 'idempotency_key');
    }
}
