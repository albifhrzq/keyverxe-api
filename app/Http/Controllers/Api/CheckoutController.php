<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
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

        try {
            return DB::transaction(function () use ($validated, $request, $xenditService) {
                $user = $request->user();
                $totalAmount = 0;
                $orderItems = [];

                // Validate stock and calculate prices from DB
                foreach ($validated['items'] as $item) {
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

                    // Decrement stock
                    $product->decrement('stock', $item['quantity']);
                }

                // Create order
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_number' => Order::generateOrderNumber(),
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
                ]);

                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => $order->load(['orderItems.product', 'payment']),
                    'invoice_url' => $invoice['invoice_url'],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
