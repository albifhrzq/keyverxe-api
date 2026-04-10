<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * List current customer's orders.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['payment', 'orderItems.product'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->input('per_page', 10));

        return response()->json($orders);
    }

    /**
     * Show order detail (must belong to current user).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        // Ensure the order belongs to the authenticated user
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order->load(['orderItems.product', 'payment']);

        return response()->json($order);
    }

    /**
     * Summarize pending unpaid orders for checkout warning UX.
     */
    public function pendingSummary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $maxPendingOrders = max((int) config('services.checkout.max_pending_orders', 3), 1);

        $pendingOrdersQuery = Order::with('payment')
            ->activePendingForUser($userId)
            ->latest();

        $pendingOrdersCount = (clone $pendingOrdersQuery)->count();
        $pendingOrders = $pendingOrdersQuery
            ->limit($maxPendingOrders)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at,
                    'invoice_url' => $order->payment?->xendit_invoice_url,
                    'expires_at' => $order->payment?->expires_at,
                ];
            })
            ->values();

        return response()->json([
            'count' => $pendingOrdersCount,
            'max_pending_orders' => $maxPendingOrders,
            'orders' => $pendingOrders,
        ]);
    }
}
