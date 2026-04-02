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
        $orders = Order::with('payment')
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
}
