<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_process_payment_for_pending_order(): void
    {
        $order = Order::factory()->pending()->create(['total' => 50.00]);
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 50.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'payment_attempt' => [
                    'id',
                    'order_id',
                    'amount',
                    'status',
                    'provider_reference',
                ],
            ],
            'meta' => ['trace_id'],
        ]);
    }

    public function test_requires_idempotency_key_header(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 50.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['detail' => 'Idempotency-Key header is required']);
    }

    public function test_idempotency_key_returns_same_payment_attempt(): void
    {
        $order = Order::factory()->pending()->create(['total' => 50.00]);
        $idempotencyKey = Str::uuid()->toString();

        $response1 = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 50.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response2 = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 50.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $this->assertEquals(
            $response1->json('data.payment_attempt.id'),
            $response2->json('data.payment_attempt.id')
        );
        $this->assertTrue($response2->json('meta.idempotent'));
    }

    public function test_validates_payment_amount_matches_order_total(): void
    {
        $order = Order::factory()->pending()->create(['total' => 50.00]);
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 40.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['title' => 'Validation Error']);
    }

    public function test_cannot_process_payment_for_non_pending_order(): void
    {
        $order = Order::factory()->paid()->create();
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => $order->total,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(422);
    }

    public function test_successful_payment_updates_order_status(): void
    {
        config(['orders.payment_stub_success_rate' => 1.0]);

        $order = Order::factory()->pending()->create(['total' => 50.00]);
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 50.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('success', $response->json('data.payment_attempt.status'));

        $order->refresh();
        $this->assertEquals(OrderStatus::PAID, $order->status);
    }

    public function test_failed_payment_updates_order_status(): void
    {
        config(['orders.payment_stub_success_rate' => 0.0]);

        $order = Order::factory()->pending()->create(['total' => 50.00]);
        $idempotencyKey = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 50.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertStatus(402);
        $this->assertEquals('failed', $response->json('data.payment_attempt.status'));

        $order->refresh();
        $this->assertEquals(OrderStatus::FAILED, $order->status);
    }

    public function test_payment_attempt_is_recorded_in_database(): void
    {
        $order = Order::factory()->pending()->create(['total' => 50.00]);
        $idempotencyKey = Str::uuid()->toString();

        $this->postJson("/api/v1/orders/{$order->id}/pay", [
            'amount' => 50.00,
        ], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'idempotency_key' => $idempotencyKey,
            'amount' => 50.00,
        ]);
    }
}
