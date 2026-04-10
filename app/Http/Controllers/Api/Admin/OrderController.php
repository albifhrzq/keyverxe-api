<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * List all orders with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'payment']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($orders);
    }

    /**
     * Show order detail.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'orderItems.product', 'payment']);

        return response()->json($order);
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,completed,cancelled'],
        ]);

        return DB::transaction(function () use ($order, $validated) {
            $lockedOrder = Order::with(['user', 'payment', 'orderItems.product'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPayment = $lockedOrder->payment()->lockForUpdate()->first();

            $currentStatus = $lockedOrder->status;
            $targetStatus = $validated['status'];

            if ($targetStatus === $currentStatus) {
                return response()->json([
                    'message' => 'Order status updated successfully',
                    'order' => $lockedOrder,
                ]);
            }

            if (!$this->isValidTransition($currentStatus, $targetStatus)) {
                return response()->json([
                    'message' => "Invalid order transition from {$currentStatus} to {$targetStatus}.",
                ], 422);
            }

            if (in_array($targetStatus, ['processing', 'shipped', 'completed'], true)) {
                $paymentStatus = $lockedPayment?->status;

                if ($paymentStatus !== 'paid') {
                    return response()->json([
                        'message' => 'Order cannot move to fulfillment status before payment is paid.',
                    ], 422);
                }
            }

            $lockedOrder->update(['status' => $targetStatus]);

            if ($targetStatus === 'cancelled') {
                if ($lockedPayment?->status === 'paid') {
                    $this->restockPaidOrder($lockedOrder);
                } else {
                    $this->releaseReservedStock($lockedOrder);
                }

                if ($lockedPayment && $lockedPayment->status === 'pending') {
                    $lockedPayment->update(['status' => 'failed']);
                }
            }

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $lockedOrder->fresh()->load(['user', 'payment']),
            ]);
        });
    }

    private function isValidTransition(string $currentStatus, string $targetStatus): bool
    {
        $transitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];

        return in_array($targetStatus, $transitions[$currentStatus] ?? [], true);
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

    private function restockPaidOrder(Order $order): void
    {
        $sortedItems = $order->orderItems->sortBy('product_id');

        foreach ($sortedItems as $item) {
            $product = $item->product()->lockForUpdate()->first();

            if (!$product) {
                continue;
            }

            $product->increment('stock', $item->quantity);
        }
    }
}
