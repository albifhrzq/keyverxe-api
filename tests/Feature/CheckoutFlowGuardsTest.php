<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutFlowGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_is_idempotent_for_same_key(): void
    {
        $user = $this->createCustomerUser();
        $product = $this->createProduct(stock: 10, reservedQuantity: 0);

        Sanctum::actingAs($user);

        $this->mock(XenditService::class, function ($mock): void {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn([
                    'invoice_id' => 'inv-checkout-001',
                    'invoice_url' => 'https://pay.example/inv-checkout-001',
                ]);
        });

        $payload = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            'shipping_address' => 'Jl. Sudirman No. 1',
            'phone' => '081234567890',
        ];

        $headers = [
            'Accept' => 'application/json',
            'X-Idempotency-Key' => 'checkout-key-001',
        ];

        $firstResponse = $this->postJson('/api/checkout', $payload, $headers);
        $secondResponse = $this->postJson('/api/checkout', $payload, $headers);

        $firstResponse->assertCreated();
        $secondResponse->assertOk()
            ->assertJsonPath('message', 'Checkout already processed. Returning existing order.');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('orders', [
            'idempotency_key' => 'checkout-key-001',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
            'reserved_quantity' => 2,
        ]);
    }

    public function test_same_idempotency_key_is_isolated_per_user(): void
    {
        $firstUser = $this->createCustomerUser();
        $secondUser = $this->createCustomerUser();
        $product = $this->createProduct(stock: 20, reservedQuantity: 0);

        $this->mock(XenditService::class, function ($mock): void {
            $mock->shouldReceive('createInvoice')
                ->twice()
                ->andReturn(
                    [
                        'invoice_id' => 'inv-user-1',
                        'invoice_url' => 'https://pay.example/inv-user-1',
                    ],
                    [
                        'invoice_id' => 'inv-user-2',
                        'invoice_url' => 'https://pay.example/inv-user-2',
                    ],
                );
        });

        $payload = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            'shipping_address' => 'Jl. Sudirman No. 1',
            'phone' => '081234567890',
        ];

        Sanctum::actingAs($firstUser);
        $firstResponse = $this->postJson('/api/checkout', $payload, [
            'Accept' => 'application/json',
            'X-Idempotency-Key' => 'shared-key-001',
        ]);

        Sanctum::actingAs($secondUser);
        $secondResponse = $this->postJson('/api/checkout', $payload, [
            'Accept' => 'application/json',
            'X-Idempotency-Key' => 'shared-key-001',
        ]);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $this->assertDatabaseCount('orders', 2);
    }

    public function test_checkout_is_blocked_when_pending_order_limit_reached(): void
    {
        $user = $this->createCustomerUser();
        $product = $this->createProduct(stock: 10, reservedQuantity: 0);

        Sanctum::actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->createOrderWithPayment(
                user: $user,
                orderStatus: 'pending',
                paymentStatus: 'pending',
            );
        }

        $response = $this->postJson('/api/checkout', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            'shipping_address' => 'Jl. Thamrin No. 1',
            'phone' => '081234567891',
        ], [
            'Accept' => 'application/json',
            'X-Idempotency-Key' => 'checkout-key-limit-001',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('max_pending_orders', 3)
            ->assertJsonPath('pending_orders_count', 3);
    }

    public function test_pending_summary_returns_only_active_pending_orders(): void
    {
        $user = $this->createCustomerUser();

        Sanctum::actingAs($user);

        $this->createOrderWithPayment(
            user: $user,
            orderStatus: 'pending',
            paymentStatus: 'pending',
        );

        $this->createOrderWithPayment(
            user: $user,
            orderStatus: 'pending',
            paymentStatus: 'pending',
        );

        $this->createOrderWithPayment(
            user: $user,
            orderStatus: 'processing',
            paymentStatus: 'paid',
        );

        $response = $this->getJson('/api/orders/pending-summary');

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('max_pending_orders', 3)
            ->assertJsonCount(2, 'orders');
    }

    public function test_expire_pending_payments_command_marks_order_and_releases_reservation(): void
    {
        $user = $this->createCustomerUser();
        $product = $this->createProduct(stock: 5, reservedQuantity: 2);

        $order = $this->createOrderWithPayment(
            user: $user,
            orderStatus: 'pending',
            paymentStatus: 'pending',
            product: $product,
            quantity: 2,
            expiresAt: now()->subMinutes(10),
        );

        Artisan::call('payments:expire-pending');

        $this->assertDatabaseHas('payments', [
            'id' => $order->payment->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 7,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_webhook_ignores_unknown_status_without_mutating_payment_state(): void
    {
        config(['services.xendit.webhook_token' => 'test-token']);

        $user = $this->createCustomerUser();
        $product = $this->createProduct(stock: 5, reservedQuantity: 2);

        $order = $this->createOrderWithPayment(
            user: $user,
            orderStatus: 'pending',
            paymentStatus: 'pending',
            product: $product,
            quantity: 2,
        );

        $response = $this->postJson('/api/webhook/xendit', [
            'external_id' => $order->order_number,
            'status' => 'PENDING',
            'id' => $order->payment->xendit_invoice_id,
            'amount' => $order->payment->amount,
        ], [
            'x-callback-token' => 'test-token',
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('payments', [
            'id' => $order->payment->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 5,
            'reserved_quantity' => 2,
        ]);
    }

    public function test_late_paid_webhook_after_expiry_is_reconciled(): void
    {
        config(['services.xendit.webhook_token' => 'test-token']);

        $user = $this->createCustomerUser();
        $product = $this->createProduct(stock: 5, reservedQuantity: 2);

        $order = $this->createOrderWithPayment(
            user: $user,
            orderStatus: 'pending',
            paymentStatus: 'pending',
            product: $product,
            quantity: 2,
            expiresAt: now()->subMinutes(10),
        );

        Artisan::call('payments:expire-pending');

        $response = $this->postJson('/api/webhook/xendit', [
            'external_id' => $order->order_number,
            'status' => 'PAID',
            'payment_method' => 'BANK_TRANSFER',
            'id' => $order->payment->xendit_invoice_id,
            'amount' => $order->payment->amount,
        ], [
            'x-callback-token' => 'test-token',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $order->payment->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 5,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_admin_cannot_complete_shipped_order_before_payment_paid(): void
    {
        $admin = $this->createAdminUser();
        $customer = $this->createCustomerUser();

        Sanctum::actingAs($admin);

        $order = $this->createOrderWithPayment(
            user: $customer,
            orderStatus: 'shipped',
            paymentStatus: 'pending',
        );

        $failResponse = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'completed',
        ]);

        $failResponse->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot move to fulfillment status before payment is paid.');

        $order->payment()->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $successResponse = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'completed',
        ]);

        $successResponse->assertOk()
            ->assertJsonPath('order.status', 'completed');
    }

    public function test_admin_cancel_releases_reserved_stock_and_marks_payment_failed(): void
    {
        $admin = $this->createAdminUser();
        $customer = $this->createCustomerUser();
        $product = $this->createProduct(stock: 8, reservedQuantity: 2);

        Sanctum::actingAs($admin);

        $order = $this->createOrderWithPayment(
            user: $customer,
            orderStatus: 'pending',
            paymentStatus: 'pending',
            product: $product,
            quantity: 2,
        );

        $response = $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertOk()
            ->assertJsonPath('order.status', 'cancelled');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
            'reserved_quantity' => 0,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $order->payment->id,
            'status' => 'failed',
        ]);
    }

    private function createCategory(): Category
    {
        $name = 'Category ' . Str::upper(Str::random(5));

        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name . '-' . Str::lower(Str::random(4))),
            'description' => 'Test category',
        ]);
    }

    private function createProduct(int $stock = 10, int $reservedQuantity = 0): Product
    {
        $category = $this->createCategory();
        $name = 'Product ' . Str::upper(Str::random(5));

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name . '-' . Str::lower(Str::random(4))),
            'description' => 'Test product',
            'price' => 500000,
            'stock' => $stock,
            'reserved_quantity' => $reservedQuantity,
            'is_active' => true,
        ]);
    }

    private function createOrderWithPayment(
        User $user,
        string $orderStatus,
        string $paymentStatus,
        ?Product $product = null,
        int $quantity = 1,
        $expiresAt = null,
    ): Order {
        $orderNumber = 'KVX-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'total_amount' => 500000,
            'status' => $orderStatus,
            'shipping_address' => 'Jl. Test No. 123',
            'phone' => '081234567899',
        ]);

        if ($product) {
            $order->orderItems()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => 500000,
                'subtotal' => 500000 * $quantity,
            ]);
        }

        $order->payment()->create([
            'xendit_invoice_id' => 'inv-' . Str::lower(Str::random(12)),
            'xendit_invoice_url' => 'https://pay.example/' . Str::lower(Str::random(8)),
            'amount' => 500000,
            'status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
            'expires_at' => $expiresAt,
        ]);

        return $order->fresh(['payment', 'orderItems.product']);
    }

    private function createCustomerUser(): User
    {
        return User::factory()->create([
            'role' => 'customer',
        ]);
    }

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }
}
