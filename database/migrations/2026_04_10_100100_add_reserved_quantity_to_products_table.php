<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reserved_quantity')->default(0)->after('stock');
            $table->index(['stock', 'reserved_quantity']);
        });

        $pendingReservations = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_reserved'))
            ->where('orders.status', 'pending')
            ->where('payments.status', 'pending')
            ->groupBy('order_items.product_id')
            ->get();

        foreach ($pendingReservations as $reservation) {
            DB::table('products')
                ->where('id', $reservation->product_id)
                ->update([
                    'reserved_quantity' => (int) $reservation->total_reserved,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_stock_reserved_quantity_index');
            $table->dropColumn('reserved_quantity');
        });
    }
};
