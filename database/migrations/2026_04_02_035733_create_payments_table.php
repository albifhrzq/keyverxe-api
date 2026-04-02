<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('xendit_invoice_id')->unique()->nullable();
            $table->string('xendit_invoice_url')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'expired', 'failed'])->default('pending');
            $table->json('xendit_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
